<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!validateCsrfToken()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $terms    = isset($_POST['terms']);

        // Doğrulama
        if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'Lütfen tüm alanları doldurun.';
        } elseif (mb_strlen($username) < 3) {
            $error = 'Kullanıcı adı en az 3 karakter olmalıdır.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Kullanıcı adı sadece harf, rakam ve alt çizgi içerebilir.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta adresi girin.';
        } elseif (mb_strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($password !== $confirm) {
            $error = 'Şifreler eşleşmiyor.';
        } elseif (!$terms) {
            $error = 'Kullanım koşullarını kabul etmelisiniz.';
        } else {
            $result = registerUser($pdo, $username, $email, $password);
            if ($result['success']) {
                $success = $result['message'];
                // Başarılı kayıt sonrası form alanlarını temizle
                $username = $email = '';
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CineGX - Premium VOD platformuna kayıt ol ve binlerce içeriğe erişim sağla.">
    <title>Kayıt Ol — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="logo">
                <h1>Cine<span>GX</span></h1>
            </div>

            <h2>Kayıt Ol</h2>

            <?php if ($error): ?>
                <div class="alert alert-error" id="alertError">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" id="alertSuccess">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" novalidate>
                <?= csrfField() ?>

                <div class="form-group">
                    <input type="text" id="username" name="username" placeholder=" "
                           value="<?= htmlspecialchars($username ?? '') ?>" required minlength="3" autocomplete="username">
                    <label for="username">Kullanıcı Adı</label>
                </div>

                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder=" "
                           value="<?= htmlspecialchars($email ?? '') ?>" required autocomplete="email">
                    <label for="email">E-posta Adresi</label>
                </div>

                <div class="form-group">
                    <input type="password" id="password" name="password" placeholder=" " required minlength="6" autocomplete="new-password">
                    <label for="password">Şifre</label>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)">göster</button>
                </div>

                <!-- Şifre Güç Göstergesi -->
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bars">
                        <div class="strength-bar" id="bar1"></div>
                        <div class="strength-bar" id="bar2"></div>
                        <div class="strength-bar" id="bar3"></div>
                        <div class="strength-bar" id="bar4"></div>
                    </div>
                    <span class="strength-text" id="strengthText"></span>
                </div>

                <div class="form-group">
                    <input type="password" id="password_confirm" name="password_confirm" placeholder=" " required autocomplete="new-password">
                    <label for="password_confirm">Şifre Tekrar</label>
                    <button type="button" class="password-toggle" onclick="togglePassword('password_confirm', this)">göster</button>
                </div>

                <!-- Kullanım Koşulları -->
                <div class="terms-row">
                    <label>
                        <input type="checkbox" name="terms" id="termsCheckbox" required>
                        <span><a href="page.php?p=kullanim" target="_blank" class="terms-link">Kullanım Koşulları</a>'nı ve <a href="page.php?p=gizlilik" target="_blank" class="terms-link">Gizlilik Politikası</a>'nı okudum ve kabul ediyorum.</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="registerBtn">Hesap Oluştur</button>
            </form>

            <div class="divider">
                <span>veya</span>
            </div>

            <div class="auth-links">
                Zaten hesabınız var mı? <a href="login.php">Giriş Yap</a>
            </div>

            <div class="auth-footer">
                <p>Bu sayfa Google reCAPTCHA ile korunmaktadır.</p>
            </div>
        </div>
    </div>

    <script>
        // Şifre Göster/Gizle
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                btn.textContent = 'gizle';
            } else {
                field.type = 'password';
                btn.textContent = 'göster';
            }
        }

        // Şifre Güç Göstergesi
        const passwordInput = document.getElementById('password');
        const bars = [
            document.getElementById('bar1'),
            document.getElementById('bar2'),
            document.getElementById('bar3'),
            document.getElementById('bar4')
        ];
        const strengthText = document.getElementById('strengthText');
        const strengthContainer = document.getElementById('passwordStrength');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            let score = 0;

            if (val.length === 0) {
                strengthContainer.style.opacity = '0';
                return;
            }

            strengthContainer.style.opacity = '1';

            if (val.length >= 6) score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            // 0-5 arası skoru 1-4 bara eşle
            let level = 0;
            if (score >= 5) level = 4;
            else if (score >= 4) level = 3;
            else if (score >= 3) level = 2;
            else if (score >= 1) level = 1;

            const levels = [
                { text: 'Çok Zayıf', color: '#e50914' },
                { text: 'Zayıf', color: '#e87c03' },
                { text: 'Orta', color: '#e5c009' },
                { text: 'Güçlü', color: '#46d369' },
            ];

            bars.forEach((bar, i) => {
                if (i < level) {
                    bar.style.background = levels[level - 1].color;
                    bar.style.opacity = '1';
                } else {
                    bar.style.background = '#333';
                    bar.style.opacity = '0.4';
                }
            });

            strengthText.textContent = levels[level > 0 ? level - 1 : 0].text;
            strengthText.style.color = levels[level > 0 ? level - 1 : 0].color;
        });

        // Şifre eşleşme kontrolü (gerçek zamanlı)
        const confirmInput = document.getElementById('password_confirm');
        confirmInput.addEventListener('input', function() {
            if (this.value && this.value !== passwordInput.value) {
                this.style.borderColor = '#e50914';
            } else if (this.value) {
                this.style.borderColor = '#46d369';
            } else {
                this.style.borderColor = 'transparent';
            }
        });
    </script>
</body>
</html>
