<?php
/**
 * CineGX — Detay Sayfası
 * ADIM 4: Film/Dizi bilgilerini, oyuncuları ve yorumları gösterir.
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

$type = isset($_GET['type']) && $_GET['type'] === 'series' ? 'series' : 'movie';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header('Location: index.php');
    exit;
}

// ─── İçerik Bilgisini Çek ───
$table = $type === 'series' ? 'series' : 'movies';
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS category_name
    FROM `$table` t
    JOIN categories c ON t.category_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$content = $stmt->fetch();

if (!$content) {
    echo "<h1>İçerik bulunamadı.</h1>";
    exit;
}

// ─── Oyuncuları Çek ───
$stmtActors = $pdo->prepare("
    SELECT a.name, a.image_url, ca.character_name
    FROM content_actors ca
    JOIN actors a ON ca.actor_id = a.id
    WHERE ca.content_type = ? AND ca.content_id = ?
");
$stmtActors->execute([$type, $id]);
$actors = $stmtActors->fetchAll();

// ─── Yorumları Çek ───
$stmtComments = $pdo->prepare("
    SELECT c.*, u.username 
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.content_type = ? AND c.content_id = ?
    ORDER BY c.created_at DESC
");
$stmtComments->execute([$type, $id]);
$comments = $stmtComments->fetchAll();

// ─── Favori ve İzleme Listesi Durumu ───
$isFav = false;
$isWl = false;
if (isLoggedIn()) {
    $userId = currentUserId();
    $stmtFav = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND content_id = ? AND content_type = ?");
    $stmtFav->execute([$userId, $id, $type]);
    $isFav = (bool)$stmtFav->fetch();

    $stmtWl = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND content_id = ? AND content_type = ?");
    $stmtWl->execute([$userId, $id, $type]);
    $isWl = (bool)$stmtWl->fetch();
}

// Görseller
$backdropUrl = !empty($content['backdrop_url']) ? htmlspecialchars($content['backdrop_url']) : 'https://image.tmdb.org/t/p/w1280/9yBVqNruk6Ykrwc32qrK2TIE5xw.jpg';
$posterUrl = !empty($content['poster_url']) ? htmlspecialchars($content['poster_url']) : '';

// ─── Sezonlar (Sadece Diziler İçin) ───
$seasons = [];
if ($type === 'series') {
    $stmtSeasons = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
    $stmtSeasons->execute([$id]);
    $seasons = $stmtSeasons->fetchAll();
    
    foreach ($seasons as &$season) {
        $stmtEp = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
        $stmtEp->execute([$season['id']]);
        $season['episodes'] = $stmtEp->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($content['title']) ?> — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Details Page Specific CSS */
        .details-hero {
            position: relative;
            width: 100%;
            min-height: 85vh;
            display: flex;
            align-items: center;
            background-size: cover;
            background-position: center 20%;
            background-attachment: fixed;
            padding: 100px 4% 40px;
        }
        .details-gradient {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, rgba(20,20,20,1) 0%, rgba(20,20,20,0.8) 40%, rgba(20,20,20,0.3) 100%),
                        linear-gradient(0deg, rgba(20,20,20,1) 0%, rgba(20,20,20,0) 30%);
            z-index: 1;
        }
        .details-content-wrapper {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 50px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            align-items: flex-start;
        }
        .details-poster {
            flex-shrink: 0;
            width: 320px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .details-poster img {
            width: 100%;
            height: auto;
            display: block;
        }
        .details-info {
            flex-grow: 1;
            padding-top: 20px;
        }
        .details-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1.1;
            text-shadow: 0 4px 20px rgba(0,0,0,0.8);
        }
        .details-meta-bar {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 25px;
            font-size: 1.1rem;
            color: #aaa;
        }
        .badge-score {
            display: flex; align-items: center; gap: 5px;
            color: #fff; font-weight: bold; background: rgba(0,0,0,0.5);
            padding: 5px 12px; border-radius: 6px; border: 1px solid #FFD700;
        }
        .badge-age {
            border: 1px solid #aaa; padding: 3px 8px; border-radius: 4px;
        }
        .details-desc {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 30px;
            color: #ddd;
            max-width: 800px;
        }
        .btn-play-large {
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--accent); color: white;
            padding: 15px 35px; border-radius: 8px;
            font-size: 1.2rem; font-weight: 700;
            text-decoration: none; transition: 0.3s;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
        }
        .btn-play-large:hover {
            transform: scale(1.05);
            background: #f40612;
            color: white;
        }
        .btn-play-large.coming-soon {
            background: #555; pointer-events: none; box-shadow: none;
        }
        .btn-action-circle {
            width: 54px; height: 54px; border-radius: 50%;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.3s;
        }
        .btn-action-circle:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }
        .btn-action-circle.active {
            color: var(--accent);
            border-color: var(--accent);
            background: rgba(229, 9, 20, 0.1);
        }

        /* Sekmeler ve İçerik Altı */
        .page-section {
            padding: 40px 4%;
            max-width: 1400px;
            margin: 0 auto;
        }
        .section-title {
            font-size: 1.8rem; margin-bottom: 25px;
            border-left: 4px solid var(--accent); padding-left: 15px;
        }
        
        /* Cast Grid */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }
        .cast-card {
            background: rgba(255,255,255,0.05);
            border-radius: 8px; overflow: hidden;
            text-align: center; padding-bottom: 15px;
        }
        .cast-img {
            width: 100%; aspect-ratio: 1/1;
            object-fit: cover; background: #333;
        }
        .cast-name { font-weight: bold; margin: 10px 0 5px; font-size: 1rem; }
        .cast-char { color: #888; font-size: 0.85rem; }

        /* Comments */
        .comment-box {
            background: rgba(255,255,255,0.05);
            padding: 20px; border-radius: 10px; margin-bottom: 15px;
        }
        .comment-header {
            display: flex; justify-content: space-between; margin-bottom: 10px;
        }
        .comment-user { font-weight: bold; color: var(--accent); }
        .comment-rating { color: #FFD700; }
        
        /* Series Episodes */
        .season-block { margin-bottom: 30px; }
        .episode-row {
            display: flex; align-items: center; gap: 20px;
            padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: 0.3s;
        }
        .episode-row:hover { background: rgba(255,255,255,0.05); }
        .ep-num { font-size: 2rem; color: #555; font-weight: bold; width: 40px; }
        .ep-info { flex-grow: 1; }
        .ep-title { font-weight: bold; font-size: 1.1rem; margin-bottom: 5px; }
        .ep-desc { color: #aaa; font-size: 0.9rem; }
        .ep-duration { color: #777; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HERO SECTION -->
<div class="details-hero" style="background-image: url('<?= $backdropUrl ?>');">
    <div class="details-gradient"></div>
    <div class="details-content-wrapper">
        <div class="details-poster">
            <?php if ($posterUrl): ?>
                <img src="<?= $posterUrl ?>" alt="<?= htmlspecialchars($content['title']) ?>">
            <?php else: ?>
                <div style="width:100%; aspect-ratio: 2/3; background:#333; display:flex; align-items:center; justify-content:center; font-size:4rem; color:#666;">
                    <?= mb_substr($content['title'], 0, 1, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="details-info">
            <h1 class="details-title"><?= htmlspecialchars($content['title']) ?></h1>
            <div class="details-meta-bar">
                <span class="badge-score">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#FFD700"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?= number_format($content['imdb_score'], 1) ?>
                </span>
                <span><?= $content['release_year'] ?></span>
                <span class="badge-age"><?= htmlspecialchars($content['age_rating']) ?></span>
                <span><?= htmlspecialchars($content['category_name']) ?></span>
                <?php if ($type === 'series'): ?>
                    <span><?= $content['total_seasons'] ?> Sezon</span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($content['director'])): ?>
                <div class="details-director" style="margin-bottom: 15px; font-size: 1.1rem; color: #ddd;">
                    <span style="color: #888;">Yönetmen:</span> <?= htmlspecialchars($content['director']) ?>
                </div>
            <?php endif; ?>
            
            <p class="details-desc"><?= htmlspecialchars($content['description']) ?></p>
            
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <?php if ($content['status'] === 'coming_soon'): ?>
                    <span class="btn-play-large coming-soon">Çok Yakında</span>
                <?php else: ?>
                    <a href="stream.php?type=<?= $type ?>&id=<?= $id ?>" class="btn-play-large">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Şimdi İzle
                    </a>
                <?php endif; ?>
                
                <!-- Favori Butonu -->
                <button onclick="toggleFavorite()" id="btnFavorite" class="btn-action-circle <?= $isFav ? 'active' : '' ?>" title="<?= $isFav ? 'Favorilerimden Çıkar' : 'Favorilerime Ekle' ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="<?= $isFav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </button>

                <!-- İzleme Listesi Butonu -->
                <button onclick="toggleWatchlist()" id="btnWatchlist" class="btn-action-circle <?= $isWl ? 'active' : '' ?>" title="<?= $isWl ? 'İzleme Listemden Çıkar' : 'İzleme Listeme Ekle' ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if ($isWl): ?>
                            <polyline points="20 6 9 17 4 12"/>
                        <?php else: ?>
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        <?php endif; ?>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EPISODES (DİZİ İSE) -->
<?php if ($type === 'series' && !empty($seasons)): ?>
<div class="page-section">
    <h2 class="section-title">Bölümler</h2>
    <?php foreach ($seasons as $season): ?>
        <div class="season-block">
            <h3><?= htmlspecialchars($season['title']) ?></h3>
            <div class="episode-list">
                <?php foreach ($season['episodes'] as $ep): ?>
                    <div class="episode-row">
                        <div class="ep-num"><?= $ep['episode_number'] ?></div>
                        <div class="ep-info">
                            <div class="ep-title"><?= htmlspecialchars($ep['title']) ?></div>
                            <div class="ep-desc"><?= htmlspecialchars($ep['description']) ?></div>
                        </div>
                        <div class="ep-duration"><?= htmlspecialchars($ep['duration']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- OYUNCULAR -->
<?php if (!empty($actors)): ?>
<div class="page-section">
    <h2 class="section-title">Oyuncular</h2>
    <div class="cast-grid">
        <?php foreach ($actors as $actor): ?>
            <div class="cast-card">
                <?php if (!empty($actor['image_url'])): ?>
                    <img class="cast-img" src="<?= htmlspecialchars($actor['image_url']) ?>" alt="<?= htmlspecialchars($actor['name']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="cast-img" style="display:flex; align-items:center; justify-content:center; color:#666;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                <?php endif; ?>
                <div class="cast-name"><?= htmlspecialchars($actor['name']) ?></div>
                <div class="cast-char"><?= htmlspecialchars($actor['character_name']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- YORUMLAR -->
<div class="page-section">
    <h2 class="section-title">Yorumlar</h2>
    
    <!-- Yorum Ekleme Formu -->
    <div class="comment-form-container" style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.08);">
        <h3 style="margin-bottom: 15px; font-size: 1.1rem;">Yorumunu Paylaş</h3>
        <form id="commentForm" onsubmit="return submitComment(event)">
            <div style="display: flex; gap: 15px; margin-bottom: 15px; align-items: center; flex-wrap: wrap;">
                <label for="commentRating" style="font-weight: 600; color: var(--text-secondary);">Puanın:</label>
                <select id="commentRating" style="background: var(--bg-input); color: white; border: 1px solid var(--border); padding: 8px 14px; border-radius: 6px; font-size: 1rem; cursor: pointer;">
                    <option value="">Seç...</option>
                    <?php for ($i = 10; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>">★ <?= $i ?>/10</option>
                    <?php endfor; ?>
                </select>
            </div>
            <textarea id="commentText" placeholder="Bu içerik hakkında ne düşünüyorsun?" rows="3" style="width: 100%; background: var(--bg-input); color: white; border: 1px solid var(--border); padding: 14px; border-radius: 8px; font-family: inherit; font-size: 0.95rem; resize: vertical; min-height: 80px;"></textarea>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                <div id="commentStatus" style="font-size: 0.85rem; color: var(--text-muted);"></div>
                <button type="submit" style="background: var(--accent); color: white; border: none; padding: 10px 28px; border-radius: 6px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='var(--accent-hover)'" onmouseout="this.style.background='var(--accent)'">
                    Yorum Yap
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($comments)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 20px;">Henüz yorum yapılmamış. İlk yorumu sen yap!</p>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-box">
                    <div class="comment-header">
                        <span class="comment-user">@<?= htmlspecialchars($comment['username']) ?></span>
                        <span class="comment-rating">★ <?= $comment['rating'] ?>/10</span>
                    </div>
                    <div class="comment-text">
                        <?= htmlspecialchars($comment['comment_text']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function submitComment(e) {
    e.preventDefault();
    const rating = document.getElementById('commentRating').value;
    const text = document.getElementById('commentText').value.trim();
    const status = document.getElementById('commentStatus');

    if (!rating) { status.textContent = '⚠ Lütfen bir puan seçin.'; status.style.color = 'var(--error)'; return false; }
    if (!text || text.length < 5) { status.textContent = '⚠ Yorum en az 5 karakter olmalı.'; status.style.color = 'var(--error)'; return false; }

    status.textContent = 'Gönderiliyor...'; status.style.color = 'var(--text-muted)';

    const formData = new FormData();
    formData.append('action', 'post_comment');
    formData.append('content_type', '<?= $type ?>');
    formData.append('content_id', '<?= $id ?>');
    formData.append('rating', rating);
    formData.append('comment_text', text);

    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                status.textContent = '✓ ' + data.message;
                status.style.color = 'var(--success)';
                // Sayfayı yenile, yeni yorum görünsün
                setTimeout(() => location.reload(), 1000);
            } else {
                status.textContent = '✗ ' + data.message;
                status.style.color = 'var(--error)';
            }
        })
        .catch(() => {
            status.textContent = '✗ Bağlantı hatası.';
            status.style.color = 'var(--error)';
        });

    return false;
}

function toggleFavorite() {
    const btn = document.getElementById('btnFavorite');
    const formData = new FormData();
    formData.append('action', 'toggle_favorite');
    formData.append('content_type', '<?= $type ?>');
    formData.append('content_id', '<?= $id ?>');

    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.is_active) {
                    btn.classList.add('active');
                    btn.title = 'Favorilerimden Çıkar';
                    btn.querySelector('svg').setAttribute('fill', 'currentColor');
                } else {
                    btn.classList.remove('active');
                    btn.title = 'Favorilerime Ekle';
                    btn.querySelector('svg').setAttribute('fill', 'none');
                }
            } else {
                alert(data.message);
            }
        });
}

function toggleWatchlist() {
    const btn = document.getElementById('btnWatchlist');
    const formData = new FormData();
    formData.append('action', 'toggle_watchlist');
    formData.append('content_type', '<?= $type ?>');
    formData.append('content_id', '<?= $id ?>');

    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.is_active) {
                    btn.classList.add('active');
                    btn.title = 'İzleme Listemden Çıkar';
                    btn.querySelector('svg').innerHTML = '<polyline points="20 6 9 17 4 12"/>';
                } else {
                    btn.classList.remove('active');
                    btn.title = 'İzleme Listeme Ekle';
                    btn.querySelector('svg').innerHTML = '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>';
                }
            } else {
                alert(data.message);
            }
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
