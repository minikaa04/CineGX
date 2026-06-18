<?php
/**
 * CineGX — Abonelik ve Ödeme Sayfası
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

$userId = currentUserId();
$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$success = false;
$error = '';

// Seçilen planı çek
$selectedPlan = null;
if ($planId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $selectedPlan = $stmt->fetch();
}

// Ödeme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    if (!validateCsrfToken()) {
        $error = 'CSRF Doğrulama hatası! Güvenlik nedeniyle işlem iptal edildi.';
    } else {
        $cc_name = trim($_POST['cc_name'] ?? '');
        $cc_number = trim($_POST['cc_number'] ?? '');
        $cc_expiry = trim($_POST['cc_expiry'] ?? '');
        $cc_cvc = trim($_POST['cc_cvc'] ?? '');
        
        if (empty($cc_name) || empty($cc_number) || empty($cc_expiry) || empty($cc_cvc) || $planId <= 0) {
            $error = 'Lütfen tüm ödeme alanlarını doldurun.';
        } else {
            // Ödeme başarılı simülasyonu
            try {
                $pdo->beginTransaction();
                
                // 1. Kullanıcının premium durumunu güncelle
                $stmt = $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
                $stmt->execute([$userId]);
                
                // 2. Abonelik oluştur
                $startDate = date('Y-m-d');
                $endDate = date('Y-m-d', strtotime('+1 month'));
                $stmtSub = $pdo->prepare("
                    INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmtSub->execute([$userId, $planId, $startDate, $endDate]);
                
                $pdo->commit();
                
                // 3. Oturum değişkenini güncelle
                $_SESSION['is_premium'] = true;
                $success = true;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Abonelik işlemi sırasında bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Tüm planları çek (İlk aşama)
$plans = $pdo->query("SELECT * FROM plans ORDER BY price_monthly ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonelik Planları — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .checkout-page { padding: 120px 4% 60px; max-width: 1000px; margin: 0 auto; }
        .page-title { text-align: center; font-size: 2.2rem; font-weight: 800; margin-bottom: 10px; }
        .page-subtitle { text-align: center; color: var(--text-muted); margin-bottom: 40px; }
        
        /* Plan Seçim Grid */
        .plans-grid {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
        }
        .plan-card {
            background: linear-gradient(180deg, rgba(30,30,30,0.8) 0%, rgba(15,15,15,0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: left;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            position: relative;
            width: 320px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .plan-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 5px;
            background: rgba(255,255,255,0.1);
            transition: 0.4s;
        }
        .plan-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: rgba(229, 9, 20, 0.5);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.2);
            z-index: 2;
        }
        .plan-card:hover::before {
            background: var(--accent);
        }
        .plan-card.popular {
            border-color: var(--accent);
            transform: scale(1.05);
            z-index: 1;
        }
        .plan-card.popular::before {
            background: var(--accent);
        }
        .plan-card.popular:hover {
            transform: translateY(-10px) scale(1.08);
        }
        .plan-badge {
            position: absolute; top: 20px; right: 20px;
            background: linear-gradient(135deg, #e50914, #b20710); color: white; padding: 5px 15px;
            font-size: 0.8rem; font-weight: 800; border-radius: 20px; text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(229,9,20,0.4);
        }
        .plan-name { font-size: 1.6rem; font-weight: 800; margin-bottom: 10px; color: white; }
        .plan-price { font-size: 2.5rem; font-weight: 900; color: white; margin-bottom: 30px; display: flex; align-items: baseline; gap: 5px; }
        .plan-price span { font-size: 1rem; color: var(--text-muted); font-weight: normal; }
        .plan-features { list-style: none; padding: 0; margin: 0 0 40px 0; flex-grow: 1; }
        .plan-features li { margin-bottom: 15px; font-size: 0.95rem; color: #ddd; display: flex; align-items: flex-start; gap: 12px; line-height: 1.4; }
        .plan-features li svg { color: var(--accent); flex-shrink: 0; margin-top: 2px; }
        .btn-select-plan {
            background: rgba(255,255,255,0.1); color: white; border: none; padding: 15px;
            border-radius: 8px; font-weight: 800; font-size: 1.1rem; text-decoration: none; text-align: center;
            transition: all 0.3s ease;
        }
        .plan-card:hover .btn-select-plan, .plan-card.popular .btn-select-plan {
            background: var(--accent);
            box-shadow: 0 5px 15px rgba(229,9,20,0.3);
        }
        .btn-select-plan:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }
        
        /* Ödeme Modülü */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px; padding: 40px;
        }
        .checkout-details h2 { font-size: 1.6rem; margin-bottom: 15px; }
        .checkout-details p { color: var(--text-secondary); line-height: 1.5; margin-bottom: 25px; }
        .order-summary-card {
            background: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .summary-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 0.95rem; }
        .summary-row.total { border-top: 1px solid rgba(255, 255, 255, 0.1); margin-top: 10px; font-weight: 700; font-size: 1.1rem; }
        
        .checkout-form h2 { font-size: 1.4rem; margin-bottom: 20px; }
        .form-row { display: flex; gap: 15px; }
        .form-group-custom { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; width: 100%; }
        .form-group-custom label { font-size: 0.85rem; color: var(--text-secondary); font-weight: 600; }
        .form-group-custom input {
            background: var(--bg-input); border: 1px solid var(--border);
            padding: 12px; border-radius: 6px; color: white; font-family: inherit; font-size: 0.95rem;
        }
        .form-group-custom input:focus { border-color: var(--accent); outline: none; }
        .btn-pay {
            width: 100%; background: var(--accent); color: white; border: none;
            padding: 14px; border-radius: 6px; font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: 0.2s; margin-top: 15px;
        }
        .btn-pay:hover { background: var(--accent-hover); }

        .success-box {
            background: rgba(70, 211, 105, 0.04);
            border: 1px solid rgba(70, 211, 105, 0.2);
            border-radius: 16px; padding: 50px; text-align: center; max-width: 500px; margin: 0 auto;
        }
        .success-icon {
            width: 80px; height: 80px; border-radius: 50%; background: rgba(70, 211, 105, 0.1);
            color: var(--success); display: flex; align-items: center; justify-content: center;
            font-size: 3rem; margin: 0 auto 20px;
        }
        .success-box h2 { font-size: 1.8rem; margin-bottom: 15px; }
        .success-box p { color: var(--text-secondary); line-height: 1.6; margin-bottom: 30px; }
        .btn-home {
            background: var(--accent); color: white; padding: 12px 30px;
            border-radius: 6px; font-weight: 700; text-decoration: none; display: inline-block;
        }
        .btn-home:hover { background: var(--accent-hover); }

        @media (max-width: 800px) {
            .checkout-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="checkout-page">
    
    <?php if ($success): ?>
        <!-- Ödeme Başarılı -->
        <div class="success-box">
            <div class="success-icon">✓</div>
            <h2>Ödeme Başarılı!</h2>
            <p>Üyeliğiniz başarıyla Premium statüsüne yükseltildi. Artık tüm film ve dizileri sınırsız ve 4K kalitesinde izleyebilirsiniz.</p>
            <a href="index.php" class="btn-home">İzlemeye Başla</a>
        </div>
        
    <?php elseif ($selectedPlan): ?>
        <!-- Ödeme Yapma Aşaması -->
        <h1 class="page-title">Güvenli Ödeme</h1>
        <p class="page-subtitle">Premium üyeliğinizi hemen tamamlayın.</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="background: rgba(229, 9, 20, 0.1); border: 1px solid rgba(229, 9, 20, 0.3); color: var(--error); padding: 15px; border-radius: 6px; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <div class="checkout-container">
            <div class="checkout-details">
                <h2>Seçilen Plan Detayları</h2>
                <p>İstediğiniz zaman iptal edebilir veya planınızı değiştirebilirsiniz.</p>
                
                <div class="order-summary-card">
                    <div class="summary-row">
                        <span>Paket Adı:</span>
                        <strong><?= htmlspecialchars($selectedPlan['name']) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Maksimum Ekran:</span>
                        <span><?= $selectedPlan['max_screens'] ?> Ekran</span>
                    </div>
                    <div class="summary-row">
                        <span>Görüntü Kalitesi:</span>
                        <span><?= htmlspecialchars($selectedPlan['resolution']) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Aylık Tutar:</span>
                        <span><?= number_format($selectedPlan['price_monthly'], 2) ?> TL</span>
                    </div>
                </div>
            </div>
            
            <div class="checkout-form">
                <h2>Kredi / Banka Kartı Bilgileri</h2>
                <form action="checkout.php?plan_id=<?= $planId ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="pay">
                    
                    <div class="form-group-custom">
                        <label for="cc_name">Kart Sahibi Ad Soyad</label>
                        <input type="text" name="cc_name" id="cc_name" placeholder="John Doe" required>
                    </div>
                    
                    <div class="form-group-custom">
                        <label for="cc_number">Kart Numarası</label>
                        <input type="text" name="cc_number" id="cc_number" placeholder="4242 4242 4242 4242" maxlength="19" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group-custom">
                            <label for="cc_expiry">Son Kullanma (AA/YY)</label>
                            <input type="text" name="cc_expiry" id="cc_expiry" placeholder="12/28" maxlength="5" required>
                        </div>
                        
                        <div class="form-group-custom">
                            <label for="cc_cvc">CVC / CVV</label>
                            <input type="text" name="cc_cvc" id="cc_cvc" placeholder="321" maxlength="3" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-pay">Ödemeyi Tamamla</button>
                </form>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Plan Seçimi Aşaması -->
        <h1 class="page-title">Abonelik Planları</h1>
        <p class="page-subtitle">Kendiniz için en uygun planı seçip premium özelliklerin tadını çıkarın.</p>
        
        <div class="plans-grid">
            <?php foreach ($plans as $index => $plan): 
                // Determine if it's the premium/ultra plan to mark as popular
                $isPopular = (stripos($plan['name'], 'Premium') !== false || stripos($plan['name'], 'Ultra') !== false || $index === 1);
            ?>
                <div class="plan-card <?= $isPopular ? 'popular' : '' ?>">
                    <?php if ($isPopular): ?>
                        <div class="plan-badge">EN POPÜLER</div>
                    <?php endif; ?>
                    <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                    <div class="plan-price"><?= number_format($plan['price_monthly'], 2) ?> <span>TL / Ay</span></div>
                    <ul class="plan-features">
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><strong><?= htmlspecialchars($plan['resolution']) ?></strong> Çözünürlük ve HDR</span>
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Aynı Anda <strong><?= $plan['max_screens'] ?></strong> Cihazda İzleme</span>
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Sınırsız Film ve Dizi Erişimi</span>
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>İstediğin Zaman İptal Et</span>
                        </li>
                        <li style="color:#aaa; font-style:italic; margin-top:10px;">
                            <?= htmlspecialchars($plan['description']) ?>
                        </li>
                    </ul>
                    <a href="checkout.php?plan_id=<?= $plan['id'] ?>" class="btn-select-plan">Seç ve Devam Et</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
