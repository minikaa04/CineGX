<?php
/**
 * CineGX — Koleksiyon Yönetim Paneli
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

requireAdmin();

$selectedCollectionId = (int)($_GET['collection_id'] ?? 0);
$successMsg = '';
$errorMsg = '';

// Koleksiyonları Çek
$collections = $pdo->query("SELECT * FROM collections ORDER BY id ASC")->fetchAll();

// Seçili koleksiyon varsa içeriğini çek
$collectionItems = [];
if ($selectedCollectionId > 0) {
    $stmt = $pdo->prepare("
        SELECT ci.id AS item_assoc_id, ci.content_type, ci.content_id,
               COALESCE(m.title, s.title) AS title
        FROM collection_items ci
        LEFT JOIN movies m ON ci.content_type = 'movie' AND ci.content_id = m.id
        LEFT JOIN series s ON ci.content_type = 'series' AND ci.content_id = s.id
        WHERE ci.collection_id = ?
        ORDER BY ci.id DESC
    ");
    $stmt->execute([$selectedCollectionId]);
    $collectionItems = $stmt->fetchAll();
}

// Koleksiyona İçerik Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    if (!validateCsrfToken()) {
        $errorMsg = 'CSRF Doğrulama hatası!';
    } else {
        $rawContent = $_POST['content'] ?? ''; // Format: "movie:1" veya "series:5"
        $parts = explode(':', $rawContent);
        
        if (count($parts) === 2 && $selectedCollectionId > 0) {
            $type = $parts[0] === 'series' ? 'series' : 'movie';
            $contentId = (int)$parts[1];
            
            try {
                // Zaten ekli mi kontrol et
                $check = $pdo->prepare("SELECT id FROM collection_items WHERE collection_id = ? AND content_id = ? AND content_type = ?");
                $check->execute([$selectedCollectionId, $contentId, $type]);
                if ($check->fetch()) {
                    $errorMsg = 'Bu içerik zaten bu koleksiyonda kayıtlı.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO collection_items (collection_id, content_id, content_type) VALUES (?, ?, ?)");
                    $stmt->execute([$selectedCollectionId, $contentId, $type]);
                    $successMsg = 'İçerik koleksiyona başarıyla eklendi.';
                    
                    // Listeyi yenile
                    $stmt = $pdo->prepare("
                        SELECT ci.id AS item_assoc_id, ci.content_type, ci.content_id,
                               COALESCE(m.title, s.title) AS title
                        FROM collection_items ci
                        LEFT JOIN movies m ON ci.content_type = 'movie' AND ci.content_id = m.id
                        LEFT JOIN series s ON ci.content_type = 'series' AND ci.content_id = s.id
                        WHERE ci.collection_id = ?
                        ORDER BY ci.id DESC
                    ");
                    $stmt->execute([$selectedCollectionId]);
                    $collectionItems = $stmt->fetchAll();
                }
            } catch (PDOException $e) {
                $errorMsg = 'Hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Koleksiyondan İçerik Kaldırma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_item') {
    if (!validateCsrfToken()) {
        $errorMsg = 'CSRF Doğrulama hatası!';
    } else {
        $assocId = (int)($_POST['assoc_id'] ?? 0);
        if ($assocId > 0) {
            $stmt = $pdo->prepare("DELETE FROM collection_items WHERE id = ?");
            $stmt->execute([$assocId]);
            $successMsg = 'İçerik koleksiyondan kaldırıldı.';
            
            // Listeyi yenile
            if ($selectedCollectionId > 0) {
                $stmt = $pdo->prepare("
                    SELECT ci.id AS item_assoc_id, ci.content_type, ci.content_id,
                           COALESCE(m.title, s.title) AS title
                    FROM collection_items ci
                    LEFT JOIN movies m ON ci.content_type = 'movie' AND ci.content_id = m.id
                    LEFT JOIN series s ON ci.content_type = 'series' AND ci.content_id = s.id
                    WHERE ci.collection_id = ?
                    ORDER BY ci.id DESC
                ");
                $stmt->execute([$selectedCollectionId]);
                $collectionItems = $stmt->fetchAll();
            }
        }
    }
}

// Tüm Filmleri ve Dizileri Çek (Ekleme dropdown listesi için)
$allMovies = $pdo->query("SELECT id, title FROM movies ORDER BY title ASC")->fetchAll();
$allSeries = $pdo->query("SELECT id, title FROM series ORDER BY title ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koleksiyonları Yönet — CineGX</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-page { padding: 120px 4% 60px; max-width: 1200px; margin: 0 auto; }
        .admin-title { font-size: 2.2rem; font-weight: 800; border-left: 5px solid var(--accent); padding-left: 15px; margin-bottom: 30px; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; }
        .alert-success { background: rgba(70, 211, 105, 0.1); border: 1px solid rgba(70, 211, 105, 0.3); color: var(--success); }
        .alert-error { background: rgba(229, 9, 20, 0.1); border: 1px solid rgba(229, 9, 20, 0.3); color: var(--error); }

        .collections-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .list-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px; padding: 25px;
        }
        .list-card-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; }
        
        .collection-links { display: flex; flex-direction: column; gap: 10px; }
        .collection-link-item {
            display: block; padding: 12px 18px; border-radius: 6px;
            background: rgba(255,255,255,0.02); color: var(--text-secondary);
            text-decoration: none; font-weight: 600; font-size: 0.95rem;
            transition: 0.2s; border: 1px solid transparent;
        }
        .collection-link-item:hover { background: rgba(255,255,255,0.06); }
        .collection-link-item.active {
            background: rgba(229, 9, 20, 0.1); border-color: var(--accent); color: white;
        }

        .add-form-inline {
            display: flex; gap: 15px; align-items: center; margin-bottom: 25px;
            background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px;
        }
        .select-inline {
            flex-grow: 1; background: var(--bg-input); color: white;
            border: 1px solid var(--border); padding: 10px; border-radius: 6px;
            font-size: 0.95rem; cursor: pointer; outline: none;
        }
        .btn-inline-add {
            background: var(--accent); color: white; border: none;
            padding: 10px 24px; border-radius: 6px; font-weight: 700;
            font-size: 0.95rem; cursor: pointer; transition: 0.2s;
        }
        .btn-inline-add:hover { background: var(--accent-hover); }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { font-weight: 700; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; }
        td { font-size: 0.92rem; }

        .btn-delete-small {
            background: var(--accent); color: white; border: none;
            padding: 6px 12px; border-radius: 4px; font-weight: 600;
            font-size: 0.78rem; cursor: pointer; transition: 0.2s;
        }
        .btn-delete-small:hover { background: var(--accent-hover); }
    </style>
</head>
<body>

<header class="navbar scrolled">
    <div class="navbar-inner">
        <a href="../index.php" class="navbar-logo">Cine<span>GX</span> Admin</a>
        <nav class="navbar-nav">
            <a href="index.php" class="nav-link">Panel</a>
            <a href="movies.php" class="nav-link">Filmler</a>
            <a href="series.php" class="nav-link">Diziler</a>
            <a href="comments.php" class="nav-link">Yorumlar</a>
            <a href="collections.php" class="nav-link active">Koleksiyonlar</a>
            <a href="videos.php" class="nav-link">Videolar</a>
        </nav>
        <div class="navbar-user">
            <a href="../index.php" class="btn-logout">Siteden Çık</a>
        </div>
    </div>
</header>

<div class="admin-page">
    <h1 class="admin-title">Koleksiyonları Yönet</h1>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= $errorMsg ?></div>
    <?php endif; ?>

    <div class="collections-layout">
        <!-- Sol Panel: Koleksiyon Listesi -->
        <div class="list-card">
            <h2 class="list-card-title">Koleksiyon Listesi</h2>
            <div class="collection-links">
                <?php foreach ($collections as $col): ?>
                    <a href="collections.php?collection_id=<?= $col['id'] ?>" class="collection-link-item <?= $selectedCollectionId === $col['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($col['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sağ Panel: Koleksiyon İçeriği -->
        <div class="list-card">
            <?php if ($selectedCollectionId <= 0): ?>
                <h2 class="list-card-title" style="color: var(--text-muted); text-align: center; margin-top: 40px;">Düzenlemek istediğiniz koleksiyonu soldan seçin.</h2>
            <?php else: ?>
                <?php 
                    $selectedColName = '';
                    foreach ($collections as $col) {
                        if ($col['id'] === $selectedCollectionId) {
                            $selectedColName = $col['title'];
                            break;
                        }
                    }
                ?>
                <h2 class="list-card-title">"<?= htmlspecialchars($selectedColName) ?>" İçeriği</h2>
                
                <!-- İçerik Ekleme Formu -->
                <form action="collections.php?collection_id=<?= $selectedCollectionId ?>" method="POST" class="add-form-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_item">
                    <select name="content" class="select-inline" required>
                        <option value="">Koleksiyona eklenecek içerik seçin...</option>
                        <optgroup label="Filmler">
                            <?php foreach ($allMovies as $m): ?>
                                <option value="movie:<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Diziler">
                            <?php foreach ($allSeries as $s): ?>
                                <option value="series:<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <button type="submit" class="btn-inline-add">Ekle</button>
                </form>

                <!-- İçerik Listesi -->
                <?php if (empty($collectionItems)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 25px;">Bu koleksiyonda henüz içerik bulunmamaktadır.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>İçerik Adı</th>
                                <th>Tür</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collectionItems as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['title'] ?? 'Silinmiş İçerik') ?></strong></td>
                                    <td><?= $item['content_type'] === 'series' ? 'Dizi' : 'Film' ?></td>
                                    <td>
                                        <form action="collections.php?collection_id=<?= $selectedCollectionId ?>" method="POST" onsubmit="return confirm('İçeriği koleksiyondan çıkarmak istediğinize emin misiniz?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="assoc_id" value="<?= $item['item_assoc_id'] ?>">
                                            <button type="submit" class="btn-delete-small">Kaldır</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
