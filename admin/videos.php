<?php
/**
 * CineGX — Video Yöneticisi
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

requireAdmin();

$action = $_GET['action'] ?? 'list';
$successMsg = '';
$errorMsg = '';
$videoDir = __DIR__ . '/../videos/';

// Dosya Yükleme (Upload) İşlemi
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $errorMsg = 'CSRF Doğrulama hatası!';
    } else {
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['video_file']['tmp_name'];
            $name = basename($_FILES['video_file']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if ($ext !== 'mp4') {
                $errorMsg = 'Sadece .mp4 formatındaki dosyalar yüklenebilir.';
            } else {
                if (move_uploaded_file($tmpName, $videoDir . $name)) {
                    $successMsg = "Video başarıyla yüklendi: " . htmlspecialchars($name);
                } else {
                    $errorMsg = 'Dosya yükleme sırasında bir hata oluştu. (Klasör yazma izinlerini kontrol edin)';
                }
            }
        } else {
            $errorCode = $_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errorMsg = "Dosya yüklenemedi. Hata Kodu: " . $errorCode . " (php.ini upload_max_filesize limitlerini kontrol edin)";
        }
        $action = 'list';
    }
}

// Dosya Silme (Delete) İşlemi
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $errorMsg = 'CSRF Doğrulama hatası!';
    } else {
        $filename = basename($_POST['filename'] ?? '');
        if (!empty($filename) && file_exists($videoDir . $filename)) {
            if (unlink($videoDir . $filename)) {
                $successMsg = "Video başarıyla silindi: " . htmlspecialchars($filename);
            } else {
                $errorMsg = 'Video silinirken bir hata oluştu.';
            }
        } else {
            $errorMsg = 'Geçersiz dosya adı veya dosya bulunamadı.';
        }
        $action = 'list';
    }
}

// Dosyaları Listeleme
$videos = [];
if (is_dir($videoDir)) {
    $files = scandir($videoDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp4') {
            $path = $videoDir . $file;
            $sizeBytes = filesize($path);
            $sizeMb = round($sizeBytes / 1048576, 2);
            $date = date("d.m.Y H:i", filemtime($path));
            $videos[] = [
                'name' => $file,
                'size' => $sizeMb,
                'date' => $date
            ];
        }
    }
}

// Sunucu Limitleri Bilgisi
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videoları Yönet — CineGX</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-page { padding: 120px 4% 60px; max-width: 1200px; margin: 0 auto; }
        .admin-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .admin-title { font-size: 2.2rem; font-weight: 800; border-left: 5px solid var(--accent); padding-left: 15px; margin: 0; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; }
        .alert-success { background: rgba(70, 211, 105, 0.1); border: 1px solid rgba(70, 211, 105, 0.3); color: var(--success); }
        .alert-error { background: rgba(229, 9, 20, 0.1); border: 1px solid rgba(229, 9, 20, 0.3); color: var(--error); }
        .alert-info { background: rgba(52, 152, 219, 0.1); border: 1px solid rgba(52, 152, 219, 0.3); color: #3498db; }

        .upload-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
        }
        .upload-card h3 { margin-bottom: 15px; font-size: 1.2rem; }
        .form-group-custom { display: flex; align-items: center; gap: 15px; }
        .form-group-custom input[type="file"] {
            background: var(--bg-input); border: 1px solid var(--border);
            padding: 12px; border-radius: 6px; color: white; flex: 1;
        }
        .btn-upload {
            background: var(--accent); color: white; border: none;
            padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
        }
        .btn-upload:hover { background: var(--accent-hover); }

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
        td { font-size: 0.95rem; }

        .btn-delete {
            background: var(--accent); color: white; border: none;
            padding: 8px 16px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: 0.2s;
        }
        .btn-delete:hover { background: var(--accent-hover); }

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
            <a href="collections.php" class="nav-link">Koleksiyonlar</a>
            <a href="videos.php" class="nav-link active">Videolar</a>
        </nav>
        <div class="navbar-user">
            <a href="../index.php" class="btn-logout">Siteden Çık</a>
        </div>
    </div>
</header>

<div class="admin-page">
    
    <div class="admin-title-row">
        <h1 class="admin-title">Videoları Yönet</h1>
    </div>

    <div class="alert alert-info">
        <strong>Sunucu Limitleri:</strong> Maksimum dosya yükleme (upload_max_filesize): <?= $maxUpload ?> | Maksimum POST (post_max_size): <?= $maxPost ?><br>
        <small>Eğer yükleyeceğiniz video bu limitlerden büyükse hata alırsınız. Bu durumda FTP veya doğrudan klasöre kopyalama yapmanız veya php.ini ayarlarını artırmanız gerekir.</small>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= $errorMsg ?></div>
    <?php endif; ?>

    <div class="upload-card">
        <h3>Yeni Video Yükle</h3>
        <form action="videos.php?action=upload" method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="form-group-custom">
                <input type="file" name="video_file" accept="video/mp4" required>
                <button type="submit" class="btn-upload">Yükle</button>
            </div>
            <small style="color:var(--text-muted); display:block; margin-top:10px;">Sadece .mp4 formatındaki dosyalar kabul edilir.</small>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Dosya Adı</th>
                    <th>Boyut (MB)</th>
                    <th>Yüklenme Tarihi</th>
                    <th style="width:100px;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($videos)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:var(--text-muted); padding:30px;">Hiç video bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($videos as $vid): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($vid['name']) ?></strong></td>
                            <td style="color: #4CAF50; font-weight:bold;"><?= $vid['size'] ?> MB</td>
                            <td style="color: var(--text-muted);"><?= $vid['date'] ?></td>
                            <td>
                                <form action="videos.php?action=delete" method="POST" onsubmit="return confirm('Bu videoyu diskten KALICI olarak silmek istediğinize emin misiniz?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($vid['name']) ?>">
                                    <button type="submit" class="btn-delete">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
