<?php
$TMDB_API_KEY = '2dca580c2a14b55200e784d157207b4d';
$queries = [
    'marvel' => 'Avengers',
    'rickmorty' => 'Rick and Morty',
    'naruto' => 'Naruto',
    'cyberpunk' => 'Cyberpunk: Edgerunners',
    'aot' => 'Attack on Titan'
];

foreach ($queries as $key => $q) {
    $url = "https://api.themoviedb.org/3/search/multi?api_key={$TMDB_API_KEY}&query=" . urlencode($q);
    $res = @file_get_contents($url);
    if ($res) {
        $data = json_decode($res, true);
        if (!empty($data['results'][0]['backdrop_path'])) {
            echo "{$key} -> https://image.tmdb.org/t/p/original" . $data['results'][0]['backdrop_path'] . "\n";
        } else {
            echo "{$key} -> YOK\n";
        }
    }
}
