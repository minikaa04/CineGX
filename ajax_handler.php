<?php
/**
 * CineGX — AJAX API Handler
 * Yorum ekleme, beğenme gibi asenkron işlemler için.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Güvenlik: Sadece POST isteklerine izin ver
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu.']);
    exit;
}

// Güvenlik: Oturum kontrolü
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'post_comment':
        $userId = $_SESSION['user_id'];
        $contentType = isset($_POST['content_type']) && $_POST['content_type'] === 'series' ? 'series' : 'movie';
        $contentId = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $commentText = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

        // Basit Doğrulama
        if ($contentId <= 0 || $rating < 1 || $rating > 10 || empty($commentText)) {
            echo json_encode(['success' => false, 'message' => 'Lütfen tüm alanları geçerli şekilde doldurun. Puan 1-10 arası olmalıdır.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO comments (user_id, content_id, content_type, rating, comment_text, likes) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$userId, $contentId, $contentType, $rating, $commentText]);
            
            echo json_encode(['success' => true, 'message' => 'Yorumunuz başarıyla eklendi.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
        }
        break;

    case 'like_comment':
        $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
        
        if ($commentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz yorum ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE comments SET likes = likes + 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            
            echo json_encode(['success' => true, 'message' => 'Yorum beğenildi.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Hata oluştu.']);
        }
        break;

    case 'toggle_favorite':
        $userId = $_SESSION['user_id'];
        $contentType = isset($_POST['content_type']) && $_POST['content_type'] === 'series' ? 'series' : 'movie';
        $contentId = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
        
        if ($contentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz içerik ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND content_id = ? AND content_type = ?");
            $stmt->execute([$userId, $contentId, $contentType]);
            $fav = $stmt->fetch();

            if ($fav) {
                $del = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
                $del->execute([$fav['id']]);
                echo json_encode(['success' => true, 'is_active' => false, 'message' => 'Favorilerden çıkarıldı.']);
            } else {
                $ins = $pdo->prepare("INSERT INTO favorites (user_id, content_id, content_type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $contentId, $contentType]);
                echo json_encode(['success' => true, 'is_active' => true, 'message' => 'Favorilere eklendi.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_watchlist':
        $userId = $_SESSION['user_id'];
        $contentType = isset($_POST['content_type']) && $_POST['content_type'] === 'series' ? 'series' : 'movie';
        $contentId = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
        
        if ($contentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz içerik ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND content_id = ? AND content_type = ?");
            $stmt->execute([$userId, $contentId, $contentType]);
            $wl = $stmt->fetch();

            if ($wl) {
                $del = $pdo->prepare("DELETE FROM watchlist WHERE id = ?");
                $del->execute([$wl['id']]);
                echo json_encode(['success' => true, 'is_active' => false, 'message' => 'İzleme listesinden çıkarıldı.']);
            } else {
                $ins = $pdo->prepare("INSERT INTO watchlist (user_id, content_id, content_type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $contentId, $contentType]);
                echo json_encode(['success' => true, 'is_active' => true, 'message' => 'İzleme listesine eklendi.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Bilinmeyen işlem.']);
        break;
}
