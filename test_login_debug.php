<?php
/**
 * Login Debug Test - Sorun tespiti
 */
require_once __DIR__ . '/includes/db.php';

echo "<h2>Password Hash/Verify Debug Test</h2>";

// Test 1: password_hash ve password_verify çalışıyor mu?
$testPass = 'test123';
$hash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 12]);
echo "<p><strong>Test 1 - Hash/Verify:</strong> ";
echo password_verify($testPass, $hash) ? '✅ ÇALIŞIYOR' : '❌ BAŞARISIZ';
echo "</p>";

// Test 2: Veritabanındaki kullanıcıları listele
echo "<h3>Veritabanındaki Kullanıcılar:</h3>";
$stmt = $pdo->query("SELECT id, username, email, password_hash FROM users");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>Username</th><th>Email</th><th>Hash (ilk 30)</th><th>Hash Uzunluğu</th><th>'password' ile verify</th></tr>";
foreach ($users as $u) {
    $verifyResult = password_verify('password', $u['password_hash']);
    echo "<tr>";
    echo "<td>{$u['id']}</td>";
    echo "<td>{$u['email']}</td>";
    echo "<td>{$u['username']}</td>";
    echo "<td>" . substr($u['password_hash'], 0, 30) . "...</td>";
    echo "<td>" . strlen($u['password_hash']) . "</td>";
    echo "<td>" . ($verifyResult ? '✅ Eşleşti' : '❌ Eşleşmedi') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 3: Session durumu
echo "<h3>Session Durumu:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Session Status: " . session_status() . " (1=disabled, 2=active)</p>";
