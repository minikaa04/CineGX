<?php
$urls = [
    'https://image.tmdb.org/t/p/original/8t38G9n0z94B46wqwRjA26f16A1.jpg',
    'https://image.tmdb.org/t/p/original/uTv7zTyUZ9yP2H1z35ce1qVp4x7.jpg',
    'https://image.tmdb.org/t/p/original/t5Z82Vz16jN7d1Pcd743m1qL12R.jpg',
    'https://image.tmdb.org/t/p/original/tIqB700F9f2Qz6Lq6nJkIioX5fC.jpg',
    'https://image.tmdb.org/t/p/original/56v2KjBlU4XaOv9rVYEQypROD7P.jpg',
    'https://image.tmdb.org/t/p/original/suopoADq0k8YZr4dQXcU6pToj6s.jpg',
    'https://image.tmdb.org/t/p/original/80A4iyh8dEInbYgO6N75T5k98Qo.jpg'
];
foreach($urls as $u) {
    $h = @get_headers($u);
    echo ($h ? substr($h[0], 9, 3) : 'ERR') . ' - ' . $u . PHP_EOL;
}
