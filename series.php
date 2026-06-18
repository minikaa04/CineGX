<?php
/**
 * CineGX — Diziler Sayfası
 * ADIM 4: Tüm dizilerin kategorilerine göre listelendiği sayfa.
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

// Kategorileri çek
$stmtCat = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmtCat->fetchAll();

// Kategori filtresi var mı?
$filterCat = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$sql = "SELECT s.*, c.name AS category_name 
        FROM series s 
        JOIN categories c ON s.category_id = c.id";
if ($filterCat > 0) {
    $sql .= " WHERE s.category_id = :cid";
}
$sql .= " ORDER BY s.title ASC";

$stmtSeries = $pdo->prepare($sql);
if ($filterCat > 0) {
    $stmtSeries->execute([':cid' => $filterCat]);
} else {
    $stmtSeries->execute();
}
$series = $stmtSeries->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diziler — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header {
            padding: 120px 4% 40px;
            display: flex; justify-content: space-between; align-items: flex-end;
            border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 40px;
        }
        .page-title { font-size: 2.5rem; margin: 0; }
        .filter-select {
            background: rgba(255,255,255,0.1); color: white;
            border: 1px solid rgba(255,255,255,0.3); padding: 10px 15px;
            border-radius: 6px; font-size: 1rem; cursor: pointer;
        }
        .filter-select option { background: #141414; color: white; }
        
        .grid-container {
            padding: 0 4% 60px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1 class="page-title">Diziler</h1>
    
    <form action="series.php" method="GET" id="filterForm">
        <select name="category" class="filter-select" onchange="document.getElementById('filterForm').submit();">
            <option value="0">Tüm Kategoriler</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filterCat === $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="grid-container">
    <?php if (empty($series)): ?>
        <p>Bu kategoride henüz dizi bulunmamaktadır.</p>
    <?php else: ?>
        <?php foreach ($series as $serie): 
            $posterUrl = !empty($serie['poster_url']) ? htmlspecialchars($serie['poster_url']) : '';
        ?>
            <div class="movie-card" data-type="series" data-id="<?= $serie['id'] ?>" onmouseenter="startHoverPreview(this, '<?= htmlspecialchars($serie['slug']) ?>')" onmouseleave="stopHoverPreview(this)">
                <a href="details.php?type=series&id=<?= $serie['id'] ?>" class="card-link">
                    <div class="card-poster">
                        <?php if ($posterUrl): ?>
                            <img src="<?= $posterUrl ?>" alt="<?= htmlspecialchars($serie['title']) ?>" class="card-poster-img" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div class="card-poster-placeholder">
                                <span class="poster-letter"><?= mb_substr($serie['title'], 0, 1, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-overlay">
                            <div class="card-play-icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                        </div>
                        <div class="hover-video-container"></div>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title"><?= htmlspecialchars($serie['title']) ?></h3>
                        <div class="card-meta">
                            <span class="card-score">★ <?= number_format($serie['imdb_score'], 1) ?></span>
                            <span class="card-year"><?= $serie['release_year'] ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- HOVER JS -->
<script>
    let hoverTimer = null;
    function startHoverPreview(card, slug) {
        if (card.dataset.hoverTimer) clearTimeout(parseInt(card.dataset.hoverTimer));
        if (window.innerWidth < 768) return;
        const videoContainer = card.querySelector('.hover-video-container');
        if (!videoContainer) return;

        const timer = setTimeout(() => {
            if (!videoContainer.querySelector('video')) {
                const video = document.createElement('video');
                const img = card.querySelector('.hover-img');
                if(img) video.poster = img.src;

                video.autoplay = true; video.muted = true; video.loop = true;
                video.classList.add('hover-video');

                const sourceWebm = document.createElement('source');
                sourceWebm.src = 'videos/' + encodeURIComponent(slug) + '.webm';
                sourceWebm.type = 'video/webm';
                video.appendChild(sourceWebm);

                const sourceMp4 = document.createElement('source');
                sourceMp4.src = 'videos/' + encodeURIComponent(slug) + '.mp4';
                sourceMp4.type = 'video/mp4';
                video.appendChild(sourceMp4);

                video.oncanplay = () => { video.style.opacity = '1'; };
                videoContainer.appendChild(video);
            } else {
                const existingVideo = videoContainer.querySelector('video');
                existingVideo.style.display = 'block';
                existingVideo.play().catch(e => console.log(e));
                existingVideo.style.opacity = '1';
            }
        }, 800);
        card.dataset.hoverTimer = timer;
    }
    function stopHoverPreview(card) {
        if (card.dataset.hoverTimer) {
            clearTimeout(parseInt(card.dataset.hoverTimer));
            card.removeAttribute('data-hoverTimer');
        }
        const videoContainer = card.querySelector('.hover-video-container');
        if (!videoContainer) return;
        const video = videoContainer.querySelector('video');
        if (video) {
            video.style.opacity = '0';
            setTimeout(() => { video.pause(); video.currentTime = 0; video.style.display = 'none'; }, 300);
        }
    }
</script>

</body>
</html>
