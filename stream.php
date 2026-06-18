<?php
/**
 * CineGX — Video Oynatıcı Sayfası
 * ADIM 5: Custom HTML5 Video Player ile güvenli izleme.
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

// Premium üyelik kontrolü (Abonelik sistemi)
if (!isPremium()) {
    header('Location: checkout.php');
    exit;
}

$type = isset($_GET['type']) && $_GET['type'] === 'series' ? 'series' : 'movie';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header('Location: index.php');
    exit;
}

// İçerik ve slug bilgisini çek
$table = $type === 'series' ? 'series' : 'movies';
$stmt = $pdo->prepare("SELECT id, title, slug, backdrop_url FROM `$table` WHERE id = ?");
$stmt->execute([$id]);
$content = $stmt->fetch();

if (!$content) {
    die("İçerik bulunamadı.");
}

$videoSlug = htmlspecialchars($content['slug']);
$posterUrl = !empty($content['backdrop_url']) ? htmlspecialchars($content['backdrop_url']) : 'https://image.tmdb.org/t/p/w1280/9yBVqNruk6Ykrwc32qrK2TIE5xw.jpg';
// Gerçekte dosya var mı kontrolü (Geliştirme ortamı için esnek bırakıyoruz, varsa oynatır)
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($content['title']) ?> — İzleniyor | CineGX</title>
    <style>
        /* Sadece Oynatıcı için Özel CSS */
        body, html {
            margin: 0; padding: 0; width: 100%; height: 100%;
            background: #000; overflow: hidden; font-family: 'Inter', sans-serif;
        }
        .player-container {
            position: relative; width: 100vw; height: 100vh;
            display: flex; justify-content: center; align-items: center;
        }
        video {
            width: 100%; height: 100%; object-fit: contain;
        }
        
        /* Netflix Tarzı Kontroller */
        .controls-overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0) 100%);
            padding: 40px 30px 20px;
            display: flex; flex-direction: column; gap: 15px;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .player-container:hover .controls-overlay,
        .player-container.active .controls-overlay {
            opacity: 1;
        }

        /* Top Bar (Geri Dön) */
        .top-bar {
            position: absolute; top: 0; left: 0; right: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0) 100%);
            padding: 30px; display: flex; align-items: center;
            opacity: 0; transition: opacity 0.3s ease; z-index: 10;
        }
        .player-container:hover .top-bar { opacity: 1; }
        
        .back-btn {
            color: white; text-decoration: none; font-size: 1.2rem;
            display: flex; align-items: center; gap: 10px; font-weight: bold;
        }
        .back-btn:hover { color: #E50914; }

        /* Progress Bar */
        .progress-container {
            width: 100%; height: 6px; background: rgba(255,255,255,0.3);
            cursor: pointer; border-radius: 3px; position: relative;
        }
        .progress-container:hover { height: 8px; }
        .progress-bar {
            height: 100%; background: #E50914; border-radius: 3px;
            width: 0%; position: relative;
        }
        .progress-bar::after {
            content: ''; position: absolute; right: -6px; top: -4px;
            width: 14px; height: 14px; background: #E50914; border-radius: 50%;
            transform: scale(0); transition: 0.2s;
        }
        .progress-container:hover .progress-bar::after { transform: scale(1); }

        /* Alt Butonlar */
        .controls-row {
            display: flex; justify-content: space-between; align-items: center;
        }
        .controls-left, .controls-right {
            display: flex; align-items: center; gap: 20px;
        }
        .control-btn {
            background: none; border: none; color: white; cursor: pointer;
            padding: 5px; display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .control-btn:hover { color: #E50914; transform: scale(1.1); }
        .title-display {
            color: white; font-weight: bold; font-size: 1.2rem; margin-left: 20px;
        }
        .time-display {
            color: white; font-size: 1rem; margin-left: 10px; font-variant-numeric: tabular-nums;
        }
        
        /* Volume Slider */
        .volume-container { display: flex; align-items: center; gap: 10px; }
        .volume-slider {
            width: 0; opacity: 0; transition: 0.3s;
            accent-color: #E50914;
        }
        .volume-container:hover .volume-slider { width: 80px; opacity: 1; }
    </style>
</head>
<body>

<div class="player-container" id="playerContainer">
    <div class="top-bar">
        <a href="details.php?type=<?= $type ?>&id=<?= $id ?>" class="back-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Detaylara Dön
        </a>
    </div>

    <!-- HTML5 Video Element (Controls gizli) -->
    <video id="videoPlayer" poster="<?= $posterUrl ?>">
        <source src="videos/<?= $videoSlug ?>.webm" type="video/webm">
        <source src="videos/<?= $videoSlug ?>.mp4" type="video/mp4">
        Tarayıcınız video etiketini desteklemiyor.
    </video>

    <!-- Custom Controls -->
    <div class="controls-overlay" id="controlsOverlay">
        <div class="progress-container" id="progressContainer">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        
        <div class="controls-row">
            <div class="controls-left">
                <button class="control-btn" id="playBtn" title="Oynat/Duraklat">
                    <!-- Play Icon -->
                    <svg id="iconPlay" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <!-- Pause Icon (Hidden initially) -->
                    <svg id="iconPause" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" style="display:none;"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                </button>
                
                <div class="volume-container">
                    <button class="control-btn" id="muteBtn" title="Sesi Kapat">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                    </button>
                    <input type="range" class="volume-slider" id="volumeSlider" min="0" max="1" step="0.1" value="1">
                </div>

                <div class="time-display">
                    <span id="currentTime">00:00</span> / <span id="duration">00:00</span>
                </div>
                
                <div class="title-display"><?= htmlspecialchars($content['title']) ?></div>
            </div>
            
            <div class="controls-right">
                <button class="control-btn" id="fullscreenBtn" title="Tam Ekran">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const video = document.getElementById('videoPlayer');
    const playerContainer = document.getElementById('playerContainer');
    const playBtn = document.getElementById('playBtn');
    const iconPlay = document.getElementById('iconPlay');
    const iconPause = document.getElementById('iconPause');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const volumeSlider = document.getElementById('volumeSlider');
    const muteBtn = document.getElementById('muteBtn');
    const currentTimeEl = document.getElementById('currentTime');
    const durationEl = document.getElementById('duration');
    const fullscreenBtn = document.getElementById('fullscreenBtn');

    // Toggle Play/Pause
    function togglePlay() {
        if (video.paused) {
            video.play();
            iconPlay.style.display = 'none';
            iconPause.style.display = 'block';
        } else {
            video.pause();
            iconPlay.style.display = 'block';
            iconPause.style.display = 'none';
        }
    }

    playBtn.addEventListener('click', togglePlay);
    video.addEventListener('click', togglePlay);

    // Format Time (MM:SS)
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    // Update Progress Bar & Time
    video.addEventListener('timeupdate', () => {
        const percent = (video.currentTime / video.duration) * 100;
        progressBar.style.width = `${percent}%`;
        currentTimeEl.textContent = formatTime(video.currentTime);
        if(!isNaN(video.duration)) {
            durationEl.textContent = formatTime(video.duration);
        }
    });

    // Set duration when metadata is loaded
    video.addEventListener('loadedmetadata', () => {
        durationEl.textContent = formatTime(video.duration);
    });

    // Click to seek
    progressContainer.addEventListener('click', (e) => {
        const rect = progressContainer.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        video.currentTime = pos * video.duration;
    });

    // Volume Control
    volumeSlider.addEventListener('input', (e) => {
        video.volume = e.target.value;
        video.muted = e.target.value === '0';
    });

    muteBtn.addEventListener('click', () => {
        video.muted = !video.muted;
        volumeSlider.value = video.muted ? 0 : video.volume;
    });

    // Fullscreen Toggle
    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            if (playerContainer.requestFullscreen) {
                playerContainer.requestFullscreen();
            } else if (playerContainer.webkitRequestFullscreen) { /* Safari */
                playerContainer.webkitRequestFullscreen();
            } else if (playerContainer.msRequestFullscreen) { /* IE11 */
                playerContainer.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) { /* Safari */
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) { /* IE11 */
                document.msExitFullscreen();
            }
        }
    });

    // Otomatik oynatmaya çalış (Tarayıcı politikalarına takılabilir, o yüzden sessiz başlatmıyoruz ama deneriz)
    video.play().catch(() => {
        console.log("Autoplay blocked by browser policy. User interaction required.");
    });
</script>

</body>
</html>
