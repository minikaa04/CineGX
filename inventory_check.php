<?php
require_once __DIR__ . '/includes/db.php';

$videoDir = __DIR__ . '/videos/';
$existingVideos = [];
if (is_dir($videoDir)) {
    $files = scandir($videoDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'mp4') {
            $existingVideos[strtolower(pathinfo($file, PATHINFO_FILENAME))] = $file;
        }
    }
}

echo "=== GÜNCEL VİDEO ENVANTERİ VE EŞLEŞMEYENLER ===\n\n";

// Filmler
echo "--- FİLMLER ---\n";
$stmt = $pdo->query('SELECT title, slug FROM movies ORDER BY title');
$missingMovies = [];
$matchedMovies = [];
while ($row = $stmt->fetch()) {
    $slugLower = strtolower($row['slug']);
    if (isset($existingVideos[$slugLower])) {
        $matchedMovies[] = $row['title'] . " (Dosya: " . $existingVideos[$slugLower] . ")";
    } else {
        $missingMovies[] = [
            'title' => $row['title'],
            'expected_file' => $row['slug'] . '.mp4'
        ];
    }
}

if (empty($missingMovies)) {
    echo "Tüm filmlerin videosu mevcut!\n";
} else {
    foreach ($missingMovies as $m) {
        echo "❌ EKSİK: {$m['title']} -> (Beklenen dosya: videos/{$m['expected_file']})\n";
    }
}

// Diziler
echo "\n--- DİZİLER ---\n";
$stmt2 = $pdo->query('SELECT title, slug FROM series ORDER BY title');
$missingSeries = [];
$matchedSeries = [];
while ($row = $stmt2->fetch()) {
    $slugLower = strtolower($row['slug']);
    if (isset($existingVideos[$slugLower])) {
        $matchedSeries[] = $row['title'] . " (Dosya: " . $existingVideos[$slugLower] . ")";
    } else {
        $missingSeries[] = [
            'title' => $row['title'],
            'expected_file' => $row['slug'] . '.mp4'
        ];
    }
}

if (empty($missingSeries)) {
    echo "Tüm dizilerin videosu mevcut!\n";
} else {
    foreach ($missingSeries as $s) {
        echo "❌ EKSİK: {$s['title']} -> (Beklenen dosya: videos/{$s['expected_file']})\n";
    }
}

echo "\n=== ÖZET ===\n";
echo "Eşleşen Film Sayısı: " . count($matchedMovies) . "\n";
echo "Eksik Film Sayısı: " . count($missingMovies) . "\n";
echo "Eşleşen Dizi Sayısı: " . count($matchedSeries) . "\n";
echo "Eksik Dizi Sayısı: " . count($missingSeries) . "\n";
