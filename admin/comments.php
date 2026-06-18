<?php
/**
 * CineGX — Yorum Yönetim Paneli
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

requireAdmin();

$csrfError = '';
$successMsg = '';

// Yorum Silme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCsrfToken()) {
        $csrfError = 'CSRF Doğrulama hatası! Lütfen tekrar deneyin.';
    } else {
        $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
        if ($commentId > 0) {
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);
            $successMsg = 'Yorum başarıyla silindi.';
        }
    }
}

// Tüm Yorumları Çek
$stmt = $pdo->query("
    SELECT c.*, u.username, u.email, COALESCE(m.title, s.title) AS content_title
    FROM comments c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN movies m ON c.content_type = 'movie' AND c.content_id = m.id
    LEFT JOIN series s ON c.content_type = 'series' AND c.content_id = s.id
    ORDER BY c.created_at DESC
");
$comments = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yorumları Yönet — CineGX</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-page { padding: 120px 4% 60px; max-width: 1200px; margin: 0 auto; }
        .admin-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 30px; border-left: 5px solid var(--accent); padding-left: 15px; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; }
        .alert-success { background: rgba(70, 211, 105, 0.1); border: 1px solid rgba(70, 211, 105, 0.3); color: var(--success); }
        .alert-error { background: rgba(229, 9, 20, 0.1); border: 1px solid rgba(229, 9, 20, 0.3); color: var(--error); }

        .comments-table-wrapper {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow-x: auto;
            padding: 20px;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); vertical-align: top; }
        th { font-weight: 700; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase; }
        td { font-size: 0.92rem; }
        
        .comment-user-info { display: flex; flex-direction: column; }
        .comment-user-info span:first-child { font-weight: bold; color: var(--text-primary); }
        .comment-user-info span:last-child { font-size: 0.8rem; color: var(--text-muted); }
        
        .btn-delete {
            background: var(--accent); color: white; border: none;
            padding: 8px 16px; border-radius: 4px; font-weight: 600;
            font-size: 0.82rem; cursor: pointer; transition: 0.2s;
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
            <a href="comments.php" class="nav-link active">Yorumlar</a>
            <a href="collections.php" class="nav-link">Koleksiyonlar</a>
            <a href="videos.php" class="nav-link">Videolar</a>
        </nav>
        <div class="navbar-user">
            <a href="../index.php" class="btn-logout">Siteden Çık</a>
        </div>
    </div>
</header>

<div class="admin-page">
    <h1 class="admin-title">Yorumları Yönet</h1>

    <?php if ($csrfError): ?>
        <div class="alert alert-error"><?= $csrfError ?></div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>

    <div class="comments-table-wrapper">
        <?php if (empty($comments)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px;">Sistemde henüz yorum bulunmuyor.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>İçerik</th>
                        <th>Yorum</th>
                        <th>Puan</th>
                        <th>Tarih</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comm): ?>
                        <tr>
                            <td>
                                <div class="comment-user-info">
                                    <span>@<?= htmlspecialchars($comm['username']) ?></span>
                                    <span><?= htmlspecialchars($comm['email']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($comm['content_title'] ?? 'Bilinmeyen İçerik') ?></td>
                            <td><?= htmlspecialchars($comm['comment_text']) ?></td>
                            <td style="color: #FFD700; font-weight: 700;">★ <?= $comm['rating'] ?></td>
                            <td style="white-space: nowrap;"><?= date('d.m.Y H:i', strtotime($comm['created_at'])) ?></td>
                            <td>
                                <form action="comments.php" method="POST" onsubmit="return confirm('Bu yorumu silmek istediğinize emin misiniz?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="comment_id" value="<?= $comm['id'] ?>">
                                    <button type="submit" class="btn-delete">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
