<?php
/**
 * Poster URL'lerinin gerçekten çalışıp çalışmadığını kontrol et.
 * Kırık URL'leri tespit edip düzelt.
 */
require_once __DIR__ . '/includes/db.php';

echo "=== POSTER URL KONTROL ===\n\n";

$tables = ['movies', 'series'];
$broken = [];

foreach ($tables as $table) {
    $rows = $pdo->query("SELECT id, title, poster_url, backdrop_url FROM `$table` ORDER BY id")->fetchAll();
    foreach ($rows as $row) {
        echo "[$table] {$row['title']}:\n";
        
        // Poster kontrolü
        if (!empty($row['poster_url'])) {
            $headers = @get_headers($row['poster_url']);
            $status = $headers ? substr($headers[0], 9, 3) : 'ERR';
            $ok = ($status === '200');
            echo "  poster: $status " . ($ok ? '✓' : '✗ KIRIK') . "\n";
            if (!$ok) {
                $broken[] = ['table' => $table, 'id' => $row['id'], 'title' => $row['title'], 'field' => 'poster_url', 'url' => $row['poster_url']];
            }
        } else {
            echo "  poster: BOŞ\n";
            $broken[] = ['table' => $table, 'id' => $row['id'], 'title' => $row['title'], 'field' => 'poster_url', 'url' => ''];
        }
        
        // Backdrop kontrolü
        if (!empty($row['backdrop_url'])) {
            $headers = @get_headers($row['backdrop_url']);
            $status = $headers ? substr($headers[0], 9, 3) : 'ERR';
            $ok = ($status === '200');
            echo "  backdrop: $status " . ($ok ? '✓' : '✗ KIRIK') . "\n";
            if (!$ok) {
                $broken[] = ['table' => $table, 'id' => $row['id'], 'title' => $row['title'], 'field' => 'backdrop_url', 'url' => $row['backdrop_url']];
            }
        } else {
            echo "  backdrop: BOŞ\n";
            $broken[] = ['table' => $table, 'id' => $row['id'], 'title' => $row['title'], 'field' => 'backdrop_url', 'url' => ''];
        }
    }
}

echo "\n=== KIRIK URL ÖZETİ ===\n";
if (empty($broken)) {
    echo "Tüm URL'ler çalışıyor!\n";
} else {
    echo count($broken) . " kırık URL bulundu:\n";
    foreach ($broken as $b) {
        echo "  [{$b['table']}] {$b['title']} → {$b['field']}: {$b['url']}\n";
    }
}
