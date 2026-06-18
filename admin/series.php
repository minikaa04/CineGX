<?php
/**
 * CineGX — Dizi Yönetim Paneli
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$successMsg = '';
$errorMsg = '';

// Kategorileri Çek
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Silme İşlemi
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $errorMsg = 'CSRF Doğrulama hatası!';
    } else {
        $delId = (int)($_POST['series_id'] ?? 0);
        if ($delId > 0) {
            $stmt = $pdo->prepare("DELETE FROM series WHERE id = ?");
            $stmt->execute([$delId]);
            $successMsg = 'Dizi başarıyla silindi.';
            $action = 'list';
        }
    }
}

// Kaydetme İşlemi (Ekleme veya Düzenleme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    if (!validateCsrfToken()) {
        $errorMsg = 'CSRF Doğrulama hatası!';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $imdb_score = (float)($_POST['imdb_score'] ?? 0.0);
        $release_year = (int)($_POST['release_year'] ?? 2024);
        $age_rating = trim($_POST['age_rating'] ?? '13+');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $status = $_POST['status'] ?? 'ongoing';
        $total_seasons = (int)($_POST['total_seasons'] ?? 1);
        $poster_url = trim($_POST['poster_url'] ?? '');
        $backdrop_url = trim($_POST['backdrop_url'] ?? '');
        $director = trim($_POST['director'] ?? '');

        if (empty($title) || empty($slug) || empty($description) || $category_id <= 0) {
            $errorMsg = 'Lütfen gerekli alanları (Başlık, Slug, Açıklama, Kategori) doldurun.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO series (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, total_seasons, poster_url, backdrop_url, director)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $status, $category_id, $total_seasons, $poster_url, $backdrop_url, $director]);
                    
                    $series_id = $pdo->lastInsertId();
                    // Yeni eklenen dizi için varsayılan bir 1. Sezon ve Pilot bölüm oluştur
                    $insSeason = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title) VALUES (?, 1, '1. Sezon')");
                    $insSeason->execute([$series_id]);
                    $season_id = $pdo->lastInsertId();
                    
                    $insEpisode = $pdo->prepare("
                        INSERT INTO episodes (season_id, episode_number, title, duration, description)
                        VALUES (?, 1, 'Pilot Bölüm', '45m', 'Dizinin heyecan dolu ilk pilot bölümü.')
                    ");
                    $insEpisode->execute([$season_id]);
                    
                    $successMsg = 'Dizi başarıyla eklendi.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE series 
                        SET title = ?, slug = ?, description = ?, imdb_score = ?, release_year = ?, age_rating = ?, status = ?, category_id = ?, total_seasons = ?, poster_url = ?, backdrop_url = ?, director = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $status, $category_id, $total_seasons, $poster_url, $backdrop_url, $director, $id]);
                    $successMsg = 'Dizi başarıyla güncellendi.';
                }
                $action = 'list';
            } catch (PDOException $e) {
                $errorMsg = 'Hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Düzenlenecek Veriyi Çek
$seriesItem = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$id]);
    $seriesItem = $stmt->fetch();
    if (!$seriesItem) {
        $errorMsg = 'Düzenlenecek dizi bulunamadı.';
        $action = 'list';
    }
}

// Tüm Dizileri Listele
$seriesList = [];
if ($action === 'list') {
    $seriesList = $pdo->query("
        SELECT s.*, c.name AS category_name 
        FROM series s 
        JOIN categories c ON s.category_id = c.id 
        ORDER BY s.title ASC
    ")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dizileri Yönet — CineGX</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-page { padding: 120px 4% 60px; max-width: 1200px; margin: 0 auto; }
        .admin-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .admin-title { font-size: 2.2rem; font-weight: 800; border-left: 5px solid var(--accent); padding-left: 15px; margin: 0; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; }
        .alert-success { background: rgba(70, 211, 105, 0.1); border: 1px solid rgba(70, 211, 105, 0.3); color: var(--success); }
        .alert-error { background: rgba(229, 9, 20, 0.1); border: 1px solid rgba(229, 9, 20, 0.3); color: var(--error); }

        .btn-add-new {
            background: var(--accent); color: white; border: none;
            padding: 10px 24px; border-radius: 6px; font-weight: 700;
            text-decoration: none; font-size: 0.95rem; transition: 0.2s;
        }
        .btn-add-new:hover { background: var(--accent-hover); }

        .table-wrapper {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow-x: auto;
            padding: 20px;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); vertical-align: middle; }
        th { font-weight: 700; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase; }
        td { font-size: 0.92rem; }

        .movie-thumb { width: 45px; height: 65px; border-radius: 4px; object-fit: cover; background: #333; }
        .action-flex { display: flex; gap: 10px; }
        .btn-edit {
            background: #444; color: white; text-decoration: none;
            padding: 8px 16px; border-radius: 4px; font-weight: 600; font-size: 0.82rem; transition: 0.2s;
        }
        .btn-edit:hover { background: #555; }
        .btn-delete {
            background: var(--accent); color: white; border: none;
            padding: 8px 16px; border-radius: 4px; font-weight: 600; font-size: 0.82rem; cursor: pointer; transition: 0.2s;
        }
        .btn-delete:hover { background: var(--accent-hover); }

        /* Form stilleri */
        .admin-form {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px; padding: 40px;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-full { grid-column: span 2; }
        .admin-form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        .admin-form-group label { font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; }
        .admin-form-group input, .admin-form-group select, .admin-form-group textarea {
            background: var(--bg-input); border: 1px solid var(--border);
            padding: 12px; border-radius: 6px; color: white; font-family: inherit; font-size: 0.95rem;
        }
        .admin-form-group input:focus, .admin-form-group select:focus, .admin-form-group textarea:focus {
            border-color: var(--accent); outline: none;
        }
        .form-actions { display: flex; gap: 15px; margin-top: 25px; }
        .btn-save { background: var(--accent); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem; }
        .btn-save:hover { background: var(--accent-hover); }
        .btn-cancel { background: #444; color: white; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 1rem; display: inline-flex; align-items: center; }
        .btn-cancel:hover { background: #555; }
    </style>
</head>
<body>

<header class="navbar scrolled">
    <div class="navbar-inner">
        <a href="../index.php" class="navbar-logo">Cine<span>GX</span> Admin</a>
        <nav class="navbar-nav">
            <a href="index.php" class="nav-link">Panel</a>
            <a href="movies.php" class="nav-link">Filmler</a>
            <a href="series.php" class="nav-link active">Diziler</a>
            <a href="comments.php" class="nav-link">Yorumlar</a>
            <a href="collections.php" class="nav-link">Koleksiyonlar</a>
            <a href="videos.php" class="nav-link">Videolar</a>
        </nav>
        <div class="navbar-user">
            <a href="../index.php" class="btn-logout">Siteden Çık</a>
        </div>
    </div>
</header>

<div class="admin-page">
    
    <?php if ($action === 'list'): ?>
        <div class="admin-title-row">
            <h1 class="admin-title">Dizileri Yönet</h1>
            <a href="series.php?action=add" class="btn-add-new">+ Yeni Dizi Ekle</a>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= $successMsg ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?= $errorMsg ?></div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Afiş</th>
                        <th>Dizi Adı</th>
                        <th>Yönetmen/Yapımcı</th>
                        <th>Kategori</th>
                        <th>Yıl</th>
                        <th>Sezon</th>
                        <th>Puan</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seriesList as $series): ?>
                        <tr>
                            <td>
                                <?php if (!empty($series['poster_url'])): ?>
                                    <img src="<?= htmlspecialchars($series['poster_url']) ?>" alt="" class="movie-thumb">
                                <?php else: ?>
                                    <div class="movie-thumb" style="display:flex;align-items:center;justify-content:center;font-size:0.7rem;color:#888;">Yok</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($series['title']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($series['slug']) ?></small></td>
                            <td><?= htmlspecialchars($series['director'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($series['category_name']) ?></td>
                            <td><?= $series['release_year'] ?></td>
                            <td><?= $series['total_seasons'] ?></td>
                            <td style="color:#FFD700;font-weight:700;">★ <?= number_format($series['imdb_score'], 1) ?></td>
                            <td>
                                <div class="action-flex">
                                    <a href="series.php?action=edit&id=<?= $series['id'] ?>" class="btn-edit">Düzenle</a>
                                    <form action="series.php?action=delete" method="POST" onsubmit="return confirm('Bu diziyi silmek istediğinize emin misiniz?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="series_id" value="<?= $series['id'] ?>">
                                        <button type="submit" class="btn-delete">Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <h1 class="admin-title"><?= $action === 'add' ? 'Yeni Dizi Ekle' : 'Diziyi Düzenle' ?></h1>
        
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?= $errorMsg ?></div>
        <?php endif; ?>

        <form action="series.php?action=<?= $action ?><?= $action === 'edit' ? '&id='.$id : '' ?>" method="POST" class="admin-form">
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="admin-form-group">
                    <label for="title">Dizi Başlığı *</label>
                    <input type="text" name="title" id="title" required value="<?= htmlspecialchars($seriesItem['title'] ?? '') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="slug">Slug (Afiş & Video İsmiyle Eşleşmeli) *</label>
                    <input type="text" name="slug" id="slug" required value="<?= htmlspecialchars($seriesItem['slug'] ?? '') ?>" placeholder="Örn: Breaking Bad">
                </div>
                <div class="admin-form-group form-full">
                    <label for="description">Açıklama *</label>
                    <textarea name="description" id="description" rows="5" required><?= htmlspecialchars($seriesItem['description'] ?? '') ?></textarea>
                </div>
                <div class="admin-form-group">
                    <label for="imdb_score">IMDb Puanı</label>
                    <input type="number" step="0.1" min="0" max="10" name="imdb_score" id="imdb_score" value="<?= htmlspecialchars($seriesItem['imdb_score'] ?? '7.0') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="release_year">Yayın Yılı</label>
                    <input type="number" min="1900" max="2100" name="release_year" id="release_year" value="<?= htmlspecialchars($seriesItem['release_year'] ?? '2024') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="total_seasons">Toplam Sezon</label>
                    <input type="number" min="1" max="100" name="total_seasons" id="total_seasons" value="<?= htmlspecialchars($seriesItem['total_seasons'] ?? '1') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="age_rating">Yaş Sınırı</label>
                    <input type="text" name="age_rating" id="age_rating" value="<?= htmlspecialchars($seriesItem['age_rating'] ?? '13+') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="category_id">Kategori *</label>
                    <select name="category_id" id="category_id" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($seriesItem['category_id']) && $seriesItem['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="status">Durum</label>
                    <select name="status" id="status">
                        <option value="ongoing" <?= (isset($seriesItem['status']) && $seriesItem['status'] === 'ongoing') ? 'selected' : '' ?>>Devam Ediyor</option>
                        <option value="completed" <?= (isset($seriesItem['status']) && $seriesItem['status'] === 'completed') ? 'selected' : '' ?>>Tamamlandı</option>
                        <option value="coming_soon" <?= (isset($seriesItem['status']) && $seriesItem['status'] === 'coming_soon') ? 'selected' : '' ?>>Yakında</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="director">Yönetmen / Yapımcı</label>
                    <input type="text" name="director" id="director" value="<?= htmlspecialchars($seriesItem['director'] ?? '') ?>">
                </div>
                <div class="admin-form-group form-full">
                    <label for="poster_url">Afiş Görsel URL (TMDB)</label>
                    <input type="url" name="poster_url" id="poster_url" value="<?= htmlspecialchars($seriesItem['poster_url'] ?? '') ?>">
                </div>
                <div class="admin-form-group form-full">
                    <label for="backdrop_url">Geniş Arka Plan Görsel URL (TMDB)</label>
                    <input type="url" name="backdrop_url" id="backdrop_url" value="<?= htmlspecialchars($seriesItem['backdrop_url'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">Kaydet</button>
                <a href="series.php" class="btn-cancel">İptal</a>
            </div>
        </form>
    <?php endif; ?>

</div>

</body>
</html>
