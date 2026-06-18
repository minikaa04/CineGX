<?php
/**
 * CineGX - Sabit Sayfalar (SSS, Gizlilik, Şartlar vb.)
 */
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

$p = isset($_GET['p']) ? $_GET['p'] : '404';

$pages = [
    'sss' => [
        'title' => 'Sıkça Sorulan Sorular (SSS)',
        'content' => '
            <div class="faq-item">
                <h3>1. CineGX nedir?</h3>
                <p>CineGX, binlerce film ve diziyi tek bir platformda sunan premium dijital sinema deneyimidir.</p>
            </div>
            <div class="faq-item">
                <h3>2. Aboneliğimi nasıl iptal edebilirim?</h3>
                <p>Profil ayarlarınızdan dilediğiniz zaman aboneliğinizi iptal edebilirsiniz. Taahhüt veya ekstra ücret yoktur.</p>
            </div>
            <div class="faq-item">
                <h3>3. Videoları indirebilir miyim?</h3>
                <p>Şu an için içeriklerimizi yalnızca çevrimiçi (online) olarak yüksek kalitede izleyebilirsiniz.</p>
            </div>'
    ],
    'yardim' => [
        'title' => 'Yardım Merkezi',
        'content' => '<p>Platformumuzla ilgili yaşadığınız her türlü teknik sorun, faturalandırma veya içerik oynatma problemleri için size destek olmaya hazırız.</p><p>Video oynatımında sorun yaşıyorsanız lütfen tarayıcınızı güncellemeyi veya çerezleri temizlemeyi deneyin.</p>'
    ],
    'iletisim' => [
        'title' => 'İletişim',
        'content' => '<p>Bizimle iletişime geçmek için aşağıdaki yolları kullanabilirsiniz:</p>
        <ul>
            <li><strong>E-posta:</strong> destek@cinegx.com</li>
            <li><strong>Telefon:</strong> 0850 123 45 67</li>
            <li><strong>Adres:</strong> CineGX Medya A.Ş., Teknoloji Vadisi, İstanbul</li>
        </ul>
        <p>Müşteri hizmetleri ekibimiz 7/24 hizmetinizdedir.</p>'
    ],
    'gizlilik' => [
        'title' => 'Gizlilik Politikası',
        'content' => '<p>CineGX olarak kişisel verilerinizin güvenliğine önem veriyoruz.</p>
        <p>1. Topladığımız Veriler: Hesap oluşturduğunuzda adınız, e-posta adresiniz ve ödeme bilgileriniz güvenli sunucularımızda saklanır.</p>
        <p>2. Veri Kullanımı: Bilgileriniz yalnızca size daha iyi bir izleme deneyimi sunmak ve hesap güvenliğinizi sağlamak amacıyla kullanılır.</p>
        <p>3. Üçüncü Taraflar: Verileriniz izniniz olmadan kesinlikle 3. şahıslarla veya reklam şirketleriyle paylaşılmaz.</p>'
    ],
    'kullanim' => [
        'title' => 'Kullanım Koşulları',
        'content' => '<p>CineGX platformuna üye olarak aşağıdaki şartları kabul etmiş sayılırsınız:</p>
        <p>1. Hizmetlerimiz yalnızca kişisel kullanım içindir, ticari amaçla kullanılamaz veya içerikler kopyalanıp çoğaltılamaz.</p>
        <p>2. Hesap bilgilerinizin güvenliği sizin sorumluluğunuzdadır. Şifrenizi kimseyle paylaşmayınız.</p>
        <p>3. CineGX, önceden haber vermeksizin platformdaki içerikleri kaldırma veya değiştirme hakkını saklı tutar.</p>'
    ],
    'cerez' => [
        'title' => 'Çerez (Cookie) Politikası',
        'content' => '<p>CineGX, sitemizi kullanımınızı analiz etmek ve size özel içerikler sunabilmek için çerezleri (cookies) kullanır.</p>
        <p>Sitemizi kullanarak çerez politikamızı kabul etmiş olursunuz. Çerezleri tarayıcı ayarlarınızdan dilediğiniz zaman devre dışı bırakabilirsiniz, ancak bu durum sitenin bazı fonksiyonlarının (örn: oturumun açık kalması) çalışmasını engelleyebilir.</p>'
    ]
];

$pageTitle = isset($pages[$p]) ? $pages[$p]['title'] : 'Sayfa Bulunamadı';
$pageContent = isset($pages[$p]) ? $pages[$p]['content'] : '<p>Aradığınız sayfa mevcut değil veya taşınmış olabilir.</p>';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CineGX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .static-page-container {
            max-width: 900px;
            margin: 100px auto 50px auto;
            padding: 40px;
            background: rgba(20,20,20,0.8);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            min-height: 50vh;
            color: #ddd;
        }
        .static-page-title {
            font-size: 2.5rem;
            color: #fff;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 15px;
            display: inline-block;
        }
        .static-page-content {
            font-size: 1.1rem;
            line-height: 1.8;
        }
        .static-page-content h3 {
            color: #fff;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .static-page-content p {
            margin-bottom: 20px;
        }
        .static-page-content ul {
            list-style-type: disc;
            margin-left: 20px;
            margin-bottom: 20px;
        }
        .static-page-content li {
            margin-bottom: 10px;
        }
        .faq-item {
            background: rgba(255,255,255,0.03);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .faq-item h3 {
            margin-top: 0;
            color: var(--accent);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="static-page-container">
    <h1 class="static-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
    <div class="static-page-content">
        <?= $pageContent ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
