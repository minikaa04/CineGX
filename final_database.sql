SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

CREATE DATABASE IF NOT EXISTS `cinegx` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cinegx`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `email`, `password_hash`, `is_premium`, `is_admin`) VALUES
('admin', 'admin@vod.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1),
('user', 'user@vod.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0),
('elif_k', 'elif@vod.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0),
('mert_y', 'mert@vod.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 0),
('zeynep_d', 'zeynep@vod.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0);

CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL,
  `price_yearly` decimal(10,2) NOT NULL,
  `max_screens` int(11) NOT NULL,
  `resolution` varchar(20) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plans` (`name`, `price_monthly`, `price_yearly`, `max_screens`, `resolution`, `description`) VALUES
('Öğrenci Planı', 49.99, 479.99, 1, '480p (SD)', 'Sadece mobil ve tablet için, tek ekran.'),
('Standart Plan', 99.99, 959.99, 2, '1080p (HD)', 'Çoğu cihazda izleyin, aynı anda 2 ekran.'),
('Ultra Plan', 149.99, 1439.99, 4, '4K+HDR', 'En iyi görüntü kalitesi, 4 ekran.'),
('Aile Planı', 199.99, 1919.99, 6, '4K+HDR', 'Geniş aileler için ebeveyn kontrolleri.');

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `slug`) VALUES
('Aksiyon', 'aksiyon'), ('Bilim Kurgu', 'bilim-kurgu'), ('Dram', 'dram'), ('Gerilim', 'gerilim'), ('Komedi', 'komedi'), ('Suç', 'suc'), ('Fantastik', 'fantastik'), ('Animasyon', 'animasyon'), ('Korku', 'korku');

