<?php
/**
 * CineGX — Admin Dashboard
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

requireAdmin();

// İstatistikleri Çek
$movieCount = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$seriesCount = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$commentCount = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();

// Son Eklenen 5 Yorum
$recentComments = $pdo->query("
    SELECT c.*, u.username, COALESCE(m.title, s.title) AS content_title
    FROM comments c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN movies m ON c.content_type = 'movie' AND c.content_id = m.id
    LEFT JOIN series s ON c.content_type = 'series' AND c.content_id = s.id
    ORDER BY c.created_at DESC
    LIMIT 5
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli — CineGX</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-page { padding: 120px 4% 60px; max-width: 1200px; margin: 0 auto; }
        .admin-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 30px; border-left: 5px solid var(--accent); padding-left: 15px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 10px;
            background: rgba(229, 9, 20, 0.1);
            color: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .stat-info h3 { font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; }
        .stat-info p { font-size: 1.8rem; font-weight: 800; color: var(--text-primary); margin: 0; }

        .admin-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .admin-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 30px;
        }
        .card-header-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 20px; }
        
        .quick-actions { display: flex; flex-direction: column; gap: 12px; }
        .action-btn {
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(255,255,255,0.05); color: white;
            padding: 15px 20px; border-radius: 8px; text-decoration: none;
            font-weight: 600; transition: 0.2s; border: 1px solid transparent;
        }
        .action-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: var(--accent);
            transform: translateX(3px);
        }

        .recent-comments-list { display: flex; flex-direction: column; gap: 15px; }
        .admin-comment-card {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px; padding: 15px; border: 1px solid rgba(255,255,255,0.04);
        }
        .acc-header { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.85rem; }
        .acc-user { font-weight: bold; color: var(--accent); }
        .acc-content { color: var(--text-muted); }
        .acc-text { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.4; }

        @media (max-width: 900px) {
            .admin-sections { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="navbar scrolled">
    <div class="navbar-inner">
        <a href="../index.php" class="navbar-logo">Cine<span>GX</span> Admin</a>
        <nav class="navbar-nav">
            <a href="index.php" class="nav-link active">Panel</a>
            <a href="movies.php" class="nav-link">Filmler</a>
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
    <h1 class="admin-title">Yönetim Paneli</h1>

    <!-- İstatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🎬</div>
            <div class="stat-info">
                <h3>Toplam Film</h3>
                <p><?= $movieCount ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📺</div>
            <div class="stat-info">
                <h3>Toplam Dizi</h3>
                <p><?= $seriesCount ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <h3>Kullanıcılar</h3>
                <p><?= $userCount ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-info">
                <h3>Yorum Sayısı</h3>
                <p><?= $commentCount ?></p>
            </div>
        </div>
    </div>

    <!-- İki Sütunlu Yapı -->
    <div class="admin-sections">
        <!-- Sol Sütun: Son Yorumlar -->
        <div class="admin-card">
            <h2 class="card-header-title">Son Eklenen Yorumlar</h2>
            <?php if (empty($recentComments)): ?>
                <p style="color: var(--text-muted);">Henüz yorum yapılmamış.</p>
            <?php else: ?>
                <div class="recent-comments-list">
                    <?php foreach ($recentComments as $comm): ?>
                        <div class="admin-comment-card">
                            <div class="acc-header">
                                <span class="acc-user">@<?= htmlspecialchars($comm['username']) ?></span>
                                <span class="acc-content"><?= htmlspecialchars($comm['content_title'] ?? 'Bilinmeyen İçerik') ?> (★ <?= $comm['rating'] ?>)</span>
                            </div>
                            <div class="acc-text"><?= htmlspecialchars($comm['comment_text']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sağ Sütun: Hızlı İşlemler -->
        <div class="admin-card">
            <h2 class="card-header-title">Hızlı Menü</h2>
            <div class="quick-actions">
                <a href="movies.php" class="action-btn">
                    <span>Filmleri Yönet</span>
                    <span>→</span>
                </a>
                <a href="series.php" class="action-btn">
                    <span>Dizileri Yönet</span>
                    <span>→</span>
                </a>
                <a href="comments.php" class="action-btn">
                    <span>Yorumları Yönet</span>
                    <span>→</span>
                </a>
                <a href="collections.php" class="action-btn">
                    <span>Koleksiyonları Düzenle</span>
                    <span>→</span>
                </a>
                <a href="videos.php" class="action-btn" style="border-color:var(--accent);">
                    <span>Video Yöneticisi (Upload/Sil)</span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
