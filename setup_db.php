<?php
require_once __DIR__ . '/includes/db.php';

try {
    // 1. Add columns to users table
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('avatar', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
        echo "Added avatar column to users.\n";
    }
    if (!in_array('theme', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN theme VARCHAR(50) DEFAULT 'default'");
        echo "Added theme column to users.\n";
    }
    if (!in_array('theme_bg', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN theme_bg VARCHAR(255) DEFAULT NULL");
        echo "Added theme_bg column to users.\n";
    }

    // 2. Create payment_methods table
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        card_number_last4 VARCHAR(4) NOT NULL,
        exp_month VARCHAR(2) NOT NULL,
        exp_year VARCHAR(2) NOT NULL,
        cardholder_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "payment_methods table ensured.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
