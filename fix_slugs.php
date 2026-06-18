<?php
/**
 * Script to fix DB slugs to exactly match the video filenames.
 */
require_once __DIR__ . '/includes/db.php';

$dir = __DIR__ . '/videos';
$files = scandir($dir);
$videoFiles = [];
foreach ($files as $f) {
    if (pathinfo($f, PATHINFO_EXTENSION) === 'mp4') {
        $videoFiles[] = $f;
    }
}

// Function to find best match
function findBestMatch($slug, $files) {
    $slugClean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $slug));
    foreach ($files as $f) {
        $fName = pathinfo($f, PATHINFO_FILENAME);
        $fClean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fName));
        if (strpos($fClean, $slugClean) !== false || strpos($slugClean, $fClean) !== false) {
            return $fName; // return the exact filename without extension
        }
    }
    return $slug; // fallback
}

$tables = ['movies', 'series'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT id, slug FROM `$table`");
    while ($row = $stmt->fetch()) {
        $bestMatch = findBestMatch($row['slug'], $videoFiles);
        if ($bestMatch !== $row['slug']) {
            echo "Updating $table: {$row['slug']} -> $bestMatch\n";
            $update = $pdo->prepare("UPDATE `$table` SET slug = ? WHERE id = ?");
            $update->execute([$bestMatch, $row['id']]);
        }
    }
}
echo "Done fixing slugs.\n";