CREATE TABLE `movies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `description` text NOT NULL,
  `imdb_score` decimal(3,1) NOT NULL,
  `release_year` int(4) NOT NULL,
  `age_rating` varchar(10) NOT NULL,
  `status` enum('released','coming_soon') NOT NULL DEFAULT 'released',
  `release_date` date DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `is_trending` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `description` text NOT NULL,
  `imdb_score` decimal(3,1) NOT NULL,
  `release_year` int(4) NOT NULL,
  `age_rating` varchar(10) NOT NULL,
  `status` enum('ongoing','completed','coming_soon') NOT NULL DEFAULT 'ongoing',
  `release_date` date DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `is_trending` tinyint(1) DEFAULT 0,
  `total_seasons` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GÖREV.MD MADDE 12 KESİN İÇERİK LİSTESİ (Smart File Binding ile)
-- slug sütununa tam olarak MP4 dosya isimleri girildi, böylece sistem videos/SLUG.mp4 arayacak.

-- MADDEDE İSTENEN FİLMLER
INSERT INTO `movies` (`title`, `slug`, `description`, `imdb_score`, `release_year`, `age_rating`, `status`, `category_id`, `is_trending`) VALUES
('Dune', 'Dune', 'Uzak bir gelecekte, ailesi çöl gezegeni Arrakis''in kontrolünü üstlenen Paul Atreides''in destansı hikayesi.', 8.0, 2021, '13+', 'released', 2, 1),
('Interstellar', 'Interstellar', 'İnsanlığın yok olmanın eşiğinde olduğu bir gelecekte, bir grup astronot yeni bir yuva bulmak amacıyla yıldızlararası bir yolculuğa çıkar.', 8.7, 2014, '13+', 'released', 2, 1),
('JOKER', 'JOKER', 'Toplum tarafından dışlanmış Arthur Fleck, deliliğin uçurumundan aşağı sürüklenerek Gotham şehrinde bir suç dehasına dönüşür.', 8.4, 2019, '18+', 'released', 3, 1),
('Spider-Man_Brand New Day', 'Spider-Man_Brand New Day', 'Peter Parker, hayatını baştan kurmaya çalışırken yeni tehlikelerle yüzleşmek zorunda kalır.', 7.5, 2024, '13+', 'released', 1, 1),
('The Dark Knight', 'The Dark Knight', 'Batman, Joker adında anarşist bir deha ortaya çıkıp şehri kaosa sürüklediğinde büyük bir sınav verir.', 9.0, 2008, '16+', 'released', 1, 1),
('The Godfather', 'The Godfather', 'Corleone mafya ailesinin, mafya babası Vito Corleone''nin en küçük oğlu Michael''ın acımasız bir patrona dönüşmesini anlatan destansı hikayesi.', 9.2, 1972, '18+', 'released', 3, 1),
('Fight Club', 'Fight Club', 'Uykusuzluk çeken bir ofis çalışanı ve umursamaz bir sabun üreticisi, gizli bir dövüş kulübü kurarak hayatlarına yeni bir anlam katarlar.', 8.8, 1999, '18+', 'released', 3, 1),
('The Hangover', 'The Hangover', 'Las Vegas''taki bir bekarlığa veda partisinin ardından uyanan üç arkadaş, damadı kaybettiklerini fark eder ve geceyi hatırlamaya çalışır.', 7.7, 2009, '18+', 'released', 5, 1),
('Superbad', 'Superbad', 'İki lise öğrencisi, mezuniyet öncesi bir partiye içki alarak kızların ilgisini çekmeye çalışır ancak planları ters gider.', 7.6, 2007, '18+', 'released', 5, 0),
('Spider-Man_Into the Spider-Verse', 'Spider-Man_Into the Spider-Verse', 'Genç Miles Morales, farklı boyutlardan gelen diğer Örümcek-Adamlarla tanışarak dünyayı kurtarmak için güçlerini birleştirir.', 8.4, 2018, '7+', 'released', 8, 1),
('Spirited Away', 'Spirited Away', 'Ailesiyle yeni bir şehre taşınırken kendini ruhlar dünyasında bulan küçük Chihiro, ailesini kurtarmak için cadı Yubaba için çalışmaya başlar.', 8.6, 2001, '7+', 'released', 8, 1),
('The Shining', 'The Shining', 'Kış sezonunda kapanan ıssız bir otele bekçi olarak giden yazar Jack Torrance, oteldeki doğaüstü olayların etkisiyle aklını yitirmeye başlar.', 8.4, 1980, '18+', 'released', 9, 1),
('Get Out', 'Get Out', 'Siyahi bir adam, beyaz kız arkadaşının ailesiyle tanışmaya gittiğinde kendini korkunç ve ürpertici bir sırrın içinde bulur.', 7.8, 2017, '18+', 'released', 9, 1),
('Avengers_Endgame', 'Avengers_Endgame', 'Evrenin yarısı yok edildikten sonra hayatta kalan Yenilmezler, Thanos''un eylemlerini geri almak için zaman yolculuğu yapar.', 8.4, 2019, '13+', 'released', 1, 1),
('Mad Max_Fury Road', 'Mad Max_Fury Road', 'Kıyamet sonrası çorak topraklarda, zorba bir lidere karşı isyan eden Furiosa, tehlikeli bir kaçış için Max ile güçlerini birleştirir.', 8.1, 2015, '18+', 'released', 1, 1),
('Gladiator', 'Gladiator', 'Roma İmparatoru''nun ihanetine uğrayan bir general, gladyatör olarak intikam arayışına girer.', 8.5, 2000, '18+', 'released', 1, 1),
('Se7en', 'Se7en', 'İki dedektif, yedi ölümcül günahı motif olarak kullanan zeki ve acımasız bir seri katili yakalamaya çalışır.', 8.6, 1995, '18+', 'released', 4, 1);

-- MADDEDE İSTENEN DİZİLER
INSERT INTO `series` (`title`, `slug`, `description`, `imdb_score`, `release_year`, `age_rating`, `status`, `category_id`, `is_trending`, `total_seasons`) VALUES
('Breaking Bad', 'Breaking Bad', 'Kanser teşhisi konulan bir kimya öğretmeni, ailesini güvence altına almak için metamfetamin üretip satmaya başlar.', 9.5, 2008, '18+', 'completed', 6, 1, 5),
('Squid Game', 'Squid Game', 'Borç batağındaki yüzlerce kişi, büyük bir para ödülü kazanmak umuduyla ölümcül çocuk oyunlarına katılır.', 8.0, 2021, '18+', 'completed', 4, 1, 1),
('Stranger Things', 'Stranger Things', 'Genç bir çocuğun kaybolması, kasabada gizli deneyler ve doğaüstü güçlerle dolu bir gizemi ortaya çıkarır.', 8.7, 2016, '16+', 'ongoing', 2, 1, 4),
('The Last of Us', 'The Last of Us', 'Kıyamet sonrası dünyada, Joel ve salgına bağışıklığı olan Ellie tehlikeli bir yolculuğa çıkar.', 8.8, 2023, '18+', 'ongoing', 2, 1, 1),
('THE WITCHER', 'THE WITCHER', 'Mutasyona uğramış canavar avcısı Rivialı Geralt, kendi yerini bulmaya çalışırken kader onu güçlü bir büyücüye götürür.', 8.1, 2019, '18+', 'ongoing', 7, 1, 3),
('The Office', 'The Office', 'Bir kağıt şirketinin çalışanlarının günlük hayatlarını konu alan eğlenceli ve absürt olaylarla dolu bir belgesel.', 9.0, 2005, '13+', 'completed', 5, 1, 9),
('Friends', 'Friends', 'New York''ta yaşayan altı yakın arkadaşın hayatı, ilişkileri ve kariyerleri üzerinden anlatılan unutulmaz bir hikaye.', 8.9, 1994, '13+', 'completed', 5, 1, 10),
('Brooklyn Nine-Nine', 'Brooklyn Nine-Nine', 'New York polis departmanında çalışan yetenekli ama çocuksu bir dedektif ile disiplinli yeni yüzbaşısının komik olayları.', 8.4, 2013, '13+', 'completed', 5, 1, 8),
('Modern Family', 'Modern Family', 'Birbirine bağlı ama çok farklı üç ailenin komik ve duygusal hikayesini izleyen modern bir aile belgeseli.', 8.5, 2009, '13+', 'completed', 5, 1, 11),
('Rick and Morty', 'Rick and Morty', 'Alkolik dahi bilim adamı Rick ve torunu Morty''nin çoklu evrenler ve farklı boyutlar arası çılgın maceraları.', 9.1, 2013, '18+', 'ongoing', 8, 1, 7),
('BoJack Horseman', 'BoJack Horseman', 'Bir zamanların ünlü sitcom yıldızı insan-at BoJack Horseman, Hollywood''da geçmişiyle ve kendi varoluşsal krizleriyle yüzleşir.', 8.8, 2014, '18+', 'completed', 8, 1, 6),
('Succession', 'Succession', 'Küresel bir medya ve eğlence devini yöneten Roy ailesinin, imparatorluğun kontrolünü ele geçirme savaşları.', 8.9, 2018, '18+', 'completed', 3, 1, 4),
('The Boys', 'The Boys', 'Süper kahramanların yozlaştığı bir dünyada, güçlerini kötüye kullanan süperlere karşı mücadele eden sivil bir grup.', 8.7, 2019, '18+', 'ongoing', 1, 1, 4);

CREATE TABLE `seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `season_number` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `seasons` (`series_id`, `season_number`, `title`) VALUES
(1, 1, '1. Sezon'), (2, 1, '1. Sezon'), (3, 1, '1. Sezon'), (4, 1, '1. Sezon'), (5, 1, '1. Sezon'),
(6, 1, '1. Sezon'), (7, 1, '1. Sezon'), (8, 1, '1. Sezon'), (9, 1, '1. Sezon'), (10, 1, '1. Sezon'),
(11, 1, '1. Sezon'), (12, 1, '1. Sezon'), (13, 1, '1. Sezon');

