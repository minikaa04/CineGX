<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!validateCsrfToken()) {
        $error = 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Lütfen e-posta ve şifrenizi girin.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta adresi girin.';
        } else {
            $result = loginUser($pdo, $email, $password);
            if ($result['success']) {
                header('Location: index.php');
                exit;
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
    <meta name="description" content="CineGX - Premium VOD platformuna giriş yapın.">
    <title>Giriş Yap — CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="logo">
                <h1>Cine<span>GX</span></h1>
            </div>

            <h2>Giriş Yap</h2>

            <?php if ($error): ?>
                <div class="alert alert-error" id="alertError">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>
                <?= csrfField() ?>

                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder=" "
                           value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
                    <label for="email">E-posta Adresi</label>
                </div>

                <div class="form-group">
                    <input type="password" id="password" name="password" placeholder=" " required autocomplete="current-password">
                    <label for="password">Şifre</label>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)">göster</button>
                </div>

                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember"> Beni hatırla
                    </label>
                    <button type="button" class="forgot-password" onclick="openForgotModal()">Şifremi Unuttum</button>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">Giriş Yap</button>
            </form>

            <div class="divider">
                <span>veya</span>
            </div>

            <div class="auth-links">
                Hesabınız yok mu? <a href="register.php">Hemen Kayıt Ol</a>
            </div>

            <div class="auth-footer">
                <p>Bu sayfa Google reCAPTCHA ile korunmaktadır.</p>
            </div>
        </div>
    </div>

    <!-- ŞİFREMİ UNUTTUM MODAL -->
    <div class="modal-overlay" id="forgotModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeForgotModal()">&times;</button>
            <h3>Şifreni mi Unuttun?</h3>
            <p>Kayıtlı e-posta adresini gir, şifre sıfırlama bağlantısını gönderelim.</p>

            <div id="forgotForm">
                <div class="form-group">
                    <input type="email" id="forgotEmail" placeholder=" " required>
                    <label for="forgotEmail">E-posta Adresi</label>
                </div>
                <button class="btn btn-primary" onclick="sendResetLink()">Sıfırlama Bağlantısı Gönder</button>
            </div>

            <div class="spinner" id="forgotSpinner"></div>
            <div class="modal-result" id="forgotResult"></div>
        </div>
    </div>

    <script>
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

        function openForgotModal() {
            document.getElementById('forgotModal').classList.add('active');
            document.getElementById('forgotForm').style.display = 'block';
            document.getElementById('forgotSpinner').classList.remove('active');
            document.getElementById('forgotResult').classList.remove('show');
            document.getElementById('forgotResult').style.display = 'none';
            document.getElementById('forgotEmail').value = '';
            setTimeout(() => document.getElementById('forgotEmail').focus(), 300);
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').classList.remove('active');
        }

        function sendResetLink() {
            const email = document.getElementById('forgotEmail').value.trim();
            if (!email || !email.includes('@')) {
                alert('Lütfen geçerli bir e-posta adresi girin.');
                return;
            }
            document.getElementById('forgotForm').style.display = 'none';
            const spinner = document.getElementById('forgotSpinner');
            spinner.classList.add('active');

            setTimeout(() => {
                spinner.classList.remove('active');
                const result = document.getElementById('forgotResult');
                result.textContent = '✓ Şifre sıfırlama bağlantısı ' + email + ' adresine gönderildi!';
                result.classList.add('show');
                result.style.display = 'block';
            }, 2500);
        }

        document.getElementById('forgotModal').addEventListener('click', function(e) {
            if (e.target === this) closeForgotModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeForgotModal();
        });
    </script>
</body>
</html>
