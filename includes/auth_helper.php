<?php
/**
 * CineGX — Kimlik Doğrulama Yardımcı Fonksiyonları
 * GÖREV.md Madde 4 & 6: Bcrypt, PDO Prepared Statements, Session, CSRF
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================================================================
   CSRF TOKEN YÖNETİMİ
   ================================================================ */

/** CSRF token üretir veya mevcudu döndürür */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Form için hidden CSRF input alanı döndürür */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/** Gönderilen CSRF token'ı doğrular */
function validateCsrfToken(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ================================================================
   KULLANICI KAYIT
   ================================================================ */

/**
 * Yeni kullanıcı kaydı oluşturur.
 * @return array ['success' => bool, 'message' => string]
 */
function registerUser(PDO $pdo, string $username, string $email, string $password): array
{
    // Email kontrolü
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Bu e-posta adresi zaten kayıtlı.'];
    }

    // Kullanıcı adı kontrolü
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Bu kullanıcı adı zaten alınmış.'];
    }

    // Bcrypt ile şifreleme (GÖREV.md Madde 4)
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_premium, is_admin) VALUES (:username, :email, :hash, 0, 0)");
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email,
        ':hash'     => $hash
    ]);

    return ['success' => true, 'message' => 'Hesabınız başarıyla oluşturuldu! Giriş yapabilirsiniz.'];
}

/* ================================================================
   KULLANICI GİRİŞ
   ================================================================ */

/**
 * Kullanıcı girişi yapar ve session başlatır.
 * @return array ['success' => bool, 'message' => string]
 */
function loginUser(PDO $pdo, string $email, string $password): array
{
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, is_premium, is_admin FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Bu e-posta adresine kayıtlı hesap bulunamadı.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Şifre hatalı. Lütfen tekrar deneyin.'];
    }

    // Session bilgilerini ayarla
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['is_premium'] = (bool) $user['is_premium'];
    $_SESSION['is_admin']   = (bool) $user['is_admin'];
    $_SESSION['logged_in']  = true;

    // Session fixation koruması — session verisi ayarlandıktan sonra yap
    session_regenerate_id(true);

    return ['success' => true, 'message' => 'Giriş başarılı! Yönlendiriliyorsunuz...'];
}

/* ================================================================
   OTURUM KONTROL FONKSİYONLARI
   ================================================================ */

/** Kullanıcı giriş yapmış mı? */
function isLoggedIn(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/** Kullanıcı premium üye mi? */
function isPremium(): bool
{
    return isLoggedIn() && isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true;
}

/** Kullanıcı admin mi? */
function isAdmin(): bool
{
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/** Giriş yapılmamışsa login sayfasına yönlendir */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/** Admin değilse ana sayfaya yönlendir */
function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

/** Mevcut kullanıcı ID'sini döndürür */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/** Mevcut kullanıcı adını döndürür */
function currentUsername(): ?string
{
    return $_SESSION['username'] ?? null;
}

/* ================================================================
   ÇIKIŞ
   ================================================================ */

/** Oturumu tamamen sonlandırır */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
