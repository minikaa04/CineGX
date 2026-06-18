<?php
/**
 * CineGX — Film Yönetim Paneli
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
        $delId = (int)($_POST['movie_id'] ?? 0);
        if ($delId > 0) {
            $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->execute([$delId]);
            $successMsg = 'Film başarıyla silindi.';
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
        $status = $_POST['status'] ?? 'released';
        $poster_url = trim($_POST['poster_url'] ?? '');
        $backdrop_url = trim($_POST['backdrop_url'] ?? '');
        $director = trim($_POST['director'] ?? '');

        if (empty($title) || empty($slug) || empty($description) || $category_id <= 0) {
            $errorMsg = 'Lütfen gerekli alanları (Başlık, Slug, Açıklama, Kategori) doldurun.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO movies (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, poster_url, backdrop_url, director)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $status, $category_id, $poster_url, $backdrop_url, $director]);
                    $successMsg = 'Film başarıyla eklendi.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE movies 
                        SET title = ?, slug = ?, description = ?, imdb_score = ?, release_year = ?, age_rating = ?, status = ?, category_id = ?, poster_url = ?, backdrop_url = ?, director = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $status, $category_id, $poster_url, $backdrop_url, $director, $id]);
                    $successMsg = 'Film başarıyla güncellendi.';
                }
                $action = 'list';
            } catch (PDOException $e) {
                $errorMsg = 'Hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Düzenlenecek Veriyi Çek
$movie = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
    if (!$movie) {
        $errorMsg = 'Düzenlenecek film bulunamadı.';
        $action = 'list';
    }
}

// Tüm Filmleri Listele
$movies = [];
if ($action === 'list') {
    $movies = $pdo->query("
        SELECT m.*, c.name AS category_name 
        FROM movies m 
        JOIN categories c ON m.category_id = c.id 
        ORDER BY m.title ASC
    ")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmleri Yönet — CineGX</title>
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
            <a href="movies.php" class="nav-link active">Filmler</a>
            <a href="series.php" class="nav-link">Diziler</a>
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
            <h1 class="admin-title">Filmleri Yönet</h1>
            <a href="movies.php?action=add" class="btn-add-new">+ Yeni Film Ekle</a>
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
                        <th>Film Adı</th>
                        <th>Yönetmen</th>
                        <th>Kategori</th>
                        <th>Yıl</th>
                        <th>Puan</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $movie): ?>
                        <tr>
                            <td>
                                <?php if (!empty($movie['poster_url'])): ?>
                                    <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="" class="movie-thumb">
                                <?php else: ?>
                                    <div class="movie-thumb" style="display:flex;align-items:center;justify-content:center;font-size:0.7rem;color:#888;">Yok</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($movie['title']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($movie['slug']) ?></small></td>
                            <td><?= htmlspecialchars($movie['director'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($movie['category_name']) ?></td>
                            <td><?= $movie['release_year'] ?></td>
                            <td style="color:#FFD700;font-weight:700;">★ <?= number_format($movie['imdb_score'], 1) ?></td>
                            <td>
                                <div class="action-flex">
                                    <a href="movies.php?action=edit&id=<?= $movie['id'] ?>" class="btn-edit">Düzenle</a>
                                    <form action="movies.php?action=delete" method="POST" onsubmit="return confirm('Bu filmi silmek istediğinize emin misiniz?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="movie_id" value="<?= $movie['id'] ?>">
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
        <h1 class="admin-title"><?= $action === 'add' ? 'Yeni Film Ekle' : 'Filmi Düzenle' ?></h1>
        
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?= $errorMsg ?></div>
        <?php endif; ?>

        <form action="movies.php?action=<?= $action ?><?= $action === 'edit' ? '&id='.$id : '' ?>" method="POST" class="admin-form">
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="admin-form-group">
                    <label for="title">Film Başlığı *</label>
                    <input type="text" name="title" id="title" required value="<?= htmlspecialchars($movie['title'] ?? '') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="slug">Slug (Afiş & Video İsmiyle Eşleşmeli) *</label>
                    <input type="text" name="slug" id="slug" required value="<?= htmlspecialchars($movie['slug'] ?? '') ?>" placeholder="Örn: Dune">
                </div>
                <div class="admin-form-group form-full">
                    <label for="description">Açıklama *</label>
                    <textarea name="description" id="description" rows="5" required><?= htmlspecialchars($movie['description'] ?? '') ?></textarea>
                </div>
                <div class="admin-form-group">
                    <label for="imdb_score">IMDb Puanı</label>
                    <input type="number" step="0.1" min="0" max="10" name="imdb_score" id="imdb_score" value="<?= htmlspecialchars($movie['imdb_score'] ?? '7.0') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="release_year">Yayın Yılı</label>
                    <input type="number" min="1900" max="2100" name="release_year" id="release_year" value="<?= htmlspecialchars($movie['release_year'] ?? '2024') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="age_rating">Yaş Sınırı</label>
                    <input type="text" name="age_rating" id="age_rating" value="<?= htmlspecialchars($movie['age_rating'] ?? '13+') ?>">
                </div>
                <div class="admin-form-group">
                    <label for="category_id">Kategori *</label>
                    <select name="category_id" id="category_id" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($movie['category_id']) && $movie['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="status">Durum</label>
                    <select name="status" id="status">
                        <option value="released" <?= (isset($movie['status']) && $movie['status'] === 'released') ? 'selected' : '' ?>>Yayınlandı</option>
                        <option value="coming_soon" <?= (isset($movie['status']) && $movie['status'] === 'coming_soon') ? 'selected' : '' ?>>Yakında</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="director">Yönetmen</label>
                    <input type="text" name="director" id="director" value="<?= htmlspecialchars($movie['director'] ?? '') ?>">
                </div>
                <div class="admin-form-group form-full">
                    <label for="poster_url">Afiş Görsel URL (TMDB)</label>
                    <input type="url" name="poster_url" id="poster_url" value="<?= htmlspecialchars($movie['poster_url'] ?? '') ?>">
                </div>
                <div class="admin-form-group form-full">
                    <label for="backdrop_url">Geniş Arka Plan Görsel URL (TMDB)</label>
                    <input type="url" name="backdrop_url" id="backdrop_url" value="<?= htmlspecialchars($movie['backdrop_url'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">Kaydet</button>
                <a href="movies.php" class="btn-cancel">İptal</a>
            </div>
        </form>
    <?php endif; ?>

</div>

</body>
</html>