CREATE TABLE `episodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season_id` int(11) NOT NULL,
  `episode_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `duration` varchar(10) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`season_id`) REFERENCES `seasons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `episodes` (`season_id`, `episode_number`, `title`, `duration`, `description`) VALUES
(1, 1, 'Pilot', '58m', 'Walter White kanser olduğunu öğrenir ve her şeyi değiştirecek bir karar alır.'),
(2, 1, 'Kırmızı Işık, Yeşil Işık', '60m', 'Ölümcül çocuk oyunlarının ilk turu başlar.'),
(3, 1, 'Will''in Kayboluşu', '49m', 'Genç bir çocuğun kaybolmasıyla kasaba altüst olur.'),
(4, 1, 'Enfekte Olduğunda', '50m', 'Joel ve Tess, bağışıklığı olan Ellie''yi kaçırma görevini üstlenir.'),
(5, 1, 'Yolun Sonu', '55m', 'Geralt ilk büyük canavar avına çıkar.'),
(6, 1, 'Pilot', '22m', 'Dunder Mifflin kağıt şirketinde yeni bir gün başlar.'),
(7, 1, 'Pilot', '22m', 'Rachel düğününden kaçar ve Central Perk''te arkadaşlarıyla tanışır.'),
(8, 1, 'Pilot', '22m', 'Dedektif Jake Peralta yeni kaptanı Raymond Holt ile tanışır.'),
(9, 1, 'Pilot', '22m', 'Pritchett, Dunphy ve Tucker-Pritchett ailelerini tanıyoruz.'),
(10, 1, 'Pilot', '23m', 'Rick, Morty''yi tehlikeli bir boyutlararası maceraya sürükler.'),
(11, 1, 'BoJack Horseman: Hikaye', '25m', 'BoJack kariyerine geri dönmek için bir plan yapar.'),
(12, 1, 'Kutlama', '63m', 'Logan Roy emeklilik haberini verir, aile kaosa sürüklenir.'),
(13, 1, 'İsim', '60m', 'Hughie''nin hayatı bir süper kahraman kazasıyla altüst olur.');

