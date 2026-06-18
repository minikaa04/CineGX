<?php
/**
 * CineGX — Video ve İçerik Senkronizasyon Scripti
 * 
 * 1. "videos/" klasöründeki gerçek .mp4 dosyalarını tarar.
 * 2. Eşleşen .mp4 dosyası olmayan tüm "filler" film/dizileri temizler.
 * 3. Eksik olan ve videos/ içinde mp4'ü olan tüm içerikleri TMDB API'den çekerek veritabanına ekler.
 * 4. Tüm içeriklerin Yönetmen ismini günceller.
 * 5. Tüm içeriklerin oyuncu kadrosunu gerçek fotoğraflarıyla (TMDB) kapsamlı şekilde günceller.
 * 6. Diziler için sezon ve bölüm bilgilerini TMDB'den çekip günceller.
 */

header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';

$TMDB_API_KEY = '2dca580c2a14b55200e784d157207b4d'; // TMDB API Key

$video_files = [
    'Arcane' => ['type' => 'series', 'query' => 'Arcane', 'slug' => 'Arcane'],
    'Attack on Titan' => ['type' => 'series', 'query' => 'Attack on Titan', 'slug' => 'Attack on Titan'],
    'Barbie' => ['type' => 'movie', 'query' => 'Barbie', 'slug' => 'Barbie'],
    'Breaking Bad' => ['type' => 'series', 'query' => 'Breaking Bad', 'slug' => 'Breaking Bad'],
    'Deadpool' => ['type' => 'movie', 'query' => 'Deadpool', 'slug' => 'Deadpool'],
    'Dune' => ['type' => 'movie', 'query' => 'Dune', 'slug' => 'Dune'],
    'FORREST GUMP' => ['type' => 'movie', 'query' => 'Forrest Gump', 'slug' => 'FORREST GUMP'],
    'Friends' => ['type' => 'series', 'query' => 'Friends', 'slug' => 'Friends'],
    'IT' => ['type' => 'movie', 'query' => 'It', 'slug' => 'IT'],
    'Interstellar' => ['type' => 'movie', 'query' => 'Interstellar', 'slug' => 'Interstellar'],
    'JOKER' => ['type' => 'movie', 'query' => 'Joker', 'slug' => 'JOKER'],
    'John Wick Official' => ['type' => 'movie', 'query' => 'John Wick', 'slug' => 'John Wick Official'],
    'Oppenheimer r' => ['type' => 'movie', 'query' => 'Oppenheimer', 'slug' => 'Oppenheimer r'],
    'PARASITE' => ['type' => 'movie', 'query' => 'Parasite', 'slug' => 'PARASITE'],
    'Peaky Blinders_ The Immortal Man' => ['type' => 'series', 'query' => 'Peaky Blinders', 'slug' => 'Peaky Blinders_ The Immortal Man'],
    'Rick and Morty' => ['type' => 'series', 'query' => 'Rick and Morty', 'slug' => 'Rick and Morty'],
    'SPIDER-MAN_ INTO THE SPIDER-VERSE -' => ['type' => 'movie', 'query' => 'Spider-Man: Into the Spider-Verse', 'slug' => 'SPIDER-MAN_ INTO THE SPIDER-VERSE -'],
    'Shrek (2001)' => ['type' => 'movie', 'query' => 'Shrek', 'slug' => 'Shrek (2001)'],
    'Spider-Man_ Brand New Day' => ['type' => 'movie', 'query' => 'Spider-Man', 'slug' => 'Spider-Man_ Brand New Day'],
    'Squid Game' => ['type' => 'series', 'query' => 'Squid Game', 'slug' => 'Squid Game'],
    'Stranger Things' => ['type' => 'series', 'query' => 'Stranger Things', 'slug' => 'Stranger Things'],
    'Succession_' => ['type' => 'series', 'query' => 'Succession', 'slug' => 'Succession_'],
    'THE WITCHER' => ['type' => 'series', 'query' => 'The Witcher', 'slug' => 'THE WITCHER'],
    'The Dark Knight' => ['type' => 'movie', 'query' => 'The Dark Knight', 'slug' => 'The Dark Knight'],
    'The Last of Us' => ['type' => 'series', 'query' => 'The Last of Us', 'slug' => 'The Last of Us'],
    'The Mandalorian _' => ['type' => 'series', 'query' => 'The Mandalorian', 'slug' => 'The Mandalorian _'],
    'Wednesday' => ['type' => 'series', 'query' => 'Wednesday', 'slug' => 'Wednesday'],
    'black mirror' => ['type' => 'series', 'query' => 'Black Mirror', 'slug' => 'black mirror'],
    'the godfather' => ['type' => 'movie', 'query' => 'The Godfather', 'slug' => 'the godfather'],
];

