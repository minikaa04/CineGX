<?php
/**
 * VOD Platform - Güvenli Veritabanı Bağlantısı (PDO)
 * GÖREV.md Madde 4: PDO Prepared Statements, utf8mb4_unicode_ci
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'cinegx');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    error_log("Veritabanı Bağlantı Hatası: " . $e->getMessage());
    http_response_code(500);
    die("Sistemde bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
}
