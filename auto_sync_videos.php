<?php
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';

$TMDB_API_KEY = '2dca580c2a14b55200e784d157207b4d';
$videoDir = __DIR__ . '/videos/';

echo "=== OTOMATİK İÇERİK SENKRONİZASYONU BAŞLADI ===\n\n";

function fetchFromTMDB($url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 12,
            'header' => "Accept: application/json\r\n"
        ]
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response ? json_decode($response, true) : null;
}

function getTMDBGenreCategoryId($genre_ids) {
    if (empty($genre_ids)) return 3; // Dram
    foreach ($genre_ids as $id) {
        if ($id == 16) return 8; // Animasyon
        if ($id == 28 || $id == 12) return 1; // Aksiyon
        if ($id == 878) return 2; // Bilim Kurgu
        if ($id == 27) return 9; // Korku
        if ($id == 80) return 6; // Suç
        if ($id == 35) return 5; // Komedi
        if ($id == 53 || $id == 9648) return 4; // Gerilim
        if ($id == 14) return 7; // Fantastik
        if ($id == 18) return 3; // Dram
    }
    return 3; 
}

// 1. Videos klasöründeki dosyaları al
$files = scandir($videoDir);
$validSlugs = [];

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext !== 'mp4') continue;

    $slug = pathinfo($file, PATHINFO_FILENAME);
    $validSlugs[] = $slug;
    
    // DB'de var mı kontrol et
    $stmtM = $pdo->prepare("SELECT id FROM movies WHERE slug = ?");
    $stmtM->execute([$slug]);
    $inMovies = $stmtM->fetch();

    $stmtS = $pdo->prepare("SELECT id FROM series WHERE slug = ?");
    $stmtS->execute([$slug]);
    $inSeries = $stmtS->fetch();

    if ($inMovies || $inSeries) {
        echo "[ATLANDI] $slug zaten veritabanında mevcut.\n";
        continue;
    }

    echo "----------------------------------------\n";
    echo "[YENİ] $slug için TMDB'de aranıyor...\n";

    // İsmi temizle (örneğin "Spider-Man_ Brand New Day" -> "Spider-Man Brand New Day")
    $query = str_replace(['_', '-'], ' ', $slug);
    $query = trim(preg_replace('/\s+/', ' ', $query));

    // TMDB'de Multi-Search yap
    $searchUrl = "https://api.themoviedb.org/3/search/multi?api_key={$TMDB_API_KEY}&query=" . urlencode($query) . "&language=tr-TR";
    $searchData = fetchFromTMDB($searchUrl);

    if (empty($searchData['results'])) {
        $searchUrl = "https://api.themoviedb.org/3/search/multi?api_key={$TMDB_API_KEY}&query=" . urlencode($query) . "&language=en-US";
        $searchData = fetchFromTMDB($searchUrl);
    }

    // Filtrele: Sadece movie veya tv sonuçları
    $bestMatch = null;
    if (!empty($searchData['results'])) {
        foreach ($searchData['results'] as $res) {
            if ($res['media_type'] === 'movie' || $res['media_type'] === 'tv') {
                $bestMatch = $res;
                break;
            }
        }
    }

    if (!$bestMatch) {
        echo "⚠ TMDB'de uygun sonuç bulunamadı: {$query}. Manuel eklenecek.\n";
        // Manuel (Dummy) ekleme: Movie olarak
        $stmt = $pdo->prepare("
            INSERT INTO movies (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, poster_url, backdrop_url)
            VALUES (?, ?, ?, ?, ?, ?, 'released', ?, 1, ?, ?)
        ");
        $stmt->execute([$slug, $slug, 'Açıklama bulunamadı.', 7.0, 2024, '13+', 3, '', '']);
        continue;
    }

    $type = ($bestMatch['media_type'] === 'tv') ? 'series' : 'movie';
    $tmdb_id = $bestMatch['id'];
    $endpoint = ($type === 'series') ? 'tv' : 'movie';

    // Detayları Çek
    $detailsUrl = "https://api.themoviedb.org/3/{$endpoint}/{$tmdb_id}?api_key={$TMDB_API_KEY}&language=tr-TR";
    $details = fetchFromTMDB($detailsUrl);
    if (!$details || empty($details['overview'])) {
        $detailsUrl = "https://api.themoviedb.org/3/{$endpoint}/{$tmdb_id}?api_key={$TMDB_API_KEY}&language=en-US";
        $details = fetchFromTMDB($detailsUrl);
    }
    if (!$details) $details = $bestMatch;

    $title = $details['title'] ?? $details['name'] ?? $query;
    $description = $details['overview'] ?? 'Açıklama bulunamadı.';
    $imdb_score = !empty($details['vote_average']) ? $details['vote_average'] : 7.0;
    
    $date_str = $details['release_date'] ?? $details['first_air_date'] ?? '2024-01-01';
    $release_year = (int)date('Y', strtotime($date_str));
    if ($release_year < 1900) $release_year = 2024;
    
    $poster_url = !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] : '';
    $backdrop_url = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] : '';
    
    $genre_ids = [];
    if (!empty($details['genres'])) {
        $genre_ids = array_map(fn($g) => $g['id'], $details['genres']);
    } elseif (!empty($bestMatch['genre_ids'])) {
        $genre_ids = $bestMatch['genre_ids'];
    }
    $category_id = getTMDBGenreCategoryId($genre_ids);
    
    $age_rating = '13+';
    if (in_array(27, $genre_ids) || in_array(80, $genre_ids)) $age_rating = '18+';
    elseif (in_array(16, $genre_ids)) $age_rating = 'G';

    // DB'ye Ekle
    if ($type === 'series') {
        $total_seasons = $details['number_of_seasons'] ?? 1;
        $stmt = $pdo->prepare("
            INSERT INTO series (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, total_seasons, poster_url, backdrop_url)
            VALUES (?, ?, ?, ?, ?, ?, 'ongoing', ?, 1, ?, ?, ?)
        ");
        $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $category_id, $total_seasons, $poster_url, $backdrop_url]);
        $content_id = $pdo->lastInsertId();
        
        // Sezon 1 ve Dummy Bölüm Ekle
        $insSeason = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title) VALUES (?, 1, '1. Sezon')");
        $insSeason->execute([$content_id]);
        $season_id = $pdo->lastInsertId();
        
        $insEpisode = $pdo->prepare("
            INSERT INTO episodes (season_id, episode_number, title, duration, description)
            VALUES (?, 1, '1. Bölüm', '45m', ?)
        ");
        $insEpisode->execute([$season_id, $description]);
        
        echo "[BAŞARILI] $title (Dizi) olarak eklendi.\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO movies (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, poster_url, backdrop_url)
            VALUES (?, ?, ?, ?, ?, ?, 'released', ?, 1, ?, ?)
        ");
        $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $category_id, $poster_url, $backdrop_url]);
        echo "[BAŞARILI] $title (Film) olarak eklendi.\n";
    }
    
    // TMDB API limitine takılmamak için bekle
    usleep(200000); 
}

// 2. Klasörde olmayanları DB'den sil (Opsiyonel temizlik)
$validSlugsStr = implode(',', array_map(fn($s) => $pdo->quote($s), $validSlugs));
if (!empty($validSlugs)) {
    $delMovies = $pdo->query("DELETE FROM movies WHERE slug NOT IN ($validSlugsStr)");
    $delSeries = $pdo->query("DELETE FROM series WHERE slug NOT IN ($validSlugsStr)");
    echo "\nDB'de olup klasörde olmayan " . $delMovies->rowCount() . " film ve " . $delSeries->rowCount() . " dizi silindi.\n";
}

echo "\n=== SENKRONİZASYON TAMAMLANDI ===\n";