echo "=== CINEGX İÇERİK SENKRONİZASYONU BAŞLADI ===\n\n";

// ─── 1. ADIM: "FILLER" İÇERİKLERİ TEMİZLE ───
$allowed_slugs = array_values(array_map(fn($item) => $item['slug'], $video_files));
$allowed_slugs_str = implode(',', array_map(fn($s) => $pdo->quote($s), $allowed_slugs));

echo "Doldurma (videos/ klasöründe videosu bulunmayan) içerikler siliniyor...\n";

// movies temizliği
$deleteMoviesStmt = $pdo->query("DELETE FROM movies WHERE slug NOT IN ($allowed_slugs_str)");
echo "Silinen film sayısı: " . $deleteMoviesStmt->rowCount() . "\n";

// series temizliği
$deleteSeriesStmt = $pdo->query("DELETE FROM series WHERE slug NOT IN ($allowed_slugs_str)");
echo "Silinen dizi sayısı: " . $deleteSeriesStmt->rowCount() . "\n";


// ─── YARDIMCI TMDB FONKSİYONLARI ───
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
    if (empty($genre_ids)) return 3; // Varsayılan: Dram
    
    // TMDB genre mapping to CineGX category IDs:
    // Aksiyon: 1 (Action: 28, Adventure: 12)
    // Bilim Kurgu: 2 (Sci-Fi: 878)
    // Dram: 3 (Drama: 18)
    // Gerilim: 4 (Thriller: 53, Mystery: 9648)
    // Komedi: 5 (Comedy: 35)
    // Suç: 6 (Crime: 80)
    // Fantastik: 7 (Fantasy: 14)
    // Animasyon: 8 (Animation: 16)
    // Korku: 9 (Horror: 27)
    
    foreach ($genre_ids as $id) {
        if ($id == 16) return 8; // Animasyon öncelikli
        if ($id == 28 || $id == 12) return 1; // Aksiyon
        if ($id == 878) return 2; // Bilim Kurgu
        if ($id == 27) return 9; // Korku
        if ($id == 80) return 6; // Suç
        if ($id == 35) return 5; // Komedi
        if ($id == 53 || $id == 9648) return 4; // Gerilim
        if ($id == 14) return 7; // Fantastik
        if ($id == 18) return 3; // Dram
    }
    return 3; // Varsayılan
}