CREATE TABLE `collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `collections` (`title`) VALUES
('Ödüllü Filmler'), ('Hafta Sonu Maratonu İçin Diziler'), ('Sadece Bu Platformda'), ('Adrenalin Dolu Aksiyon'), ('Gülmek İçin En İyiler');

CREATE TABLE `collection_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `content_type` enum('movie','series') NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`collection_id`) REFERENCES `collections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `collection_items` (`collection_id`, `content_id`, `content_type`) VALUES
(1, 1, 'movie'), (1, 2, 'movie'), (1, 3, 'movie'), (1, 6, 'movie'), (1, 7, 'movie'),
(2, 1, 'series'), (2, 2, 'series'), (2, 3, 'series'), (2, 12, 'series'), (2, 13, 'series'),
(3, 4, 'series'), (3, 5, 'series'), (3, 10, 'series'), (3, 11, 'series'),
(4, 4, 'movie'), (4, 5, 'movie'), (4, 14, 'movie'), (4, 15, 'movie'), (4, 16, 'movie'),
(5, 8, 'movie'), (5, 9, 'movie'), (5, 6, 'series'), (5, 7, 'series'), (5, 8, 'series');

CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `content_type` enum('movie','series') NOT NULL,
  `rating` int(2) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 10),
  `comment_text` text NOT NULL,
  `likes` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Her içerik için en az 3 gerçek kullanıcı incelemesi (GÖREV.md Madde 2)
INSERT INTO `comments` (`user_id`, `content_id`, `content_type`, `rating`, `comment_text`, `likes`) VALUES
-- Dune (movie 1)
(2, 1, 'movie', 9, 'Denis Villeneuve vizyonuyla adeta bir görsel şölen. Sinematografi muhteşem.', 124),
(3, 1, 'movie', 8, 'Kitaba sadık kalınmış, Timothée Chalamet mükemmel bir Paul Atreides.', 87),
(4, 1, 'movie', 9, 'Bilim kurgu sevenlerin mutlaka izlemesi gereken bir başyapıt.', 203),
-- Interstellar (movie 2)
(2, 2, 'movie', 10, 'Zaman, uzay ve sevgi üzerine yapılmış en iyi film. Nolan dehasını konuşturmuş.', 540),
(3, 2, 'movie', 10, 'Hans Zimmer müzikleri ile birlikte tam bir sinema deneyimi.', 312),
(5, 2, 'movie', 9, 'Bilimsel detaylar ve duygusal derinlik mükemmel harmanlanmış.', 189),
-- JOKER (movie 3)
(2, 3, 'movie', 10, 'Joaquin Phoenix''in oyunculuğu inanılmaz. Oscar hak edilmiş.', 320),
(4, 3, 'movie', 9, 'Toplumsal eleştirisi ve karakter çalışması ile unutulmaz bir film.', 156),
(5, 3, 'movie', 8, 'Karanlık ama etkileyici. Phoenix''siz düşünülemez.', 98),
-- Spider-Man Brand New Day (movie 4)
(3, 4, 'movie', 8, 'Peter Parker''ın yeni başlangıcı heyecan verici. Aksiyon sahneleri harika.', 67),
(4, 4, 'movie', 7, 'Klasik Spider-Man ruhunu yakalayan eğlenceli bir film.', 45),
(5, 4, 'movie', 8, 'Süper kahraman filmlerinin en iyilerinden biri.', 89),
-- The Dark Knight (movie 5)
(2, 5, 'movie', 10, 'Heath Ledger''ın Joker''ı sinema tarihinin en iyi kötü karakteri.', 890),
(3, 5, 'movie', 10, 'Nolan üçlemesinin zirvesi. Mükemmel senaryo ve oyunculuk.', 654),
(4, 5, 'movie', 9, 'Süper kahraman filmi olmaktan öte bir suç draması başyapıtı.', 432),
-- The Godfather (movie 6)
(2, 6, 'movie', 10, 'Sinema tarihinin tartışmasız en büyük filmi. Brando efsanevi.', 780),
(3, 6, 'movie', 10, 'Her sahne, her diyalog kusursuz işlenmiş. Başyapıtların başyapıtı.', 654),
(5, 6, 'movie', 10, 'Mafya filmlerinin babası. Al Pacino''nun dönüşümü inanılmaz.', 543),
-- Fight Club (movie 7)
(2, 7, 'movie', 9, 'Fincher''ın dehası ve Pitt-Norton ikilisinin kimyası muhteşem.', 234),
(4, 7, 'movie', 10, 'Tüketim toplumuna en sert eleştiri. Sonu efsane.', 345),
(5, 7, 'movie', 9, 'Her izleyişte yeni detaylar keşfediyorsunuz. Kült klasik.', 198),
-- The Hangover (movie 8)
(3, 8, 'movie', 8, 'Gülmekten karnım ağrıdı. Zach Galifianakis süper.', 123),
(4, 8, 'movie', 7, 'Arkadaşlarla izlenmesi gereken efsane komedi.', 89),
(5, 8, 'movie', 8, 'Las Vegas sahneleri unutulmaz. En iyi komedi filmlerinden.', 156),
-- Superbad (movie 9)
(2, 9, 'movie', 7, 'Lise komedilerinin en iyisi. Jonah Hill harika.', 67),
(3, 9, 'movie', 8, 'Samimi ve komik. Gerçek arkadaşlık hikayesi.', 54),
(4, 9, 'movie', 7, 'McLovin sahnesi tek başına filmi izlemeye değer kılıyor.', 98),
-- Spider-Man Into the Spider-Verse (movie 10)
(2, 10, 'movie', 10, 'Animasyon tarihinin en yenilikçi filmi. Görsel bir şaheser.', 456),
(3, 10, 'movie', 9, 'Miles Morales''in hikayesi çok güçlü. Müzikler harika.', 312),
(5, 10, 'movie', 10, 'Her karesi bir çizgi roman sayfası gibi. Muhteşem sanat yönetimi.', 278),
-- Spirited Away (movie 11)
(2, 11, 'movie', 10, 'Miyazaki''nin başyapıtı. Her yaştan izleyici için büyülü.', 567),
(3, 11, 'movie', 10, 'Japon animasyonunun dünyaya armağanı. Duygusal ve derin.', 432),
(4, 11, 'movie', 9, 'Çocukluğumun en güzel filmi. Hâlâ aynı büyüyü hissediyorum.', 345),
-- The Shining (movie 12)
(2, 12, 'movie', 9, 'Kubrick''in dehası her sahnede hissediliyor. Jack Nicholson korkunç iyi.', 234),
(4, 12, 'movie', 10, 'Korku sinemasının tartışmasız en iyi filmi. Ürpertici atmosfer.', 345),
(5, 12, 'movie', 9, 'Yıllar geçse de korkutuculuğunu kaybetmeyen nadir filmlerden.', 198),
-- Get Out (movie 13)
(2, 13, 'movie', 8, 'Jordan Peele korku türüne yepyeni bir soluk getirdi.', 156),
(3, 13, 'movie', 9, 'Toplumsal korku kavramını mükemmel işlemiş. Zekice senaryo.', 234),
(5, 13, 'movie', 8, 'Gerilim ve sosyal eleştiri bir arada. Çok etkileyici.', 123),
-- Avengers Endgame (movie 14)
(2, 14, 'movie', 9, 'MCU''nun 10 yıllık finaline yakışır bir kapanış. Epik savaş sahnesi.', 678),
(3, 14, 'movie', 9, 'Tony Stark''ın vedası yürek burkucu. Marvel''ın en duygusal anı.', 543),
(4, 14, 'movie', 8, 'Fan servisi fazla ama yine de tatmin edici bir final.', 321),
-- Mad Max Fury Road (movie 15)
(3, 15, 'movie', 10, 'Baştan sona adrenalin. Furiosa karakteri efsanevi.', 234),
(4, 15, 'movie', 9, 'Pratik efektler ve aksiyon koreografisi inanılmaz.', 189),
(5, 15, 'movie', 10, 'George Miller 70 yaşında en iyi aksiyon filmini çekmiş.', 267),
-- Gladiator (movie 16)
(2, 16, 'movie', 9, 'Russell Crowe''un en ikonik rolü. Roma''nın ihtişamı perdede.', 345),
(3, 16, 'movie', 9, 'Destansı müzikler ve savaş sahneleri ile mükemmel bir epik.', 278),
(5, 16, 'movie', 10, 'İntikam hikayesinin en güzel anlatımı. Her sahne etkileyici.', 198),
-- Se7en (movie 17)
(2, 17, 'movie', 9, 'Fincher''ın karanlık atmosferi ve o son sahne... Unutulmaz.', 234),
(3, 17, 'movie', 10, 'Gerilim türünün en zekice yazılmış filmi. Morgan Freeman harika.', 312),
(4, 17, 'movie', 9, 'Yedi günah teması o kadar iyi işlenmiş ki tüyleriniz diken diken.', 189),
-- Breaking Bad (series 1)
(2, 1, 'series', 10, 'Televizyon tarihinin en iyi dizisi. Her bölümü sinema kalitesinde.', 890),
(3, 1, 'series', 10, 'Bryan Cranston''ın Walter White performansı eşsiz.', 765),
(5, 1, 'series', 10, 'Finaline kadar kaliteyi koruyan nadir dizilerden. Mükemmel.', 654),
-- Squid Game (series 2)
(2, 2, 'series', 8, 'Kore yapımlarının dünyaya açılan kapısı. Konsept dahice.', 432),
(3, 2, 'series', 8, 'Gerilim ve toplumsal eleştiri bir arada. Çok etkileyici.', 312),
(4, 2, 'series', 7, 'İlk sezon çok iyiydi, oyunlar gerçekten gergin.', 234),
-- Stranger Things (series 3)
(2, 3, 'series', 9, '80''ler nostaljisi ve bilim kurgu mükemmel harmanlanmış.', 567),
(4, 3, 'series', 9, 'Çocuk oyuncuların performansı inanılmaz. Eleven unutulmaz.', 432),
(5, 3, 'series', 8, 'Her sezon daha büyük ve daha epik. Müzikleri harika.', 345),
-- The Last of Us (series 4)
(2, 4, 'series', 10, 'Oyun uyarlamalarının nasıl yapılması gerektiğinin kanıtı.', 543),
(3, 4, 'series', 9, 'Pedro Pascal ve Bella Ramsey kimyası mükemmel.', 432),
(5, 4, 'series', 10, '3. bölüm tek başına bir kısa film şaheseri.', 678),
-- THE WITCHER (series 5)
(2, 5, 'series', 8, 'Henry Cavill, Geralt rolü için doğmuş. Dövüş sahneleri epik.', 234),
(3, 5, 'series', 7, 'Fantastik dünya çok iyi kurgulanmış. Kitaplara sadık.', 189),
(4, 5, 'series', 8, 'Yennefer ve Ciri hikaye hatları çok güçlü.', 156),
-- The Office (series 6)
(3, 6, 'series', 10, 'Michael Scott sinema tarihinin en komik karakteri. Her bölüm altın.', 890),
(4, 6, 'series', 9, 'Mockumentary tarzının zirvesi. Jim ve Dwight ikilisi efsane.', 654),
(5, 6, 'series', 10, 'Yüzlerce kez izledim, hâlâ gülüyorum. Zamansız komedi.', 543),
-- Friends (series 7)
(2, 7, 'series', 9, 'Kuşakları birleştiren dizi. Central Perk''te oturmak isterdim.', 876),
(3, 7, 'series', 9, 'Chandler''ın esprileri hâlâ güncel. En iyi sitcom.', 654),
(4, 7, 'series', 8, 'Ross ve Rachel ilişkisi izleyiciyi 10 sezon ekrana bağladı.', 543),
-- Brooklyn Nine-Nine (series 8)
(2, 8, 'series', 8, 'Andy Samberg ve Andre Braugher ikilisi muhteşem. Eğlenceli polis dizisi.', 234),
(3, 8, 'series', 9, 'Komedi ve polisiye mükemmel dengelenmiş. Captain Holt efsane.', 189),
(5, 8, 'series', 8, 'Her bölümü neşeli ve pozitif. Halloween bölümleri en iyi.', 156),
-- Modern Family (series 9)
(2, 9, 'series', 8, 'Aile komedisinin en güzel hali. Her karakter sevimli.', 234),
(4, 9, 'series', 9, 'Phil Dunphy en iyi TV babası. Komik ve duygusal.', 189),
(5, 9, 'series', 8, 'Gloria ve Jay ilişkisi çok eğlenceli. Güldürürken düşündürüyor.', 123),
-- Rick and Morty (series 10)
(2, 10, 'series', 10, 'Yetişkin animasyonunun zirvesi. Bilim ve felsefe mükemmel işlenmiş.', 432),
(3, 10, 'series', 9, 'Her bölüm farklı bir evren, farklı bir macera. Dahice yazılmış.', 345),
(4, 10, 'series', 9, 'Pickle Rick bölümü tek başına bir şaheser.', 267),
-- BoJack Horseman (series 11)
(2, 11, 'series', 9, 'Animasyon kılığında en derin drama. Depresyon teması çok gerçekçi.', 234),
(3, 11, 'series', 10, 'Free Churro bölümü televizyon tarihinin en iyi monologu.', 312),
(5, 11, 'series', 9, 'Komik başlıyor ama sonra kalbinizi söküp alıyor. Başyapıt.', 198),
-- Succession (series 12)
(2, 12, 'series', 10, 'Zenginlik ve iktidar üzerine en keskin dizi. Senaryo kusursuz.', 345),
(3, 12, 'series', 9, 'Roy ailesi dinamikleri inanılmaz gerçekçi. Her karakter derinlikli.', 267),
(4, 12, 'series', 10, 'Televizyondaki en iyi dram. Her sezon bir öncekinden iyi.', 432),
-- The Boys (series 13)
(2, 13, 'series', 9, 'Süper kahraman yorgunluğuna en iyi panzehir. Cesur ve acımasız.', 345),
(4, 13, 'series', 9, 'Homelander televizyon tarihinin en korkutucu kötü adamı.', 432),
(5, 13, 'series', 8, 'Karanlık mizah ve aksiyon mükemmel harmanlanmış. Büyük beğeni.', 267);

CREATE TABLE `actors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `actors` (`name`, `image_url`) VALUES
('Bryan Cranston', 'posters/actors/bryan-cranston.jpg'),
('Aaron Paul', 'posters/actors/aaron-paul.jpg'),
('Timothée Chalamet', 'posters/actors/timothee-chalamet.jpg'),
('Zendaya', 'posters/actors/zendaya.jpg'),
('Joaquin Phoenix', 'posters/actors/joaquin-phoenix.jpg'),
('Robert De Niro', 'posters/actors/robert-deniro.jpg'),
('Tom Hardy', 'posters/actors/tom-hardy.jpg'),
('Matthew McConaughey', 'posters/actors/matthew-mcconaughey.jpg'),
('Anne Hathaway', 'posters/actors/anne-hathaway.jpg'),
('Christian Bale', 'posters/actors/christian-bale.jpg'),
('Heath Ledger', 'posters/actors/heath-ledger.jpg'),
('Marlon Brando', 'posters/actors/marlon-brando.jpg'),
('Al Pacino', 'posters/actors/al-pacino.jpg'),
('Brad Pitt', 'posters/actors/brad-pitt.jpg'),
('Edward Norton', 'posters/actors/edward-norton.jpg'),
('Bradley Cooper', 'posters/actors/bradley-cooper.jpg'),
('Zach Galifianakis', 'posters/actors/zach-galifianakis.jpg'),
('Jonah Hill', 'posters/actors/jonah-hill.jpg'),
('Michael Cera', 'posters/actors/michael-cera.jpg'),
('Shameik Moore', 'posters/actors/shameik-moore.jpg'),
('Jack Nicholson', 'posters/actors/jack-nicholson.jpg'),
('Daniel Kaluuya', 'posters/actors/daniel-kaluuya.jpg'),
('Allison Williams', 'posters/actors/allison-williams.jpg'),
('Robert Downey Jr.', 'posters/actors/robert-downey-jr.jpg'),
('Chris Evans', 'posters/actors/chris-evans.jpg'),
('Charlize Theron', 'posters/actors/charlize-theron.jpg'),
('Russell Crowe', 'posters/actors/russell-crowe.jpg'),
('Connie Nielsen', 'posters/actors/connie-nielsen.jpg'),
('Morgan Freeman', 'posters/actors/morgan-freeman.jpg'),
('Lee Jung-jae', 'posters/actors/lee-jung-jae.jpg'),
('Park Hae-soo', 'posters/actors/park-hae-soo.jpg'),
('Millie Bobby Brown', 'posters/actors/millie-bobby-brown.jpg'),
('Winona Ryder', 'posters/actors/winona-ryder.jpg'),
('Pedro Pascal', 'posters/actors/pedro-pascal.jpg'),
('Bella Ramsey', 'posters/actors/bella-ramsey.jpg'),
('Henry Cavill', 'posters/actors/henry-cavill.jpg'),
('Anya Chalotra', 'posters/actors/anya-chalotra.jpg'),
('Steve Carell', 'posters/actors/steve-carell.jpg'),
('Jennifer Aniston', 'posters/actors/jennifer-aniston.jpg'),
('Courteney Cox', 'posters/actors/courteney-cox.jpg'),
('Andy Samberg', 'posters/actors/andy-samberg.jpg'),
('Ed O''Neill', 'posters/actors/ed-oneill.jpg'),
('Sofía Vergara', 'posters/actors/sofia-vergara.jpg'),
('Jeremy Allen White', 'posters/actors/jeremy-allen-white.jpg'),
('Will Arnett', 'posters/actors/will-arnett.jpg'),
('Jeremy Strong', 'posters/actors/jeremy-strong.jpg'),
('Brian Cox', 'posters/actors/brian-cox.jpg'),
('Karl Urban', 'posters/actors/karl-urban.jpg'),
('Antony Starr', 'posters/actors/antony-starr.jpg'),
('Tom Holland', 'posters/actors/tom-holland.jpg');

