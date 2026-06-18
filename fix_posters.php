<?php
/**
 * CineGX — Kırık Poster URL'lerini Düzelt
 * TMDB API'den gerçek ve çalışan poster/backdrop URL'lerini çeker.
 * 
 * TMDB API Key gereklidir. Ücretsiz kayıt: https://www.themoviedb.org/settings/api
 */
require_once __DIR__ . '/includes/db.php';

$TMDB_API_KEY = '2dca580c2a14b55200e784d157207b4d'; // Ücretsiz TMDB API key

/**
 * TMDB'den film/dizi bilgisi çeker
 */
function searchTMDB($title, $type = 'movie', $apiKey) {
    $cleanTitle = preg_replace('/[_]/', ' ', $title);
    $cleanTitle = preg_replace('/\s+/', ' ', trim($cleanTitle));
    
    $endpoint = ($type === 'series') ? 'tv' : 'movie';
    $url = "https://api.themoviedb.org/3/search/{$endpoint}?api_key={$apiKey}&query=" . urlencode($cleanTitle) . "&language=tr-TR";
    
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, false, $ctx);
    
    if ($response === false) {
        echo "  ⚠ API isteği başarısız: $cleanTitle\n";
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data['results'])) {
        // İngilizce tekrar dene
        $url = "https://api.themoviedb.org/3/search/{$endpoint}?api_key={$apiKey}&query=" . urlencode($cleanTitle) . "&language=en-US";
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) return null;
        $data = json_decode($response, true);
    }
    
    if (!empty($data['results'])) {
        $result = $data['results'][0];
        $poster = !empty($result['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $result['poster_path'] : null;
        $backdrop = !empty($result['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $result['backdrop_path'] : null;
        return ['poster' => $poster, 'backdrop' => $backdrop];
    }
    
    return null;
}

echo "=== TMDB'den Gerçek Poster/Backdrop URL'lerini Çekme ===\n\n";

$tables = [
    'movies' => 'movie',
    'series' => 'series'
];

$updated = 0;
$failed = 0;

foreach ($tables as $table => $type) {
    $rows = $pdo->query("SELECT id, title, poster_url, backdrop_url FROM `$table` ORDER BY id")->fetchAll();
    
    foreach ($rows as $row) {
        echo "[{$table}] {$row['title']}... ";
        
        // Mevcut URL'lerin çalışıp çalışmadığını kontrol et
        $posterOk = false;
        $backdropOk = false;
        
        if (!empty($row['poster_url'])) {
            $headers = @get_headers($row['poster_url']);
            $posterOk = ($headers && strpos($headers[0], '200') !== false);
        }
        
        if (!empty($row['backdrop_url'])) {
            $headers = @get_headers($row['backdrop_url']);
            $backdropOk = ($headers && strpos($headers[0], '200') !== false);
        }
        
        // Eğer her ikisi de çalışıyorsa atla
        if ($posterOk && $backdropOk) {
            echo "✓ Her iki URL de çalışıyor, atlanıyor.\n";
            continue;
        }
        
        // TMDB'den yenilerini çek
        $result = searchTMDB($row['title'], $type, $TMDB_API_KEY);
        
        if ($result) {
            $newPoster = $posterOk ? $row['poster_url'] : ($result['poster'] ?? $row['poster_url']);
            $newBackdrop = $backdropOk ? $row['backdrop_url'] : ($result['backdrop'] ?? $row['backdrop_url']);
            
            $stmt = $pdo->prepare("UPDATE `$table` SET poster_url = ?, backdrop_url = ? WHERE id = ?");
            $stmt->execute([$newPoster, $newBackdrop, $row['id']]);
            
            echo "✓ Güncellendi";
            if (!$posterOk) echo " [poster]";
            if (!$backdropOk) echo " [backdrop]";
            echo "\n";
            $updated++;
        } else {
            echo "✗ TMDB'de bulunamadı!\n";
            $failed++;
        }
        
        // Rate limiting - TMDB sınırları aşmamak için
        usleep(300000); // 300ms
    }
}

echo "\n=== SONUÇ ===\n";
echo "Güncellenen: $updated\n";
echo "Başarısız: $failed\n";
echo "Tamamlandı!\n";
