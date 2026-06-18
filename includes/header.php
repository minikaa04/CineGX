<?php
/**
 * CineGX — Header / Navbar
 * Sabit (sticky) modern navbar: Logo, Navigasyon, Kullanıcı bilgisi, Çıkış
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userTheme = 'default';
$userThemeBg = '';
$userAvatar = '';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($pdo)) {
    $stmtH = $pdo->prepare("SELECT theme, theme_bg, avatar FROM users WHERE id = ?");
    $stmtH->execute([$_SESSION['user_id']]);
    $uData = $stmtH->fetch();
    if ($uData) {
        $userTheme = $uData['theme'] ?? 'default';
        $userThemeBg = $uData['theme_bg'] ?? '';
        $userAvatar = $uData['avatar'] ?? '';
    }
}
?>
<header class="navbar" id="mainNavbar">
    <div class="navbar-inner">
        <!-- Logo -->
        <a href="index.php" class="navbar-logo" id="navLogo">
            Cine<span>GX</span>
        </a>

        <!-- Navigation Links -->
        <nav class="navbar-nav" id="navMenu">
            <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" id="navHome">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Ana Sayfa
            </a>
            <a href="movies.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'movies.php' ? 'active' : '' ?>" id="navMovies">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/><line x1="17" y1="17" x2="22" y2="17"/></svg>
                Filmler
            </a>
            <a href="series.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'series.php' ? 'active' : '' ?>" id="navSeries">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>
                Diziler
            </a>
            <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>" id="navProfile">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profilim
            </a>
            <?php if (!isPremium()): ?>
                <a href="checkout.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'checkout.php' ? 'active' : '' ?>" id="navPremium" style="color: #FFD700 !important; font-weight: bold;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFD700" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Premium Üye Ol
                </a>
            <?php endif; ?>
        </nav>

        <!-- Search Bar -->
        <form action="search.php" method="GET" class="navbar-search-form" id="navbarSearchForm">
            <div class="search-input-wrapper">
                <input type="text" name="q" placeholder="Ara..." aria-label="Ara" required>
                <button type="submit" aria-label="Ara">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </div>
        </form>

        <!-- User Info & Logout -->
        <div class="navbar-user" id="navUser">
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                <div class="user-avatar" id="userAvatar">
                    <?php if (!empty($userAvatar)): ?>
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" style="width:100%; height:100%; border-radius:6px; object-fit:cover;">
                    <?php else: ?>
                        <?= strtoupper(mb_substr($_SESSION['username'] ?? 'U', 0, 1, 'UTF-8')) ?>
                    <?php endif; ?>
                </div>
                <span class="user-name" id="userName"><?= htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı') ?></span>
                <?php if (isAdmin()): ?>
                    <a href="admin/index.php" class="btn-logout" id="adminBtn" title="Admin Paneli" style="border-color: rgba(255,255,255,0.2);">
                        Panel
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout" id="logoutBtn" title="Çıkış Yap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Çıkış
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login" id="loginNavBtn">Giriş Yap</a>
            <?php endif; ?>
        </div>

        <!-- Mobile Hamburger -->
        <button class="navbar-toggle" id="navToggle" aria-label="Menüyü aç/kapat">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<script>
// Navbar scroll effect
let lastScroll = 0;
const navbar = document.getElementById('mainNavbar');
window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    if (currentScroll > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
    lastScroll = currentScroll;
});

// Mobile toggle
const navToggle = document.getElementById('navToggle');
const navMenu = document.getElementById('navMenu');
const navUser = document.getElementById('navUser');
if (navToggle) {
    navToggle.addEventListener('click', () => {
        navToggle.classList.toggle('active');
        navMenu.classList.toggle('open');
        navUser.classList.toggle('open');
    });
}

// Global Theme Apply
(function() {
    const dbTheme = '<?= htmlspecialchars($userTheme) ?>';
    const dbBg = '<?= htmlspecialchars($userThemeBg) ?>';
    let themeToApply = dbTheme;
    let bgToApply = dbBg;

    // LocalStorage fallback for non-logged in or instant sync
    const lsTheme = localStorage.getItem('profile-theme');
    const lsBg = localStorage.getItem('profile-theme-bg');

    // DB has priority if logged in, but we can sync LS
    if (dbTheme !== 'default' || dbBg !== '') {
        localStorage.setItem('profile-theme', dbTheme);
        localStorage.setItem('profile-theme-bg', dbBg);
    } else if (lsTheme) {
        themeToApply = lsTheme;
        bgToApply = lsBg;
    }

    if (themeToApply !== 'default' && bgToApply) {
        document.body.classList.add('theme-' + themeToApply);
        const bgDiv = document.createElement('div');
        bgDiv.id = 'profile-bg-overlay';
        bgDiv.style.position = 'fixed';
        bgDiv.style.top = '0';
        bgDiv.style.left = '0';
        bgDiv.style.width = '100vw';
        bgDiv.style.height = '100vh';
        bgDiv.style.backgroundImage = `url('${bgToApply}')`;
        bgDiv.style.backgroundSize = 'cover';
        bgDiv.style.backgroundPosition = 'center top';
        bgDiv.style.opacity = '0.12';
        bgDiv.style.zIndex = '-2';
        bgDiv.style.pointerEvents = 'none';
        document.body.appendChild(bgDiv);
    }
})();
</script>