// ─── 2. ADIM: İÇERİKLERİ TMDB'DEN ÇEKEREK EKLE / GÜNCELLE ───
foreach ($video_files as $filename => $info) {
    $type = $info['type'];
    $query = $info['query'];
    $slug = $info['slug'];
    
    echo "\n----------------------------------------\n";
    echo "İşleniyor: [{$type}] {$query} (Slug: {$slug})\n";
    
    // TMDB'de ara
    $endpoint = ($type === 'series') ? 'tv' : 'movie';
    $searchUrl = "https://api.themoviedb.org/3/search/{$endpoint}?api_key={$TMDB_API_KEY}&query=" . urlencode($query) . "&language=tr-TR";
    $searchData = fetchFromTMDB($searchUrl);
    
    // Türkçe sonuç yoksa İngilizce dene
    if (empty($searchData['results'])) {
        $searchUrl = "https://api.themoviedb.org/3/search/{$endpoint}?api_key={$TMDB_API_KEY}&query=" . urlencode($query) . "&language=en-US";
        $searchData = fetchFromTMDB($searchUrl);
    }
    
    if (empty($searchData['results'])) {
        echo "⚠ TMDB'de bulunamadı: {$query}\n";
        continue;
    }
    
    $result = $searchData['results'][0];
    $tmdb_id = $result['id'];
    
    // Detayları çek
    $detailsUrl = "https://api.themoviedb.org/3/{$endpoint}/{$tmdb_id}?api_key={$TMDB_API_KEY}&language=tr-TR";
    $details = fetchFromTMDB($detailsUrl);
    if (!$details || empty($details['overview'])) {
        $detailsUrl = "https://api.themoviedb.org/3/{$endpoint}/{$tmdb_id}?api_key={$TMDB_API_KEY}&language=en-US";
        $details = fetchFromTMDB($detailsUrl);
    }
    
    if (!$details) $details = $result;
    
    // Değerleri hazırla
    $title = $details['title'] ?? $details['name'] ?? $query;
    $description = $details['overview'] ?? 'Açıklama bulunamadı.';
    $imdb_score = !empty($details['vote_average']) ? $details['vote_average'] : 7.0;
    
    $date_str = $details['release_date'] ?? $details['first_air_date'] ?? '2020-01-01';
    $release_year = (int)date('Y', strtotime($date_str));
    if ($release_year < 1900) $release_year = 2020;
    
    $poster_url = !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] : '';
    $backdrop_url = !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $details['backdrop_path'] : '';
    
    // Kategori tespiti
    $genre_ids = [];
    if (!empty($details['genres'])) {
        $genre_ids = array_map(fn($g) => $g['id'], $details['genres']);
    } elseif (!empty($result['genre_ids'])) {
        $genre_ids = $result['genre_ids'];
    }
    $category_id = getTMDBGenreCategoryId($genre_ids);
    
    // Yaş sınırı tespiti (genre'lere göre basitçe ayarla)
    $age_rating = '13+';
    if (in_array(27, $genre_ids) || in_array(80, $genre_ids)) {
        $age_rating = '18+';
    } elseif (in_array(16, $genre_ids)) {
        $age_rating = 'G';
    }
    
    // DB'de var mı kontrol et
    $table = ($type === 'series') ? 'series' : 'movies';
    $checkStmt = $pdo->prepare("SELECT id FROM `$table` WHERE slug = ?");
    $checkStmt->execute([$slug]);
    $existing = $checkStmt->fetch();
    
    $content_id = 0;
    if ($existing) {
        $content_id = $existing['id'];
        echo "Mevcut içerik güncelleniyor (ID: {$content_id})...\n";
        
        if ($type === 'series') {
            $total_seasons = $details['number_of_seasons'] ?? 1;
            $stmt = $pdo->prepare("
                UPDATE series 
                SET title = ?, description = ?, imdb_score = ?, release_year = ?, age_rating = ?, category_id = ?, poster_url = ?, backdrop_url = ?, total_seasons = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $imdb_score, $release_year, $age_rating, $category_id, $poster_url, $backdrop_url, $total_seasons, $content_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE movies 
                SET title = ?, description = ?, imdb_score = ?, release_year = ?, age_rating = ?, category_id = ?, poster_url = ?, backdrop_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $imdb_score, $release_year, $age_rating, $category_id, $poster_url, $backdrop_url, $content_id]);
        }
    } else {
        echo "Yeni içerik ekleniyor...\n";
        if ($type === 'series') {
            $total_seasons = $details['number_of_seasons'] ?? 1;
            $stmt = $pdo->prepare("
                INSERT INTO series (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, total_seasons, poster_url, backdrop_url)
                VALUES (?, ?, ?, ?, ?, ?, 'ongoing', ?, 1, ?, ?, ?)
            ");
            $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $category_id, $total_seasons, $poster_url, $backdrop_url]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO movies (title, slug, description, imdb_score, release_year, age_rating, status, category_id, is_trending, poster_url, backdrop_url)
                VALUES (?, ?, ?, ?, ?, ?, 'released', ?, 1, ?, ?)
            ");
            $stmt->execute([$title, $slug, $description, $imdb_score, $release_year, $age_rating, $category_id, $poster_url, $backdrop_url]);
        }
        $content_id = $pdo->lastInsertId();
    }
    
    // ─── 3. ADIM: YÖNETMEN VE OYUNCULARI ÇEK / GÜNCELLE ───
    $creditsUrl = "https://api.themoviedb.org/3/{$endpoint}/{$tmdb_id}/credits?api_key={$TMDB_API_KEY}&language=tr-TR";
    $credits = fetchFromTMDB($creditsUrl);
    if (!$credits) {
        $creditsUrl = "https://api.themoviedb.org/3/{$endpoint}/{$tmdb_id}/credits?api_key={$TMDB_API_KEY}&language=en-US";
        $credits = fetchFromTMDB($creditsUrl);
    }
    
    // Yönetmen bul
    $director_name = null;
    if ($credits && !empty($credits['crew'])) {
        foreach ($credits['crew'] as $crew) {
            if ($crew['job'] === 'Director' || $crew['job'] === 'Executive Producer' || $crew['job'] === 'Creator') {
                $director_name = $crew['name'];
                break;
            }
        }
    }
    
    if (!$director_name) $director_name = 'Bilinmiyor';
    
    // Yönetmeni güncelle
    $updateDirStmt = $pdo->prepare("UPDATE `$table` SET director = ? WHERE id = ?");
    $updateDirStmt->execute([$director_name, $content_id]);
    echo "Yönetmen güncellendi: {$director_name}\n";
    
    // Oyuncuları güncelle
    if ($credits && !empty($credits['cast'])) {
        // Eski oyuncu bağlantılarını sil
        $delActorsStmt = $pdo->prepare("DELETE FROM content_actors WHERE content_id = ? AND content_type = ?");
        $delActorsStmt->execute([$content_id, $type]);
        
        $cast_limit = 8;
        $count = 0;
        foreach ($credits['cast'] as $cast_member) {
            if ($count >= $cast_limit) break;
            
            $actor_name = $cast_member['name'];
            $character_name = $cast_member['character'] ?? 'Kendisi';
            $profile_path = $cast_member['profile_path'];
            
            $actor_image = '';
            if (!empty($profile_path)) {
                $actor_image = 'https://image.tmdb.org/t/p/w185' . $profile_path;
            } else {
                $actor_image = ''; // boş kalabilir, placeholder basılacak
            }
            
            // Aktör tabloda var mı?
            $checkActor = $pdo->prepare("SELECT id FROM actors WHERE name = ?");
            $checkActor->execute([$actor_name]);
            $actorRow = $checkActor->fetch();
            
            if ($actorRow) {
                $actor_id = $actorRow['id'];
                // Resmi varsa güncelle
                if (!empty($actor_image)) {
                    $upActImg = $pdo->prepare("UPDATE actors SET image_url = ? WHERE id = ?");
                    $upActImg->execute([$actor_image, $actor_id]);
                }
            } else {
                // Yeni aktör ekle
                $insActor = $pdo->prepare("INSERT INTO actors (name, image_url) VALUES (?, ?)");
                $insActor->execute([$actor_name, $actor_image]);
                $actor_id = $pdo->lastInsertId();
            }
            
            // İçerikle bağla
            $insLink = $pdo->prepare("INSERT INTO content_actors (content_id, content_type, actor_id, character_name) VALUES (?, ?, ?, ?)");
            $insLink->execute([$content_id, $type, $actor_id, $character_name]);
            
            $count++;
        }
        echo "Oyuncu kadrosu güncellendi (Toplam: {$count} oyuncu).\n";
    }
    
    // ─── 4. ADIM: DİZİ İSE SEZON VE BÖLÜM EKLE ───
    if ($type === 'series') {
        // Eski sezon ve bölümleri sil
        // Cascade delete olduğu için seasons silinince episodes otomatik silinir.
        $delSeasons = $pdo->prepare("DELETE FROM seasons WHERE series_id = ?");
        $delSeasons->execute([$content_id]);
        
        // Sezon 1 detaylarını çek
        $seasonUrl = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/1?api_key={$TMDB_API_KEY}&language=tr-TR";
        $seasonData = fetchFromTMDB($seasonUrl);
        if (!$seasonData) {
            $seasonUrl = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/1?api_key={$TMDB_API_KEY}&language=en-US";
            $seasonData = fetchFromTMDB($seasonUrl);
        }
        
        if ($seasonData && !empty($seasonData['episodes'])) {
            // Sezon 1 ekle
            $insSeason = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title) VALUES (?, 1, '1. Sezon')");
            $insSeason->execute([$content_id]);
            $season_id = $pdo->lastInsertId();
            
            $ep_count = 0;
            foreach ($seasonData['episodes'] as $episode) {
                $ep_num = $episode['episode_number'];
                $ep_title = $episode['name'] ?? "Bölüm {$ep_num}";
                $ep_desc = $episode['overview'] ?? 'Bölüm açıklaması bulunamadı.';
                if (empty($ep_desc)) $ep_desc = 'Bölüm açıklaması bulunamadı.';
                $duration = $episode['runtime'] ? $episode['runtime'] . 'd' : '45d';
                
                $insEpisode = $pdo->prepare("
                    INSERT INTO episodes (season_id, episode_number, title, duration, description)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insEpisode->execute([$season_id, $ep_num, $ep_title, $duration, $ep_desc]);
                $ep_count++;
            }
            echo "Dizi Sezon 1 ve {$ep_count} bölüm başarıyla eklendi.\n";
        } else {
            // Hiç bulunamadıysa dummy sezon ekle
            $insSeason = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title) VALUES (?, 1, '1. Sezon')");
            $insSeason->execute([$content_id]);
            $season_id = $pdo->lastInsertId();
            
            $insEpisode = $pdo->prepare("
                INSERT INTO episodes (season_id, episode_number, title, duration, description)
                VALUES (?, 1, 'Pilot Bölüm', '45m', 'Dizinin heyecan dolu ilk pilot bölümü.')
            ");
            $insEpisode->execute([$season_id]);
            echo "Dummy Sezon 1 ve 1 pilot bölüm eklendi.\n";
        }
    }
    
    // TMDB API limitlerine saygı için
    usleep(150000); // 150ms bekle
}

echo "\n=== TÜM İÇERİK SENKRONİZASYONU TAMAMLANDI ===\n";
