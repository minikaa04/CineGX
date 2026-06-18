<?php
/**
 * CineGX — Ana Sayfa (Index)
 * ADIM 3: Netflix tarzı Hero Banner + Kategoriye Göre Slider + Trending
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Giriş kontrolü — giriş yapmamışsa login'e yönlendir
requireLogin();

// ─── Kategorileri çek ───
$stmtCat = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id");
$categories = $stmtCat->fetchAll();

// ─── Tüm filmleri çek ───
$stmtMovies = $pdo->query("
    SELECT m.*, c.name AS category_name, c.slug AS category_slug
    FROM movies m
    JOIN categories c ON m.category_id = c.id
    ORDER BY m.is_trending DESC, m.imdb_score DESC
");
$allMovies = $stmtMovies->fetchAll();

// ─── Tüm dizileri çek ───
$stmtSeries = $pdo->query("
    SELECT s.*, c.name AS category_name, c.slug AS category_slug
    FROM series s
    JOIN categories c ON s.category_id = c.id
    ORDER BY s.is_trending DESC, s.imdb_score DESC
");
$allSeries = $stmtSeries->fetchAll();

// ─── Trending içerikleri ayır (Hero Banner havuzu) ───
$trendingMovies = array_filter($allMovies, fn($m) => $m['is_trending']);
$trendingSeries = array_filter($allSeries, fn($s) => $s['is_trending']);
$trendingAll = array_merge(array_values($trendingMovies), array_values($trendingSeries));
shuffle($trendingAll);

// Hero için rastgele bir trending içerik seç
$heroContent = $trendingAll[0] ?? $allMovies[0] ?? null;
$heroType = isset($heroContent['total_seasons']) ? 'series' : 'movie';

// ─── Filmleri kategoriye göre grupla ───
$moviesByCategory = [];
foreach ($allMovies as $movie) {
    $moviesByCategory[$movie['category_name']][] = $movie;
}

// ─── Dizileri kategoriye göre grupla ───
$seriesByCategory = [];
foreach ($allSeries as $serie) {
    $seriesByCategory[$serie['category_name']][] = $serie;
}

// ─── Top 10 listesi (IMDb'ye göre) ───
$topMovies = array_slice($allMovies, 0, 10);
$topSeries = array_slice($allSeries, 0, 10);

/**
 * Kart Render Fonksiyonu (DRY)
 */
