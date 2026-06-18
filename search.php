<?php
/**
 * CineGX — Arama Sonuçları Sayfası
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

$movies = [];
$series = [];

if ($searchQuery !== '') {
    // Filmlerde ara
    $stmtMovies = $pdo->prepare("
        SELECT m.*, c.name AS category_name 
        FROM movies m 
        JOIN categories c ON m.category_id = c.id
        WHERE m.title LIKE :q1 OR m.description LIKE :q2
        ORDER BY m.title ASC
    ");
    $stmtMovies->execute([':q1' => "%{$searchQuery}%", ':q2' => "%{$searchQuery}%"]);
    $movies = $stmtMovies->fetchAll();

    // Dizilerde ara
    $stmtSeries = $pdo->prepare("
        SELECT s.*, c.name AS category_name 
        FROM series s 
        JOIN categories c ON s.category_id = c.id
        WHERE s.title LIKE :q1 OR s.description LIKE :q2
        ORDER BY s.title ASC
    ");
    $stmtSeries->execute([':q1' => "%{$searchQuery}%", ':q2' => "%{$searchQuery}%"]);
    $series = $stmtSeries->fetchAll();
}

$totalResults = count($movies) + count($series);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>"<?= htmlspecialchars($searchQuery) ?>" Arama Sonuçları — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header {
            padding: 120px 4% 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 40px;
        }
        .page-title {
            font-size: 2.2rem;
            margin: 0;
            font-weight: 800;
        }
        .search-stats {
            color: var(--text-muted);
            margin-top: 10px;
            font-size: 0.95rem;
        }
        .results-section {
            padding: 0 4% 40px;
        }
        .section-subtitle {
            font-size: 1.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            border-left: 4px solid var(--accent);
            padding-left: 12px;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        .no-results {
            padding: 80px 4%;
            text-align: center;
            color: var(--text-muted);
        }
        .no-results h2 {
            font-size: 1.8rem;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        .no-results p {
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.6;
        }
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
            }
            .page-title {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1 class="page-title">Arama Sonuçları</h1>
    <p class="search-stats">
        "<?= htmlspecialchars($searchQuery) ?>" için <?= $totalResults ?> sonuç bulundu.
    </p>
</div>

<div class="results-section">
    <?php if ($totalResults === 0): ?>
        <div class="no-results">
            <h2>Hiçbir Sonuç Bulunamadı</h2>
            <p>Aradığınız kelimelere uygun film veya dizi bulunamadı. Lütfen yazım kurallarını kontrol edin veya farklı anahtar kelimelerle tekrar aramayı deneyin.</p>
        </div>
    <?php else: ?>
        
        <!-- FİLMLER -->
        <?php if (!empty($movies)): ?>
            <div class="results-block">
                <h2 class="section-subtitle">Filmler</h2>
                <div class="grid-container">
                    <?php foreach ($movies as $movie): 
                        $posterUrl = !empty($movie['poster_url']) ? htmlspecialchars($movie['poster_url']) : '';
                    ?>
                        <div class="movie-card" data-type="movie" data-id="<?= $movie['id'] ?>" onmouseenter="startHoverPreview(this, '<?= htmlspecialchars($movie['slug']) ?>')" onmouseleave="stopHoverPreview(this)">
                            <a href="details.php?type=movie&id=<?= $movie['id'] ?>" class="card-link">
                                <div class="card-poster">
                                    <?php if ($posterUrl): ?>
                                        <img src="<?= $posterUrl ?>" alt="<?= htmlspecialchars($movie['title']) ?>" class="card-poster-img" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="card-poster-placeholder">
                                            <span class="poster-letter"><?= mb_substr($movie['title'], 0, 1, 'UTF-8') ?></span>
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
                                    <h3 class="card-title"><?= htmlspecialchars($movie['title']) ?></h3>
                                    <div class="card-meta">
                                        <span class="card-score">★ <?= number_format($movie['imdb_score'], 1) ?></span>
                                        <span class="card-year"><?= $movie['release_year'] ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- DİZİLER -->
        <?php if (!empty($series)): ?>
            <div class="results-block">
                <h2 class="section-subtitle">Diziler</h2>
                <div class="grid-container">
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
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- HOVER JS (Aynı mekanizma) -->
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
