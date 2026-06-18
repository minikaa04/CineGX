<?php
/**
 * CineGX — Profil Sayfası
 * Kullanıcı bilgilerini görüntüler, favorileri, izleme listesini ve tema seçimini yönetir.
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

$userId = currentUserId();
$username = currentUsername();

// Kullanıcı bilgilerini çek
$stmt = $pdo->prepare("SELECT id, username, email, is_premium, created_at, avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Ödeme yöntemleri
$stmtCards = $pdo->prepare("SELECT * FROM payment_methods WHERE user_id = ? ORDER BY created_at DESC");
$stmtCards->execute([$userId]);
$paymentMethods = $stmtCards->fetchAll();

// Abonelik durumu
$stmtSub = $pdo->prepare("
    SELECT s.*, p.name as plan_name, p.price_monthly 
    FROM subscriptions s 
    LEFT JOIN plans p ON s.plan_id = p.id 
    WHERE s.user_id = ? AND s.status = 'active' 
    LIMIT 1
");
$stmtSub->execute([$userId]);
$activeSub = $stmtSub->fetch();

// Tüm Paketler
$stmtAllPlans = $pdo->query("SELECT * FROM plans ORDER BY price_monthly ASC");
$allPlans = $stmtAllPlans->fetchAll();

// Kullanıcının yaptığı yorum sayısı
$stmtCommentCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE user_id = ?");
$stmtCommentCount->execute([$userId]);
$commentCount = $stmtCommentCount->fetch()['cnt'];

// Kullanıcının son yorumları
$stmtRecentComments = $pdo->prepare("
    SELECT c.*, 
           COALESCE(m.title, s.title) AS content_title,
           c.content_type
    FROM comments c
    LEFT JOIN movies m ON c.content_type = 'movie' AND c.content_id = m.id
    LEFT JOIN series s ON c.content_type = 'series' AND c.content_id = s.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmtRecentComments->execute([$userId]);
$recentComments = $stmtRecentComments->fetchAll();

// Favorileri çek
$stmtFavs = $pdo->prepare("
    SELECT f.content_id, f.content_type,
           COALESCE(m.title, s.title) AS title,
           COALESCE(m.poster_url, s.poster_url) AS poster_url,
           COALESCE(m.slug, s.slug) AS slug,
           COALESCE(m.imdb_score, s.imdb_score) AS imdb_score,
           COALESCE(m.release_year, s.release_year) AS release_year
    FROM favorites f
    LEFT JOIN movies m ON f.content_type = 'movie' AND f.content_id = m.id
    LEFT JOIN series s ON f.content_type = 'series' AND f.content_id = s.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmtFavs->execute([$userId]);
$favorites = $stmtFavs->fetchAll();

// İzleme listesini çek
$stmtWl = $pdo->prepare("
    SELECT w.content_id, w.content_type,
           COALESCE(m.title, s.title) AS title,
           COALESCE(m.poster_url, s.poster_url) AS poster_url,
           COALESCE(m.slug, s.slug) AS slug,
           COALESCE(m.imdb_score, s.imdb_score) AS imdb_score,
           COALESCE(m.release_year, s.release_year) AS release_year
    FROM watchlist w
    LEFT JOIN movies m ON w.content_type = 'movie' AND w.content_id = m.id
    LEFT JOIN series s ON w.content_type = 'series' AND w.content_id = s.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmtWl->execute([$userId]);
$watchlist = $stmtWl->fetchAll();

$memberSince = date('d.m.Y', strtotime($user['created_at']));
$initial = strtoupper(mb_substr($username, 0, 1, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-page { padding: 100px 4% 60px; max-width: 1100px; margin: 0 auto; }
        
        .profile-header {
            display: flex; align-items: center; gap: 30px;
            padding: 40px; background: rgba(20, 20, 20, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px; border: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 40px;
        }
        .profile-avatar {
            width: 120px; height: 120px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #b20710);
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; font-weight: 800; color: white; flex-shrink: 0;
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.3);
            overflow: hidden;
            position: relative;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar-edit {
            position: absolute; bottom: 0; left: 0; width: 100%; height: 30px;
            background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; cursor: pointer; opacity: 0; transition: 0.3s;
        }
        .profile-avatar:hover .profile-avatar-edit { opacity: 1; }
        .profile-details h1 { font-size: 2rem; margin-bottom: 5px; }
        .profile-email { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 10px; }
        .profile-stats {
            display: flex; gap: 20px; flex-wrap: wrap;
        }
        .stat-item {
            font-size: 0.85rem; color: var(--text-secondary);
            display: flex; align-items: center; gap: 6px;
        }
        .stat-value { color: var(--accent); font-weight: 700; }
        .premium-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000; padding: 3px 10px; border-radius: 4px;
            font-size: 0.75rem; font-weight: 700;
        }

        .section-title {
            font-size: 1.4rem; font-weight: 700; margin-bottom: 20px;
            border-left: 4px solid var(--accent); padding-left: 15px;
            margin-top: 40px;
        }
        
        .comment-card {
            background: rgba(255,255,255,0.04); padding: 20px;
            border-radius: 10px; margin-bottom: 12px;
            border: 1px solid rgba(255,255,255,0.06);
            transition: 0.2s;
        }
        .comment-card:hover { background: rgba(255,255,255,0.06); }
        .comment-card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px;
        }
        .comment-content-title {
            font-weight: 600; color: var(--accent);
        }
        .comment-card-rating { color: #FFD700; font-weight: bold; }
        .comment-card-text { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; }
        .comment-card-date { color: var(--text-muted); font-size: 0.8rem; margin-top: 8px; }

        .empty-state {
            text-align: center; padding: 45px;
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
            border: 1px dashed rgba(255,255,255,0.1);
            color: var(--text-muted); font-size: 0.95rem;
        }

        /* Tema Seçimi Menüsü CSS */
        .theme-selector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }
        .theme-option-card {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.06);
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .theme-option-card:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-2px);
        }
        .theme-option-card.active {
            border-color: var(--accent);
            background: rgba(229, 9, 20, 0.05);
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.25);
        }
        .theme-preview {
            width: 100%;
            height: 80px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .theme-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 20px;
        }

        /* Yeni Profil Modülleri */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        .settings-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 25px;
        }
        .settings-card h3 { font-size: 1.2rem; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .form-group-custom { margin-bottom: 15px; }
        .form-group-custom label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; }
        .form-group-custom input { width: 100%; padding: 12px; border-radius: 6px; background: var(--bg-input); border: 1px solid var(--border); color: white; }
        .btn-update { background: var(--accent); color: white; padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-update:hover { background: var(--accent-hover); }
        
        .card-item { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.05); }
        .card-info { display: flex; align-items: center; gap: 15px; }
        .card-icon { font-size: 1.5rem; color: var(--text-secondary); }

        .custom-file-upload { display: inline-flex; align-items: center; gap: 8px; padding: 10px 15px; cursor: pointer; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; font-size: 0.9rem; transition: 0.3s; color: var(--text-primary); }
        .custom-file-upload:hover { background: rgba(255,255,255,0.1); border-color: var(--accent); }
        .file-name { margin-left: 10px; font-size: 0.85rem; color: var(--text-secondary); font-style: italic; }
        input[type="file"] { display: none !important; }

        /* Yeni UI Tasarımları */
        .profile-avatar-wrapper { position: relative; display: inline-block; cursor: pointer; }
        .profile-avatar-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; }
        .profile-avatar-wrapper:hover .profile-avatar-overlay { opacity: 1; }
        
        .username-container { display: flex; align-items: center; gap: 10px; }
        .username-container h1 { margin: 0; display: flex; align-items: center; gap: 10px; }
        .edit-name-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 5px; opacity: 0.6; transition: 0.3s; }
        .edit-name-btn:hover { opacity: 1; color: var(--accent); }
        .edit-name-input { display: none; background: rgba(0,0,0,0.5); border: 1px solid var(--border); color: white; padding: 5px 10px; font-size: 2rem; font-weight: bold; border-radius: 6px; width: 200px; }
        .save-name-btn { display: none; background: var(--accent); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }

        .add-theme-card { display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(255,255,255,0.02); border: 2px dashed rgba(255,255,255,0.2); border-radius: 8px; cursor: pointer; transition: 0.3s; height: 100%; min-height: 110px; }
        .add-theme-card:hover { border-color: var(--text-primary); background: rgba(255,255,255,0.05); }
        .add-theme-icon { font-size: 2rem; color: var(--text-secondary); margin-bottom: 5px; }

        /* Abonelik Accordion CSS */
        .subscription-accordion { margin-bottom: 40px; }
        .accordion-header { display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.05); padding: 15px 20px; border-radius: 8px; cursor: pointer; transition: 0.3s; border: 1px solid rgba(255,255,255,0.1); }
        .accordion-header:hover { background: rgba(255,255,255,0.08); }
        .accordion-header h2 { margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        .accordion-content { overflow: hidden; max-height: 0; transition: max-height 0.5s ease-in-out; background: rgba(0,0,0,0.2); border-radius: 0 0 8px 8px; margin-top: -5px; padding: 0 20px; border: 1px solid rgba(255,255,255,0.05); border-top: none; }
        .accordion-content.open { max-height: 1500px; padding: 20px; }
        
        /* Abonelik Detayları */
        .sub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .sub-card { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); }
        .sub-card h3 { margin-top: 0; margin-bottom: 15px; color: var(--text-secondary); font-size: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .active-plan-badge { display: inline-block; background: var(--accent); color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 1.2rem; margin-bottom: 10px; }
        .upgrade-alert { margin-top: 15px; padding: 15px; background: rgba(229, 9, 20, 0.1); border-left: 3px solid var(--accent); border-radius: 4px; }
        
        /* Plan Karşılaştırma */
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .plan-item { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .plan-item.is-current { border-color: var(--accent); background: rgba(229, 9, 20, 0.05); }
        .plan-price { font-size: 1.5rem; font-weight: bold; margin: 10px 0; }
        
        /* Ödeme Yöntemleri */
        .payment-list { display: flex; flex-direction: column; gap: 10px; }
        .payment-item { display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.4); padding: 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); }
        .payment-item-info { display: flex; align-items: center; gap: 10px; font-family: monospace; font-size: 1.1rem; }
        .btn-delete-card { background: none; border: none; color: #ff4444; cursor: pointer; padding: 5px; opacity: 0.7; transition: 0.3s; }
        .btn-delete-card:hover { opacity: 1; transform: scale(1.1); }
        
        @media (max-width: 768px) {
            .sub-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-stats { justify-content: center; }
            .grid-container {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 15px;
            }
            .settings-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="profile-page">
    <!-- Profil Başlığı -->
    <div class="profile-header">
        <label class="profile-avatar-wrapper">
            <div class="profile-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" id="avatarPreview">
                <?php else: ?>
                    <?= $initial ?>
                <?php endif; ?>
            </div>
            <div class="profile-avatar-overlay">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
            </div>
            <input type="file" id="inlineAvatarUpload" accept="image/*">
        </label>
        
        <div class="profile-details">
            <div class="username-container">
                <h1 id="usernameDisplay"><?= htmlspecialchars($user['username']) ?> 
                    <button type="button" class="edit-name-btn" id="editNameBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                </h1>
                <input type="text" id="usernameInput" class="edit-name-input" value="<?= htmlspecialchars($user['username']) ?>">
                <button type="button" class="save-name-btn" id="saveNameBtn">Kaydet</button>
            </div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="profile-stats">
                <span class="stat-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Üyelik: <span class="stat-value"><?= $memberSince ?></span>
                </span>
                <span class="stat-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Yorum: <span class="stat-value"><?= $commentCount ?></span>
                </span>
                <?php if ($user['is_premium']): ?>
                    <span class="premium-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        PREMIUM
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Abonelik ve Ödeme Yönetimi (Accordion) -->
    <div class="subscription-accordion">
        <div class="accordion-header" id="subAccordionHeader">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                Abonelik ve Hesap Ayarları
            </h2>
            <svg id="subAccordionIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition: transform 0.3s;"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </div>
        <div class="accordion-content" id="subAccordionContent">
            <div class="sub-grid">
                <!-- Sol Taraf: Abonelik Detayı -->
                <div class="sub-card">
                    <h3>Mevcut Abonelik</h3>
                    <?php if($activeSub): ?>
                        <div class="active-plan-badge"><?= htmlspecialchars($activeSub['plan_name']) ?></div>
                        <p style="color: var(--text-secondary);">Fatura Kesim Tarihi: <strong style="color: white;"><?= date('d.m.Y', strtotime($activeSub['end_date'])) ?></strong></p>
                        
                        <?php if($activeSub['plan_id'] < 4): // 4: En yüksek plan varsayımı ?>
                            <div class="upgrade-alert">
                                <strong>Deneyiminizi Yükseltin!</strong><br>
                                <span style="font-size: 0.9rem; color: var(--text-secondary);">4K kalite ve daha fazla ekran için paketinizi yükseltebilirsiniz.</span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Aktif bir aboneliğiniz bulunmuyor.</p>
                        <a href="checkout.php" class="btn-update" style="display:inline-block; margin-top:10px; text-decoration:none;">Abonelik Başlat</a>
                    <?php endif; ?>
                </div>

                <!-- Sağ Taraf: Ödeme Yöntemleri -->
                <div class="sub-card">
                    <h3>Kayıtlı Kartlarım</h3>
                    <?php if(empty($paymentMethods)): ?>
                        <p style="color: var(--text-secondary);">Kayıtlı ödeme yönteminiz yok.</p>
                    <?php else: ?>
                        <div class="payment-list">
                            <?php foreach($paymentMethods as $card): ?>
                                <div class="payment-item" id="card-<?= $card['id'] ?>">
                                    <div class="payment-item-info">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                        **** **** **** <?= htmlspecialchars($card['card_number_last4']) ?>
                                    </div>
                                    <button class="btn-delete-card" onclick="deleteCard(<?= $card['id'] ?>)" title="Kartı Sil">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button class="btn-update" style="margin-top: 15px; width: 100%;" onclick="alert('Yeni kart ekleme modülü henüz yapım aşamasında.')">+ Yeni Kart Ekle</button>
                </div>
            </div>

            <!-- Alt Kısım: Tüm Paketler -->
            <div class="sub-card" style="margin-bottom: 20px;">
                <h3>Tüm Paket Seçenekleri</h3>
                <div class="plans-grid">
                    <?php foreach($allPlans as $plan): ?>
                        <div class="plan-item <?= ($activeSub && $activeSub['plan_id'] == $plan['id']) ? 'is-current' : '' ?>">
                            <h4 style="margin: 0 0 10px 0; color: <?= ($activeSub && $activeSub['plan_id'] == $plan['id']) ? 'var(--accent)' : 'white' ?>;"><?= htmlspecialchars($plan['name']) ?></h4>
                            <div class="plan-price"><?= number_format($plan['price_monthly'], 2) ?> ₺<span style="font-size:0.8rem; color:var(--text-secondary);">/ay</span></div>
                            <p style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($plan['description']) ?></p>
                            <p style="font-size: 0.85rem; font-weight: bold; margin-top: 10px;"><?= htmlspecialchars($plan['resolution']) ?> • <?= $plan['max_screens'] ?> Ekran</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MangaBuff Tema Seçim Menüsü -->
    <h2 class="section-title">Profil Teması Seçimi</h2>
    <div class="theme-selector-grid">
        <!-- Özel Tema Ekleme Kartı -->
        <label class="theme-option-card add-theme-card" title="Bilgisayardan kendi arkaplanını yükle">
            <div class="add-theme-icon">+</div>
            <div class="theme-name">Özel Yükle</div>
            <input type="file" id="inlineCustomBgUpload" accept="image/*" style="display:none;">
        </label>

        <div class="theme-option-card active" data-theme="default" data-bg="">
            <div class="theme-preview" style="background: linear-gradient(135deg, #141414, #0a0a0a); border: 2px solid #555;"></div>
            <div class="theme-name">Varsayılan Koyu</div>
        </div>
        <div class="theme-option-card" data-theme="marvel" data-bg="https://image.tmdb.org/t/p/original/9BBTo63ANSmhC4e6r62OJFuK2GL.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/9BBTo63ANSmhC4e6r62OJFuK2GL.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Marvel Avengers</div>
        </div>
        <div class="theme-option-card" data-theme="rickmorty" data-bg="https://image.tmdb.org/t/p/original/zJZfxi8X3XPHAhxXseRugtnNVtt.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/zJZfxi8X3XPHAhxXseRugtnNVtt.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Rick and Morty</div>
        </div>
        <div class="theme-option-card" data-theme="naruto" data-bg="https://image.tmdb.org/t/p/original/mpsYIytXhDXjI9yYC1Fp1S3PxsS.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/mpsYIytXhDXjI9yYC1Fp1S3PxsS.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Naruto Shippuden</div>
        </div>
        <div class="theme-option-card" data-theme="cyberpunk" data-bg="https://image.tmdb.org/t/p/original/bRE6zX4iOAejLOQCHryoV5WNu8G.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/bRE6zX4iOAejLOQCHryoV5WNu8G.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Cyberpunk</div>
        </div>
        <div class="theme-option-card" data-theme="strangerthings" data-bg="https://image.tmdb.org/t/p/original/56v2KjBlU4XaOv9rVYEQypROD7P.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/56v2KjBlU4XaOv9rVYEQypROD7P.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Stranger Things</div>
        </div>
        <div class="theme-option-card" data-theme="got" data-bg="https://image.tmdb.org/t/p/original/suopoADq0k8YZr4dQXcU6pToj6s.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/suopoADq0k8YZr4dQXcU6pToj6s.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Game of Thrones</div>
        </div>
        <div class="theme-option-card" data-theme="aot" data-bg="https://image.tmdb.org/t/p/original/rqbCbjB19amtOtFQbb3K2lgm2zv.jpg">
            <div class="theme-preview" style="background-image: url('https://image.tmdb.org/t/p/w300/rqbCbjB19amtOtFQbb3K2lgm2zv.jpg'); background-size: cover; background-position: center;"></div>
            <div class="theme-name">Attack on Titan</div>
        </div>
    </div>

    <!-- Favorilerim -->
    <h2 class="section-title">Favorilerim</h2>
    <?php if (empty($favorites)): ?>
        <div class="empty-state">
            <p>Favori listeniz henüz boş. Sevdiğiniz içerikleri favorilere ekleyin!</p>
        </div>
    <?php else: ?>
        <div class="grid-container">
            <?php foreach ($favorites as $item): 
                $posterUrl = !empty($item['poster_url']) ? htmlspecialchars($item['poster_url']) : '';
            ?>
                <div class="movie-card" data-type="<?= $item['content_type'] ?>" data-id="<?= $item['content_id'] ?>" onmouseenter="startHoverPreview(this, '<?= htmlspecialchars($item['slug']) ?>')" onmouseleave="stopHoverPreview(this)">
                    <a href="details.php?type=<?= $item['content_type'] ?>&id=<?= $item['content_id'] ?>" class="card-link">
                        <div class="card-poster" style="aspect-ratio: 2/3;">
                            <?php if ($posterUrl): ?>
                                <img src="<?= $posterUrl ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-poster-img" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                            <?php else: ?>
                                <div class="card-poster-placeholder">
                                    <span class="poster-letter"><?= mb_substr($item['title'], 0, 1, 'UTF-8') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="card-overlay">
                                <div class="card-play-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </div>
                            </div>
                            <div class="hover-video-container"></div>
                        </div>
                        <div class="card-info">
                            <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                            <div class="card-meta">
                                <span class="card-score">★ <?= number_format($item['imdb_score'], 1) ?></span>
                                <span class="card-year"><?= $item['release_year'] ?></span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- İzleme Listesi -->
    <h2 class="section-title">İzleme Listem</h2>
    <?php if (empty($watchlist)): ?>
        <div class="empty-state">
            <p>İzleme listeniz henüz boş. Daha sonra izlemek istediğiniz içerikleri ekleyin!</p>
        </div>
    <?php else: ?>
        <div class="grid-container">
            <?php foreach ($watchlist as $item): 
                $posterUrl = !empty($item['poster_url']) ? htmlspecialchars($item['poster_url']) : '';
            ?>
                <div class="movie-card" data-type="<?= $item['content_type'] ?>" data-id="<?= $item['content_id'] ?>" onmouseenter="startHoverPreview(this, '<?= htmlspecialchars($item['slug']) ?>')" onmouseleave="stopHoverPreview(this)">
                    <a href="details.php?type=<?= $item['content_type'] ?>&id=<?= $item['content_id'] ?>" class="card-link">
                        <div class="card-poster" style="aspect-ratio: 2/3;">
                            <?php if ($posterUrl): ?>
                                <img src="<?= $posterUrl ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-poster-img" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                            <?php else: ?>
                                <div class="card-poster-placeholder">
                                    <span class="poster-letter"><?= mb_substr($item['title'], 0, 1, 'UTF-8') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="card-overlay">
                                <div class="card-play-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </div>
                            </div>
                            <div class="hover-video-container"></div>
                        </div>
                        <div class="card-info">
                            <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                            <div class="card-meta">
                                <span class="card-score">★ <?= number_format($item['imdb_score'], 1) ?></span>
                                <span class="card-year"><?= $item['release_year'] ?></span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Son Yorumlar -->
    <h2 class="section-title">Son Yorumlarım</h2>
    <?php if (empty($recentComments)): ?>
        <div class="empty-state">
            <p>Henüz yorum yapmadınız. Film ve dizileri keşfedip ilk yorumunuzu yapın!</p>
        </div>
    <?php else: ?>
        <?php foreach ($recentComments as $comment): ?>
            <div class="comment-card">
                <div class="comment-card-header">
                    <a href="details.php?type=<?= $comment['content_type'] ?>&id=<?= $comment['content_id'] ?>" class="comment-content-title">
                        <?= htmlspecialchars($comment['content_title'] ?? 'Bilinmeyen İçerik') ?>
                    </a>
                    <span class="comment-card-rating">★ <?= $comment['rating'] ?>/10</span>
                </div>
                <div class="comment-card-text"><?= htmlspecialchars($comment['comment_text']) ?></div>
                <div class="comment-card-date"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- HOVER VE TEMA JS -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Tema Ayarlama
        const savedTheme = localStorage.getItem('profile-theme') || 'default';
        const savedBg = localStorage.getItem('profile-theme-bg') || '';
        applyTheme(savedTheme, savedBg);

        document.querySelectorAll('.theme-option-card').forEach(card => {
            const theme = card.dataset.theme;
            const bg = card.dataset.bg;
            
            if (theme === savedTheme) {
                document.querySelectorAll('.theme-option-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            }

            card.addEventListener('click', () => {
                document.querySelectorAll('.theme-option-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                localStorage.setItem('profile-theme', theme);
                localStorage.setItem('profile-theme-bg', bg);
                applyTheme(theme, bg);

                // Save to DB
                const fd = new FormData();
                fd.append('action', 'update_theme');
                fd.append('theme', theme);
                fd.append('theme_bg', bg);
                fetch('profile_action.php', { method: 'POST', body: fd });
            });
        });
        
        // Profile update AJAX
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'update_profile');
            fetch('profile_action.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                const rDiv = document.getElementById('profileRes');
                rDiv.style.color = res.success ? 'var(--success)' : 'var(--error)';
                rDiv.innerText = res.message;
                if(res.success && res.avatarUrl) {
                    location.reload();
                }
            });
        });

        // Add Card AJAX
        document.getElementById('addCardForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'add_card');
            fetch('profile_action.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                const rDiv = document.getElementById('cardRes');
                rDiv.style.color = res.success ? 'var(--success)' : 'var(--error)';
                rDiv.innerText = res.message;
                if(res.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            });
        });
    });

    function applyTheme(theme, bg) {
        const body = document.body;
        body.classList.remove('theme-marvel', 'theme-rickmorty', 'theme-naruto');
        
        let overlay = document.getElementById('profile-bg-overlay');
        if (overlay) overlay.remove();

        if (theme !== 'default' && bg) {
            body.classList.add('theme-' + theme);
            
            const bgDiv = document.createElement('div');
            bgDiv.id = 'profile-bg-overlay';
            bgDiv.style.position = 'fixed';
            bgDiv.style.top = '0';
            bgDiv.style.left = '0';
            bgDiv.style.width = '100vw';
            bgDiv.style.height = '100vh';
            bgDiv.style.backgroundImage = `url('${bg}')`;
            bgDiv.style.backgroundSize = 'cover';
            bgDiv.style.backgroundPosition = 'center top';
            bgDiv.style.opacity = '0.12';
            bgDiv.style.zIndex = '-2';
            bgDiv.style.pointerEvents = 'none';
            bgDiv.style.transition = 'background-image 0.5s ease';
            
            body.appendChild(bgDiv);
        }
    }

    // Inline Avatar Yükleme
    const inlineAvatarUpload = document.getElementById('inlineAvatarUpload');
    if(inlineAvatarUpload) {
        inlineAvatarUpload.addEventListener('change', function() {
            if(this.files && this.files[0]) {
                const formData = new FormData();
                formData.append('action', 'update_profile');
                formData.append('username', document.getElementById('usernameDisplay').textContent.trim());
                formData.append('avatar', this.files[0]);
                
                fetch('profile_action.php', { method: 'POST', body: formData })
                .then(r => r.json()).then(res => {
                    if(res.success) location.reload();
                    else alert(res.message);
                });
            }
        });
    }

    // Inline Username Değiştirme
    const editNameBtn = document.getElementById('editNameBtn');
    const saveNameBtn = document.getElementById('saveNameBtn');
    const usernameDisplay = document.getElementById('usernameDisplay');
    const usernameInput = document.getElementById('usernameInput');

    if(editNameBtn) {
        editNameBtn.addEventListener('click', () => {
            usernameDisplay.style.display = 'none';
            usernameInput.style.display = 'block';
            saveNameBtn.style.display = 'block';
            usernameInput.focus();
        });
    }

    if(saveNameBtn) {
        saveNameBtn.addEventListener('click', () => {
            const newName = usernameInput.value.trim();
            if(!newName) return;
            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('username', newName);
            
            fetch('profile_action.php', { method: 'POST', body: formData })
            .then(r => r.json()).then(res => {
                if(res.success) {
                    usernameDisplay.childNodes[0].nodeValue = newName + " ";
                    usernameDisplay.style.display = 'flex';
                    usernameInput.style.display = 'none';
                    saveNameBtn.style.display = 'none';
                } else alert(res.message);
            });
        });
    }

    // Inline Custom BG Yükleme (Tema Izgarasındaki Yeni Kart)
    const inlineCustomBgUpload = document.getElementById('inlineCustomBgUpload');
    if(inlineCustomBgUpload) {
        inlineCustomBgUpload.addEventListener('change', function() {
            if(this.files && this.files[0]) {
                const formData = new FormData();
                formData.append('action', 'upload_custom_bg');
                formData.append('custom_bg', this.files[0]);
                
                fetch('profile_action.php', { method: 'POST', body: formData })
                .then(r => r.json()).then(res => {
                    if(res.success) {
                        localStorage.setItem('profile-theme', 'custom');
                        localStorage.setItem('profile-theme-bg', res.bgUrl);
                        applyTheme('custom', res.bgUrl);
                        document.querySelectorAll('.theme-option-card').forEach(c => c.classList.remove('active'));
                        this.closest('.theme-option-card').classList.add('active');
                    } else alert(res.message);
                });
            }
        });
    }

    // Hover Video Preview
    let hoverTimer = null;
    function startHoverPreview(card, slug) {
        if (card.dataset.hoverTimer) clearTimeout(parseInt(card.dataset.hoverTimer));
        if (window.innerWidth < 768) return;
        const videoContainer = card.querySelector('.hover-video-container');
        if (!videoContainer) return;

        const timer = setTimeout(() => {
            if (!videoContainer.querySelector('video')) {
                const video = document.createElement('video');
                const img = card.querySelector('.hover-img');
                if(img) video.poster = img.src;

                video.autoplay = true; video.muted = true; video.loop = true;
                video.classList.add('hover-video');

                const sourceWebm = document.createElement('source');
                sourceWebm.src = 'videos/' + encodeURIComponent(slug) + '.webm';
                sourceWebm.type = 'video/webm';
                video.appendChild(sourceWebm);

                const sourceMp4 = document.createElement('source');
                sourceMp4.src = 'videos/' + encodeURIComponent(slug) + '.mp4';
                sourceMp4.type = 'video/mp4';
                video.appendChild(sourceMp4);

                video.oncanplay = () => { video.style.opacity = '1'; };
                videoContainer.appendChild(video);
            } else {
                const existingVideo = videoContainer.querySelector('video');
                existingVideo.style.display = 'block';
                existingVideo.play().catch(e => console.log(e));
                existingVideo.style.opacity = '1';
            }
        }, 800);
        card.dataset.hoverTimer = timer;
    }
    
    function stopHoverPreview(card) {
        if (card.dataset.hoverTimer) {
            clearTimeout(parseInt(card.dataset.hoverTimer));
            card.removeAttribute('data-hoverTimer');
        }
        const videoContainer = card.querySelector('.hover-video-container');
        if (!videoContainer) return;
        const video = videoContainer.querySelector('video');
        if (video) {
            video.style.opacity = '0';
            setTimeout(() => { video.pause(); video.currentTime = 0; video.style.display = 'none'; }, 300);
        }
    }

    // Abonelik Accordion
    const subAccordionHeader = document.getElementById('subAccordionHeader');
    const subAccordionContent = document.getElementById('subAccordionContent');
    const subAccordionIcon = document.getElementById('subAccordionIcon');
    if(subAccordionHeader) {
        subAccordionHeader.addEventListener('click', () => {
            subAccordionContent.classList.toggle('open');
            if(subAccordionContent.classList.contains('open')) {
                subAccordionIcon.style.transform = 'rotate(180deg)';
            } else {
                subAccordionIcon.style.transform = 'rotate(0deg)';
            }
        });
    }

    // Kart Silme (AJAX)
    function deleteCard(cardId) {
        if(!confirm('Bu kartı silmek istediğinize emin misiniz?')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_card');
        formData.append('card_id', cardId);
        
        fetch('profile_action.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(res => {
            if(res.success) {
                const cardEl = document.getElementById('card-' + cardId);
                if(cardEl) {
                    cardEl.style.transition = '0.3s';
                    cardEl.style.opacity = '0';
                    cardEl.style.transform = 'translateX(20px)';
                    setTimeout(() => cardEl.remove(), 300);
                }
            } else alert(res.message);
        }).catch(err => {
            console.error(err);
            alert('Sunucu hatası.');
        });
    }
</script>

</body>
</html>
