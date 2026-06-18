<?php
require 'c:/xampp/htdocs/final/includes/db.php';

echo "BAGLANTI BASARILI! PDO aktif.\n";

$stmt = $pdo->query('SELECT COUNT(*) as total FROM movies');
$r = $stmt->fetch();
echo "Film sayisi: " . $r['total'] . "\n";

$stmt2 = $pdo->query('SELECT COUNT(*) as total FROM series');
$r2 = $stmt2->fetch();
echo "Dizi sayisi: " . $r2['total'] . "\n";

echo "TOPLAM ICERIK: " . ($r['total'] + $r2['total']) . "\n";

$stmt3 = $pdo->query('SELECT title, slug, imdb_score FROM movies ORDER BY id');
echo "\n--- FILMLER ---\n";
while ($row = $stmt3->fetch()) {
    echo $row['title'] . " | slug: " . $row['slug'] . " | IMDb: " . $row['imdb_score'] . "\n";
}

$stmt4 = $pdo->query('SELECT title, slug, imdb_score FROM series ORDER BY id');
echo "\n--- DIZILER ---\n";
while ($row = $stmt4->fetch()) {
    echo $row['title'] . " | slug: " . $row['slug'] . " | IMDb: " . $row['imdb_score'] . "\n";
}
