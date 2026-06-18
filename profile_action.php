<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor.']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = currentUserId();

if ($action === 'update_theme') {
    $theme = $_POST['theme'] ?? 'default';
    $themeBg = $_POST['theme_bg'] ?? '';

    $stmt = $pdo->prepare("UPDATE users SET theme = ?, theme_bg = ? WHERE id = ?");
    if ($stmt->execute([$theme, $themeBg, $userId])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
    }
    exit;
}

if ($action === 'update_profile') {
    $username = trim($_POST['username'] ?? '');
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı adı boş olamaz.']);
        exit;
    }

    // Avatar upload
    $avatarUrl = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/img/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['avatar']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newName)) {
                $avatarUrl = 'assets/img/avatars/' . $newName;
            }
        }
    }

    if ($avatarUrl) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, avatar = ? WHERE id = ?");
        $success = $stmt->execute([$username, $avatarUrl, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $success = $stmt->execute([$username, $userId]);
    }

    if ($success) {
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true, 'message' => 'Profil güncellendi.', 'avatarUrl' => $avatarUrl]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu.']);
    }
    exit;
}

if ($action === 'upload_custom_bg') {
    if (isset($_FILES['custom_bg']) && $_FILES['custom_bg']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/img/backgrounds/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['custom_bg']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $newName = 'bg_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['custom_bg']['tmp_name'], $uploadDir . $newName)) {
                $bgUrl = 'assets/img/backgrounds/' . $newName;
                
                // Veritabanına "custom" tema olarak kaydet
                $stmt = $pdo->prepare("UPDATE users SET theme = 'custom', theme_bg = ? WHERE id = ?");
                $stmt->execute([$bgUrl, $userId]);
                
                echo json_encode(['success' => true, 'bgUrl' => $bgUrl]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false, 'message' => 'Dosya yüklenemedi. Sadece JPG, PNG veya WEBP.']);
    exit;
}

if ($action === 'add_card') {
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $expMonth = $_POST['exp_month'] ?? '';
    $expYear = $_POST['exp_year'] ?? '';
    $cardholder = trim($_POST['cardholder_name'] ?? '');

    if (strlen($cardNumber) < 15 || empty($expMonth) || empty($expYear) || empty($cardholder)) {
        echo json_encode(['success' => false, 'message' => 'Lütfen tüm kart bilgilerini eksiksiz girin.']);
        exit;
    }

    $last4 = substr($cardNumber, -4);
    
    $stmt = $pdo->prepare("INSERT INTO payment_methods (user_id, card_number_last4, exp_month, exp_year, cardholder_name) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$userId, $last4, $expMonth, $expYear, $cardholder])) {
        echo json_encode(['success' => true, 'message' => 'Kart başarıyla eklendi.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kart eklenemedi.']);
    }
    exit;
}

if ($action === 'delete_card') {
    $cardId = $_POST['card_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$cardId, $userId])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Silinemedi.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
