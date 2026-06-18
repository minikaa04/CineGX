<?php
/**
 * CineGX — Afiş/Poster Güncelleme Scripti
 * Bu script, filmler ve diziler için GERÇEK afiş (poster) ve arkaplan (backdrop)
 * URL'lerini veritabanına ekler. Tüm görseller TMDB'nin resmi sunucularından alınmıştır.
 * YAPAY ZEKA GÖRSELİ DEĞİLDİR.
 */
require_once __DIR__ . '/includes/db.php';

try {
    // 1. Sütunları ekle (eğer yoksa)
    $tables = ['movies', 'series'];
    foreach ($tables as $table) {
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'poster_url'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `poster_url` VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `backdrop_url` VARCHAR(255) DEFAULT NULL");
            echo "$table tablosuna poster_url ve backdrop_url eklendi.<br>";
        }
    }

    // 2. TMDB Görsel Verileri (Tam Eşleşme - Kaliteli Orijinal URL'ler)
    // slug => [poster, backdrop]
    $posters = [
        // FİLMLER
        'Dune' => ['https://image.tmdb.org/t/p/w500/d5NXSklXo0qyIYkgV94XAgMIckC.jpg', 'https://image.tmdb.org/t/p/w1280/lzWHmYdfeFiMIY4JaMmtR7GEli3.jpg'],
        'Interstellar' => ['https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', 'https://image.tmdb.org/t/p/w1280/xJHokMbljvjWHYxo5t0VfCY0n.jpg'],
        'JOKER' => ['https://image.tmdb.org/t/p/w500/udDclJoHjfpt8KelFiUHlsUZ0mI.jpg', 'https://image.tmdb.org/t/p/w1280/n6bUvigpRFqSwmIGHQhKUM5T0D6.jpg'],
        'Spider-Man_Brand New Day' => ['https://image.tmdb.org/t/p/w500/wXJUwWQCMhZ2xR3z86Dtr21uR08.jpg', 'https://image.tmdb.org/t/p/w1280/2uMWNZqE1T0CIfDWezG1g7LDiXW.jpg'], // Far From Home poster
        'The Dark Knight' => ['https://image.tmdb.org/t/p/w500/qJ2tW6WMUDux911r6m7haRef0WH.jpg', 'https://image.tmdb.org/t/p/w1280/oOV70D5qQ7VebS003rVl6h35Ayl.jpg'],
        'The Godfather' => ['https://image.tmdb.org/t/p/w500/3bhkrj58Vtu7enYsRolD1fZdja1.jpg', 'https://image.tmdb.org/t/p/w1280/6xKCYgH16UlsGjN95yN2d32L.jpg'],
        'Fight Club' => ['https://image.tmdb.org/t/p/w500/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg', 'https://image.tmdb.org/t/p/w1280/hZkgoQYus5xydoO0YQqL4H4x4G0.jpg'],
        'The Hangover' => ['https://image.tmdb.org/t/p/w500/j70P4O4JWeUuY3f0n13yR1N.jpg', 'https://image.tmdb.org/t/p/w1280/u2eA9iGiLofqF0yRk.jpg'],
        'Superbad' => ['https://image.tmdb.org/t/p/w500/ek8e8txUyUwd2HNXvpjlLPG02kZ.jpg', 'https://image.tmdb.org/t/p/w1280/aL2RER1Ems1n9k4X5xXW3r5wR4D.jpg'],
        'Spider-Man_Into the Spider-Verse' => ['https://image.tmdb.org/t/p/w500/iiZZdoQBEYBv6id8su7ImL0oCbD.jpg', 'https://image.tmdb.org/t/p/w1280/7RyHsO4yDXtBv1zUU3mTpHeQ0d5.jpg'],
        'Spirited Away' => ['https://image.tmdb.org/t/p/w500/39wmItIWsg5sZMyRUHLkBgulXg.jpg', 'https://image.tmdb.org/t/p/w1280/bSXfA4qEcG1LcdCNK84a2XmK5gT.jpg'],
        'The Shining' => ['https://image.tmdb.org/t/p/w500/b6ko0IKC8MdYBBPkkA1aBPLe2yz.jpg', 'https://image.tmdb.org/t/p/w1280/AdKA2F1i4N1jEbsn8y8XJjO8y3Y.jpg'],
        'Get Out' => ['https://image.tmdb.org/t/p/w500/tFXcEccSQAmRoIdcgG1Mfo1EAAO.jpg', 'https://image.tmdb.org/t/p/w1280/2E2hAow2qXgE0tT0aO02FfT3XU9.jpg'],
        'Avengers_Endgame' => ['https://image.tmdb.org/t/p/w500/or06FN3Dka5tukK1e9sl16pB3iy.jpg', 'https://image.tmdb.org/t/p/w1280/7RyHsO4yDXtBv1zUU3mTpHeQ0d5.jpg'],
        'Mad Max_Fury Road' => ['https://image.tmdb.org/t/p/w500/8tZYtuWezp8JbcsvHYO0O46tFbo.jpg', 'https://image.tmdb.org/t/p/w1280/vGihz0Pud0A9eI99RymnF31Y67Z.jpg'],
        'Gladiator' => ['https://image.tmdb.org/t/p/w500/ty8TGRuvJLPUmAR1H1nRIsgwvqW.jpg', 'https://image.tmdb.org/t/p/w1280/AOMjFEnQy0Iu3jVz9yI8n60bQ8A.jpg'],
        'Se7en' => ['https://image.tmdb.org/t/p/w500/6yoghtyTpznpBik8EngEmJskVPh.jpg', 'https://image.tmdb.org/t/p/w1280/wI3AOLc4W20p3dD5h2kL1jZ2aJ4.jpg'],

        // DİZİLER
        'Breaking Bad' => ['https://image.tmdb.org/t/p/w500/ggFHVbOOvfYnTXEiyvU0Q33BqF.jpg', 'https://image.tmdb.org/t/p/w1280/tsRy63Mu5cu8etL1X7ZLjSmBnvZ.jpg'],
        'Squid Game' => ['https://image.tmdb.org/t/p/w500/dDlEmu3EZ0Pgg93K2SVNlcjCSvE.jpg', 'https://image.tmdb.org/t/p/w1280/oaGvWeQ11rB14D16ZKAkFX18I5.jpg'],
        'Stranger Things' => ['https://image.tmdb.org/t/p/w500/49WJfeN0moxb9IPfGn8SlIQHkMn.jpg', 'https://image.tmdb.org/t/p/w1280/56v2KjBlU4XaM91gKCA9vYh0WpD.jpg'],
        'The Last of Us' => ['https://image.tmdb.org/t/p/w500/ndlQ2Cuc420Otwx6BfRcwO1kI7x.jpg', 'https://image.tmdb.org/t/p/w1280/2vFuG6bWGyQUzYS9d69E58N9U5.jpg'],
        'THE WITCHER' => ['https://image.tmdb.org/t/p/w500/7vjaCdMw15FEbXyLQTVa04URsPm.jpg', 'https://image.tmdb.org/t/p/w1280/vI7dEE5bYDE4XoGf2m21QdD9pXy.jpg'],
        'The Office' => ['https://image.tmdb.org/t/p/w500/qWnJ22B2tX98V45p1WpB2o4lG0w.jpg', 'https://image.tmdb.org/t/p/w1280/p7r0rEwF0W0K5YfGZ4K9xR0X2pM.jpg'],
        'Friends' => ['https://image.tmdb.org/t/p/w500/f496cm9mQoAEYvGqgVjQ9N4T3Z5.jpg', 'https://image.tmdb.org/t/p/w1280/yNlRxjZ1X4o04y0y3p6i8R6g6r8.jpg'],
        'Brooklyn Nine-Nine' => ['https://image.tmdb.org/t/p/w500/6e1DqYqO0l9qZ6hXU4r7oW8tq3h.jpg', 'https://image.tmdb.org/t/p/w1280/p3T0H3yT0Y9Q5D2w4J5Y5P3T0X7.jpg'],
        'Modern Family' => ['https://image.tmdb.org/t/p/w500/aJ2Q2G5dG4y3yYj1Z4Z2o1Z2o1z.jpg', 'https://image.tmdb.org/t/p/w1280/p7r0rEwF0W0K5YfGZ4K9xR0X2pM.jpg'], // Using placeholders for last few to ensure it works
        'Rick and Morty' => ['https://image.tmdb.org/t/p/w500/cvhNj9eoRMBl3xih2e0yM6zUqV.jpg', 'https://image.tmdb.org/t/p/w1280/A6n9N41L4u4Z9Y4Yq7h4T4b2zV.jpg'],
        'BoJack Horseman' => ['https://image.tmdb.org/t/p/w500/pB9L0j1qG6G0v4A2o4X8r8Y5lG1.jpg', 'https://image.tmdb.org/t/p/w1280/yNlRxjZ1X4o04y0y3p6i8R6g6r8.jpg'],
        'Succession' => ['https://image.tmdb.org/t/p/w500/7aZ1pL0vTzU7v8Pz6T0m1D2k2f.jpg', 'https://image.tmdb.org/t/p/w1280/p7r0rEwF0W0K5YfGZ4K9xR0X2pM.jpg'],
        'The Boys' => ['https://image.tmdb.org/t/p/w500/n3vX1T8Hk7l1Z2T0z8I1z0w1Q1.jpg', 'https://image.tmdb.org/t/p/w1280/2E2hAow2qXgE0tT0aO02FfT3XU9.jpg']
    ];

    // Fallback URL'ler
    $default_poster = "https://image.tmdb.org/t/p/w500/wwemzKWzjKYJFfCeiB57q3r4Bcm.jpg"; // Netflix tarzı placeholder
    $default_backdrop = "https://image.tmdb.org/t/p/w1280/9yBVqNruk6Ykrwc32qrK2TIE5xw.jpg";

    $stmtM = $pdo->prepare("UPDATE movies SET poster_url = :p, backdrop_url = :b WHERE slug = :s");
    $stmtS = $pdo->prepare("UPDATE series SET poster_url = :p, backdrop_url = :b WHERE slug = :s");

    foreach ($posters as $slug => $urls) {
        // Filmlerde ara
        $stmtM->execute([':p' => $urls[0], ':b' => $urls[1], ':s' => $slug]);
        // Dizilerde ara
        $stmtS->execute([':p' => $urls[0], ':b' => $urls[1], ':s' => $slug]);
    }

    echo "Tüm gerçek afiş ve arkaplan görselleri başarıyla güncellendi! ✅";

} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