function renderCard($item, $type, $isTop10 = false, $rank = 0) {
    $posterUrl = !empty($item['poster_url']) ? htmlspecialchars($item['poster_url']) : null;
    $slug = htmlspecialchars($item['slug']);
    $title = htmlspecialchars($item['title']);
    $score = number_format($item['imdb_score'], 1);
    $year = $item['release_year'];
    $age = isset($item['age_rating']) ? $item['age_rating'] : '';
    $seasons = isset($item['total_seasons']) ? $item['total_seasons'] . ' Sezon' : null;
    $id = $item['id'];

    $top10Class = $isTop10 ? 'top10-card' : '';
    
    // Hover event'leri - JS fonksiyonlarını çağırır
    echo '<div class="movie-card '.$top10Class.'" data-type="'.$type.'" data-id="'.$id.'" onmouseenter="startHoverPreview(this, \''.$slug.'\')" onmouseleave="stopHoverPreview(this)">';
    echo '<a href="details.php?type='.$type.'&id='.$id.'" class="card-link">';
    
    if ($isTop10) {
        echo '<div class="top10-rank">'.($rank + 1).'</div>';
    }

    echo '<div class="card-poster">';
    if ($posterUrl) {
        // Gerçek poster varsa göster
        echo '<img src="'.$posterUrl.'" alt="'.$title.'" class="card-poster-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">';
    } else {
        // Yoksa placeholder
        $letter = mb_substr($item['title'], 0, 1, 'UTF-8');
        echo '<div class="card-poster-placeholder"><span class="poster-letter">'.$letter.'</span><span class="poster-title-small">'.$title.'</span></div>';
    }

    echo '<div class="card-overlay">';
    echo '<div class="card-play-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>';
    echo '</div>'; // overlay bitiş
    
    // Video Container for Hover Preview
    echo '<div class="hover-video-container"></div>';

    echo '</div>'; // poster bitiş

    echo '<div class="card-info">';
    echo '<h3 class="card-title">'.$title.'</h3>';
    echo '<div class="card-meta">';
    echo '<span class="card-score"><svg width="12" height="12" viewBox="0 0 24 24" fill="#FFD700"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> '.$score.'</span>';
    echo '<span class="card-year">'.$year.'</span>';
    if ($seasons) {
        echo '<span class="card-seasons">'.$seasons.'</span>';
    } else if ($age) {
        echo '<span class="card-age">'.$age.'</span>';
    }
    echo '</div>'; // meta bitiş
    echo '</div>'; // info bitiş

    echo '</a>';
    echo '</div>'; // card bitiş
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CineGX — Premium dijital sinema platformu. Binlerce film ve dizi tek bir yerde.">
    <title>CineGX — Premium Dijital Sinema</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="main-content" id="mainContent">

    <!-- ═══════════════ HERO BANNER ═══════════════ -->
    <?php if ($heroContent): ?>
    <?php $heroBg = !empty($heroContent['backdrop_url']) ? htmlspecialchars($heroContent['backdrop_url']) : ''; ?>
    <section class="hero-banner" id="heroBanner" data-slug="<?= htmlspecialchars($heroContent['slug']) ?>" onmouseenter="startHeroHover(this)" onmouseleave="stopHeroHover(this)">
        <div class="hero-backdrop" id="heroBackdrop" style="background-image: url('<?= $heroBg ?>'); background-size: cover; background-position: top center;"></div>
        <div class="hero-video-container" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; overflow: hidden; background: transparent;"></div>
        <div class="hero-gradient" style="position: absolute; inset: 0; z-index: 2; background: linear-gradient(to top, var(--bg-primary) 0%, transparent 50%), linear-gradient(to right, rgba(20,20,20,0.9) 0%, transparent 60%);"></div>
        <div class="hero-content" style="position: relative; z-index: 3;">
            <div class="hero-meta">
                <span class="hero-badge"><?= $heroType === 'series' ? 'DİZİ' : 'FİLM' ?></span>
                <span class="hero-rating">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#FFD700"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?= number_format($heroContent['imdb_score'], 1) ?>
                </span>
                <span class="hero-year"><?= $heroContent['release_year'] ?></span>
                <span class="hero-age-badge"><?= htmlspecialchars($heroContent['age_rating']) ?></span>
                <?php if ($heroType === 'series' && isset($heroContent['total_seasons'])): ?>
                    <span class="hero-seasons"><?= $heroContent['total_seasons'] ?> Sezon</span>
                <?php endif; ?>
            </div>
            <h1 class="hero-title" id="heroTitle"><?= htmlspecialchars($heroContent['title']) ?></h1>
            <p class="hero-description"><?= htmlspecialchars(mb_substr($heroContent['description'], 0, 200, 'UTF-8')) ?><?= mb_strlen($heroContent['description'], 'UTF-8') > 200 ? '...' : '' ?></p>
            <div class="hero-actions">
                <a href="details.php?type=<?= $heroType ?>&id=<?= $heroContent['id'] ?>" class="btn-hero-play" id="heroPlayBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Şimdi İzle
                </a>
                <a href="details.php?type=<?= $heroType ?>&id=<?= $heroContent['id'] ?>" class="btn-hero-info" id="heroInfoBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    Detaylar
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════ TRENDING ŞİMDİ ═══════════════ -->
    <?php if (!empty($trendingAll)): ?>
    <section class="content-row" id="sectionTrending">
        <div class="row-header">
            <h2 class="row-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Trend Olan İçerikler
            </h2>
        </div>
        <div class="slider-container" id="sliderTrending">
            <button class="slider-btn slider-btn-left" onclick="slideLeft('sliderTrending')" aria-label="Sola kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="slider-track">
                <?php foreach (array_slice($trendingAll, 0, 15) as $idx => $item):
                    $type = isset($item['total_seasons']) ? 'series' : 'movie';
                    renderCard($item, $type);
                endforeach; ?>
            </div>
            <button class="slider-btn slider-btn-right" onclick="slideRight('sliderTrending')" aria-label="Sağa kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════ TOP 10 FİLMLER ═══════════════ -->
    <?php if (!empty($topMovies)): ?>
    <section class="content-row top10-row" id="sectionTop10Movies">
        <div class="row-header">
            <h2 class="row-title">
                <span class="top10-badge">TOP 10</span>
                Bugün Türkiye'de En Çok İzlenen Filmler
            </h2>
        </div>
        <div class="slider-container" id="sliderTop10Movies">
            <button class="slider-btn slider-btn-left" onclick="slideLeft('sliderTop10Movies')" aria-label="Sola kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="slider-track">
                <?php foreach ($topMovies as $rank => $movie):
                    renderCard($movie, 'movie', true, $rank);
                endforeach; ?>
            </div>
            <button class="slider-btn slider-btn-right" onclick="slideRight('sliderTop10Movies')" aria-label="Sağa kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════ FİLMLER — KATEGORİYE GÖRE ═══════════════ -->
    <?php foreach ($moviesByCategory as $catName => $movies): ?>
    <section class="content-row" id="section-movie-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>">
        <div class="row-header">
            <h2 class="row-title"><?= htmlspecialchars($catName) ?> Filmleri</h2>
        </div>
        <div class="slider-container" id="slider-movie-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>">
            <button class="slider-btn slider-btn-left" onclick="slideLeft('slider-movie-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>')" aria-label="Sola kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="slider-track">
                <?php foreach ($movies as $movie):
                    renderCard($movie, 'movie');
                endforeach; ?>
            </div>
            <button class="slider-btn slider-btn-right" onclick="slideRight('slider-movie-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>')" aria-label="Sağa kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </section>
    <?php endforeach; ?>

    <!-- ═══════════════ TOP 10 DİZİLER ═══════════════ -->
    <?php if (!empty($topSeries)): ?>
    <section class="content-row top10-row" id="sectionTop10Series">
        <div class="row-header">
            <h2 class="row-title">
                <span class="top10-badge">TOP 10</span>
                Bugün Türkiye'de En Çok İzlenen Diziler
            </h2>
        </div>
        <div class="slider-container" id="sliderTop10Series">
            <button class="slider-btn slider-btn-left" onclick="slideLeft('sliderTop10Series')" aria-label="Sola kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="slider-track">
                <?php foreach ($topSeries as $rank => $serie):
                    renderCard($serie, 'series', true, $rank);
                endforeach; ?>
            </div>
            <button class="slider-btn slider-btn-right" onclick="slideRight('sliderTop10Series')" aria-label="Sağa kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════ DİZİLER — KATEGORİYE GÖRE ═══════════════ -->
    <?php foreach ($seriesByCategory as $catName => $series): ?>
    <section class="content-row" id="section-series-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>">
        <div class="row-header">
            <h2 class="row-title"><?= htmlspecialchars($catName) ?> Dizileri</h2>
        </div>
        <div class="slider-container" id="slider-series-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>">
            <button class="slider-btn slider-btn-left" onclick="slideLeft('slider-series-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>')" aria-label="Sola kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="slider-track">
                <?php foreach ($series as $serie):
                    renderCard($serie, 'series');
                endforeach; ?>
            </div>
            <button class="slider-btn slider-btn-right" onclick="slideRight('slider-series-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $catName))) ?>')" aria-label="Sağa kaydır">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </section>
    <?php endforeach; ?>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- ═══════════════ SLIDER VE HOVER JS ═══════════════ -->
<script>
    // ─── Slider kaydırma fonksiyonları ───
    function slideLeft(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const track = container.querySelector('.slider-track');
        const scrollAmount = track.clientWidth * 0.75;
        track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    }

    function slideRight(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const track = container.querySelector('.slider-track');
        const scrollAmount = track.clientWidth * 0.75;
        track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }

    // ─── Slider butonlarının görünürlük kontrolü ───
    document.querySelectorAll('.slider-container').forEach(container => {
        const track = container.querySelector('.slider-track');
        const leftBtn = container.querySelector('.slider-btn-left');
        const rightBtn = container.querySelector('.slider-btn-right');

        function updateButtons() {
            if (leftBtn) leftBtn.style.opacity = track.scrollLeft > 10 ? '1' : '0';
            if (rightBtn) rightBtn.style.opacity = (track.scrollLeft + track.clientWidth) < (track.scrollWidth - 10) ? '1' : '0';
        }

        track.addEventListener('scroll', updateButtons);
        window.addEventListener('resize', updateButtons);
        setTimeout(updateButtons, 100);
    });

    // ─── Sayfa yüklenme animasyonu ───
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.content-row').forEach((row, index) => {
            row.style.animationDelay = `${index * 0.08}s`;
            row.classList.add('animate-in');
        });
    });

    // ═══════════════ HOVER-TO-PLAY (YOUTUBE/NETFLIX STYLE) ═══════════════
    // Küçük kartlar için hover başlat
    function startHoverPreview(card, slug) {
        // Mobilde hover etkileşimi devre dışı
        if (window.innerWidth < 768) return;

        const videoContainer = card.querySelector('.hover-video-container');
        if (!videoContainer) return;

        // Zaten yüklenmiş bir video varsa tekrar oluşturma, sadece oynat
        const existingVideo = videoContainer.querySelector('video');
        if (existingVideo) {
            // Daha önce hata almışsa (data-error flag) tekrar deneme
            if (existingVideo.dataset.error === '1') return;
            existingVideo.style.display = 'block';
            existingVideo.play().catch(() => {});
            existingVideo.style.opacity = '1';
            return;
        }
        
        // Yeni video elementi oluştur
        const video = document.createElement('video');
        
        const img = card.querySelector('.hover-img');
        if(img) video.poster = img.src;

        video.autoplay = true;
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        video.classList.add('hover-video');

        const sourceWebm = document.createElement('source');
        sourceWebm.src = 'videos/' + slug + '.webm';
        sourceWebm.type = 'video/webm';
        video.appendChild(sourceWebm);

        const sourceMp4 = document.createElement('source');
        sourceMp4.src = 'videos/' + slug + '.mp4';
        sourceMp4.type = 'video/mp4';
        video.appendChild(sourceMp4);

        // Video yüklenip oynatılabilir olduğunda yumuşak geçişle göster
        video.addEventListener('canplay', function onCanPlay() {
            video.style.opacity = '1';
            video.removeEventListener('canplay', onCanPlay);
        });

        // ÖNEMLİ: Video dosyası bulunamazsa (404), elementi kaldır ki afiş bozulmasın
        video.addEventListener('error', function onError() {
            video.dataset.error = '1';
            video.style.opacity = '0';
            video.removeEventListener('error', onError);
            // Video elementini DOM'da bırak ama sakla — tekrar oluşturmayı engeller
        });

        videoContainer.appendChild(video);
    }

    // Küçük kartlar için hover bitir
    function stopHoverPreview(card) {
        const videoContainer = card.querySelector('.hover-video-container');
        if (!videoContainer) return;

        const video = videoContainer.querySelector('video');
        if (video && video.dataset.error !== '1') {
            video.style.opacity = '0'; // Videoyu gizle → afiş tekrar görünür
            setTimeout(() => {
                video.pause();
                video.currentTime = 0;
                video.style.display = 'none';
            }, 350);
        }
    }

    // ─── HERO BANNER İÇİN ÖZEL HOVER ───
    function startHeroHover(heroElement) {
        if (window.innerWidth < 768) return;
        const slug = heroElement.dataset.slug;
        const videoContainer = heroElement.querySelector('.hero-video-container');
        if (!videoContainer || !slug) return;

        const existingVideo = videoContainer.querySelector('video');
        if (existingVideo) {
            if (existingVideo.dataset.error === '1') return;
            existingVideo.style.display = 'block';
            existingVideo.play().catch(() => {});
            existingVideo.style.opacity = '1';
            return;
        }

        const video = document.createElement('video');
        
        // Find hero backdrop for poster
        const heroEl = document.querySelector('.hero');
        if(heroEl) {
            const bgImage = heroEl.style.backgroundImage;
            const urlMatch = bgImage.match(/url\(['"]?(.*?)['"]?\)/);
            if(urlMatch && urlMatch[1]) video.poster = urlMatch[1];
        }

        video.autoplay = true;
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        video.style.width = '100%';
        video.style.height = '100%';
        video.style.objectFit = 'cover';
        video.style.opacity = '0';
        video.style.transition = 'opacity 0.6s ease';

        const sourceWebm = document.createElement('source');
        sourceWebm.src = 'videos/' + slug + '.webm';
        sourceWebm.type = 'video/webm';
        video.appendChild(sourceWebm);

        const sourceMp4 = document.createElement('source');
        sourceMp4.src = 'videos/' + slug + '.mp4';
        sourceMp4.type = 'video/mp4';
        video.appendChild(sourceMp4);

        video.addEventListener('canplay', function onCanPlay() {
            video.style.opacity = '1';
            video.removeEventListener('canplay', onCanPlay);
        });

        video.addEventListener('error', function onError() {
            video.dataset.error = '1';
            video.style.opacity = '0';
            video.removeEventListener('error', onError);
        });

        videoContainer.appendChild(video);
    }

    function stopHeroHover(heroElement) {
        const videoContainer = heroElement.querySelector('.hero-video-container');
        if (!videoContainer) return;

        const video = videoContainer.querySelector('video');
        if (video && video.dataset.error !== '1') {
            video.style.opacity = '0';
            setTimeout(() => {
                video.pause();
                video.currentTime = 0;
                video.style.display = 'none';
            }, 500);
        }
    }
</script>

</body>
</html>