CREATE TABLE `content_actors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `content_type` enum('movie','series') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `character_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`actor_id`) REFERENCES `actors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `content_actors` (`content_id`, `content_type`, `actor_id`, `character_name`) VALUES
-- Dune (movie 1)
(1, 'movie', 3, 'Paul Atreides'), (1, 'movie', 4, 'Chani'),
-- Interstellar (movie 2)
(2, 'movie', 8, 'Cooper'), (2, 'movie', 9, 'Dr. Amelia Brand'),
-- JOKER (movie 3)
(3, 'movie', 5, 'Arthur Fleck / Joker'), (3, 'movie', 6, 'Murray Franklin'),
-- Spider-Man Brand New Day (movie 4)
(4, 'movie', 50, 'Peter Parker / Spider-Man'),
-- The Dark Knight (movie 5)
(5, 'movie', 10, 'Bruce Wayne / Batman'), (5, 'movie', 11, 'Joker'),
-- The Godfather (movie 6)
(6, 'movie', 12, 'Vito Corleone'), (6, 'movie', 13, 'Michael Corleone'),
-- Fight Club (movie 7)
(7, 'movie', 14, 'Tyler Durden'), (7, 'movie', 15, 'Anlatıcı'),
-- The Hangover (movie 8)
(8, 'movie', 16, 'Phil Wenneck'), (8, 'movie', 17, 'Alan Garner'),
-- Superbad (movie 9)
(9, 'movie', 18, 'Seth'), (9, 'movie', 19, 'Evan'),
-- Spider-Man Into the Spider-Verse (movie 10)
(10, 'movie', 20, 'Miles Morales'),
-- Spirited Away (movie 11) - Animasyon, seslendirenler
(11, 'movie', 20, 'Chihiro (seslendirme)'),
-- The Shining (movie 12)
(12, 'movie', 21, 'Jack Torrance'),
-- Get Out (movie 13)
(13, 'movie', 22, 'Chris Washington'), (13, 'movie', 23, 'Rose Armitage'),
-- Avengers Endgame (movie 14)
(14, 'movie', 24, 'Tony Stark / Iron Man'), (14, 'movie', 25, 'Steve Rogers / Captain America'),
-- Mad Max Fury Road (movie 15)
(15, 'movie', 7, 'Max Rockatansky'), (15, 'movie', 26, 'Imperator Furiosa'),
-- Gladiator (movie 16)
(16, 'movie', 27, 'Maximus'), (16, 'movie', 28, 'Lucilla'),
-- Se7en (movie 17)
(17, 'movie', 29, 'Dedektif Somerset'), (17, 'movie', 14, 'Dedektif Mills'),
-- Breaking Bad (series 1)
(1, 'series', 1, 'Walter White'), (1, 'series', 2, 'Jesse Pinkman'),
-- Squid Game (series 2)
(2, 'series', 30, 'Seong Gi-hun'), (2, 'series', 31, 'Cho Sang-woo'),
-- Stranger Things (series 3)
(3, 'series', 32, 'Eleven'), (3, 'series', 33, 'Joyce Byers'),
-- The Last of Us (series 4)
(4, 'series', 34, 'Joel Miller'), (4, 'series', 35, 'Ellie Williams'),
-- THE WITCHER (series 5)
(5, 'series', 36, 'Geralt of Rivia'), (5, 'series', 37, 'Yennefer'),
-- The Office (series 6)
(6, 'series', 38, 'Michael Scott'),
-- Friends (series 7)
(7, 'series', 39, 'Rachel Green'), (7, 'series', 40, 'Monica Geller'),
-- Brooklyn Nine-Nine (series 8)
(8, 'series', 41, 'Jake Peralta'),
-- Modern Family (series 9)
(9, 'series', 42, 'Jay Pritchett'), (9, 'series', 43, 'Gloria Pritchett'),
-- Rick and Morty (series 10) - Animasyon
(10, 'series', 44, 'Rick Sanchez (seslendirme)'),
-- BoJack Horseman (series 11) - Animasyon
(11, 'series', 45, 'BoJack Horseman (seslendirme)'),
-- Succession (series 12)
(12, 'series', 46, 'Kendall Roy'), (12, 'series', 47, 'Logan Roy'),
-- The Boys (series 13)
(13, 'series', 48, 'Billy Butcher'), (13, 'series', 49, 'Homelander');

COMMIT;
