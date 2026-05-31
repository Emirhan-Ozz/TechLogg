<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['kullanici_id'])) { header("Location: login.php"); exit; }

if (!isset($_SESSION['unvan'])) {
    $stmt = $pdo->prepare("SELECT unvan FROM KULLANICI WHERE kullanici_id = ?");
    $stmt->execute([$_SESSION['kullanici_id']]);
    $userCheck = $stmt->fetch();
    $_SESSION['unvan'] = $userCheck ? $userCheck['unvan'] : 'Öğrenci';
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['islem']) && $_POST['islem'] == 'yeni_ariza') {
    if($pdo) {
        $cihaz_id = !empty($_POST['cihaz_id']) ? $_POST['cihaz_id'] : NULL;
        $manuel_cihaz = !empty($_POST['manuel_cihaz_adi']) ? trim($_POST['manuel_cihaz_adi']) : NULL;
        $stmt = $pdo->prepare("INSERT INTO ARIZA (kullanici_id, cihaz_id, manuel_cihaz_adi, baslik, aciklama, oncelik, durum) VALUES (?, ?, ?, ?, ?, ?, 'Bekliyor')");
        $stmt->execute([$_SESSION['kullanici_id'], $cihaz_id, $manuel_cihaz, trim($_POST['baslik']), trim($_POST['aciklama']), $_POST['oncelik']]);
    }
    header("Location: index.php?page=arizalar&success=1");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['islem']) && $_POST['islem'] == 'cihaz_ekle') {
    if($pdo) {
        $qr_id = "TECHLOG-QR-" . strtoupper(substr(md5(uniqid()), 0, 8));
        $cihaz_tipi = $_POST['cihaz_tipi'] ?? 'Diğer';
        $marka_model = $_POST['marka_model'] ?? 'Bilinmeyen Cihaz';
        
       
        $rand_suffix = strtoupper(substr(md5(uniqid()), 0, 4));
        $mac_adresi = !empty($_POST['mac']) ? $_POST['mac'] : 'GİRİLMEDİ-' . $rand_suffix;
        $sahiplik = $_POST['sahiplik'] ?? 'Sahsi';
        
        $isAuthorized = (isset($_SESSION['rol']) && $_SESSION['rol'] == 'Sistem Yöneticisi') || (isset($_SESSION['unvan']) && $_SESSION['unvan'] == 'Personel');
        if ($sahiplik == 'Kurum' && !$isAuthorized) {
            $sahiplik = 'Sahsi';
        }

        $bina = ($sahiplik == 'Kurum' && !empty($_POST['bina'])) ? trim($_POST['bina']) : NULL;
        $oda = ($sahiplik == 'Kurum' && !empty($_POST['oda'])) ? trim($_POST['oda']) : NULL;
        
        $stmt = $pdo->prepare("INSERT INTO KULLANICI_CIHAZLARI (kullanici_id, cihaz_tipi, marka_model, mac_adresi, qr_kod_id, sahiplik, bina, oda) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['kullanici_id'], $cihaz_tipi, $marka_model, $mac_adresi, $qr_id, $sahiplik, $bina, $oda]);
    }
    header("Location: index.php?page=cihazlar&success=device");
    exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $stmt = $pdo->prepare("DELETE FROM KULLANICI_CIHAZLARI WHERE cihaz_id = ? AND kullanici_id = ?");
    $stmt->execute([$sil_id, $_SESSION['kullanici_id']]);
    header("Location: index.php?page=cihazlar&msg=deleted");
    exit;
}

if (isset($_GET['coz'])) {
    $coz_id = (int)$_GET['coz'];
    $stmt = $pdo->prepare("UPDATE ARIZA SET durum = 'Çözüldü' WHERE ariza_id = ?");
    $stmt->execute([$coz_id]);
    header("Location: index.php?page=arizalar&msg=solved");
    exit;
}

$page = $_GET['page'] ?? 'dashboard';


$bekleyen_sayisi = 0;
$cozulen_sayisi = 0;
$uyari_sayisi = 0;

if($pdo) {

    $stmt = $pdo->query("SELECT COUNT(*) FROM ARIZA WHERE durum = 'Bekliyor'");
    $bekleyen_sayisi = $stmt->fetchColumn();


    $stmt = $pdo->query("SELECT COUNT(*) FROM ARIZA WHERE durum = 'Çözüldü'");
    $cozulen_sayisi = $stmt->fetchColumn();


    $stmt = $pdo->query("SELECT COUNT(*) FROM ARIZA WHERE oncelik = 'KRİTİK' AND durum = 'Bekliyor'");
    $uyari_sayisi = $stmt->fetchColumn();


    $onumdeki_kisi = 0;
    if ($_SESSION['rol'] != 'Sistem Yöneticisi') {
        $stmt = $pdo->prepare("SELECT ariza_id FROM ARIZA WHERE kullanici_id = ? AND durum = 'Bekliyor' ORDER BY ariza_id ASC LIMIT 1");
        $stmt->execute([$_SESSION['kullanici_id']]);
        $user_oldest = $stmt->fetchColumn();
        
        if ($user_oldest) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ARIZA WHERE durum = 'Bekliyor' AND ariza_id < ?");
            $stmt->execute([$user_oldest]);
            $onumdeki_kisi = $stmt->fetchColumn();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechLog - Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --bg: #0d0f14; 
            --sidebar-bg: rgba(18, 20, 29, 0.95); 
            --card-bg: rgba(255, 255, 255, 0.03); 
            --accent: #ff2d55; 
            --accent-glow: rgba(255, 45, 85, 0.4);
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            --transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); 
        }
        
        body {
            background: radial-gradient(circle at top right, #1e1b4b, #0d0f14); 
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            letter-spacing: -0.2px;
        }

        h1, h2, h3, h4, h5, h6, .fw-black { font-family: 'Outfit', sans-serif; letter-spacing: -1px; }
        select option { background: #12141d !important; color: #fff !important; }

        .traffic-lights { display: flex; gap: 8px; padding: 20px 0 0 25px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; }
        .dot-red { background: #ff5f56; }
        .dot-yellow { background: #ffbd2e; }
        .dot-green { background: #27c93f; }
          
        .sidebar { 
            width: 260px; 
            height: 100vh; 
            background: var(--sidebar-bg); 
            backdrop-filter: blur(40px);
            border-right: 1px solid rgba(255,255,255,0.08);
            display: flex; 
            flex-direction: column; 
            z-index: 1000; 
        }
        .sidebar-header { 
            padding: 30px 25px; 
            font-size: 1.4rem; 
            font-weight: 900; 
            letter-spacing: 3px; 
            color: #fff; 
            display: flex;
            align-items: center;
            text-shadow: 0 0 20px rgba(255,45,85,0.3);
        }
        
        .nav-link { 
            color: var(--text-dim); 
            padding: 14px 25px; 
            margin: 6px 15px; 
            font-weight: 600; 
            border-radius: 12px;
            transition: var(--transition); 
            display: flex; 
            align-items: center; 
            text-decoration: none;
        }
        .nav-link i { margin-right: 15px; width: 22px; font-size: 1.2rem; transition: var(--transition); }
        .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
            transform: translateX(5px);
        }
        .nav-link.active { 
            color: #fff; 
            background: rgba(255,45,85,0.08);
            border-left: 3px solid var(--accent);
            box-shadow: 10px 0 20px rgba(255,45,85,0.05);
        }
        .nav-link.active i { color: var(--accent); text-shadow: 0 0 15px var(--accent-glow); transform: scale(1.1); }

        
        .main-content { 
            flex: 1; 
            padding: 40px 60px; 
            overflow-y: auto; 
            position: relative;
            z-index: 1;
        }

        
        .glass-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(40px); 
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 24px; 
            padding: 30px; 
            transition: var(--transition); 
            position: relative;
            overflow: hidden;
        }
        .glass-card:hover { 
            transform: translateY(-5px); 
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        
        .stat-label { color: var(--text-dim); font-size: 0.75rem; font-weight: 800; letter-spacing: 1.5px; margin-bottom: 10px; }
        .stat-value { font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; line-height: 1; }

        
        .badge-status { 
            font-size: 0.65rem; font-weight: 800; padding: 6px 14px; border-radius: 30px; border: 1px solid currentColor; letter-spacing: 0.5px;
        }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { color: var(--text-dim); font-size: 0.7rem; font-weight: 800; letter-spacing: 1px; padding: 15px 20px; text-transform: uppercase; }
        .custom-table tbody tr { background: rgba(255,255,255,0.02); transition: var(--transition); }
        .custom-table tbody tr:hover { background: rgba(255,255,255,0.05); transform: scale(1.005); }
        .custom-table td { padding: 20px; border-top: 1px solid rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.03); }
        .custom-table td:first-child { border-left: 1px solid rgba(255,255,255,0.03); border-top-left-radius: 16px; border-bottom-left-radius: 16px; }
        .custom-table td:last-child { border-right: 1px solid rgba(255,255,255,0.03); border-top-right-radius: 16px; border-bottom-right-radius: 16px; }

        
        .chat-widget { 
            position: fixed; bottom: 110px; right: 30px; width: 380px; height: 550px; 
            background: rgba(18, 20, 29, 0.95); backdrop-filter: blur(50px); 
            border-radius: 30px; box-shadow: 0 25px 80px rgba(0,0,0,0.6); 
            display: flex; flex-direction: column; overflow: hidden; 
            transform: scale(0.8) translateY(50px); transform-origin: bottom right; 
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            z-index: 2000; opacity: 0; pointer-events: none; border: 1px solid rgba(255,255,255,0.1); 
        }
        .chat-widget.active { transform: scale(1) translateY(0); opacity: 1; pointer-events: auto; }
        
        .chat-header { 
            background: rgba(255, 255, 255, 0.05); padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .chat-header h6 { margin: 0; font-weight: 900; letter-spacing: 1px; color: #fff; }

        .chat-messages { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: transparent; }
        
        .msg-bubble { max-width: 85%; padding: 14px 20px; border-radius: 20px; font-size: 0.9rem; line-height: 1.5; animation: bubbleIn 0.4s ease; }
        @keyframes bubbleIn { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }

        .msg-user { background: var(--accent); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; box-shadow: 0 10px 20px var(--accent-glow); }
        .msg-bot { background: rgba(255,255,255,0.08); color: #fff; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid rgba(255,255,255,0.1); }
        
        .chat-input-container { padding: 20px; background: rgba(0,0,0,0.3); display: flex; gap: 12px; }
        .chat-input { 
            flex: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 15px; padding: 12px 20px; color: #fff; outline: none; 
        }
        .chat-input:focus { border-color: var(--accent); background: rgba(255,255,255,0.1); }
        
        .floating-chat-btn { 
            position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px; background: var(--accent); 
            color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-size: 1.6rem; cursor: pointer; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            box-shadow: 0 15px 35px var(--accent-glow); z-index: 1999;
        }
        .floating-chat-btn:hover { transform: scale(1.1) rotate(15deg); }

        
        .typing-indicator { display: none; align-self: flex-start; background: rgba(255,255,255,0.05); padding: 12px 18px; border-radius: 18px; border-bottom-left-radius: 4px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1); }
        .typing-indicator span { display: inline-block; width: 6px; height: 6px; background: var(--accent); border-radius: 50%; margin: 0 2px; animation: typingDots 1.4s infinite ease-in-out both; }
        
        .modal-content { 
            background: rgba(18, 20, 29, 0.98) !important; 
            backdrop-filter: blur(60px) !important;
            border: 1px solid rgba(255,255,255,0.15) !important;
            box-shadow: 0 40px 80px rgba(0,0,0,0.8) !important;
        }
        .modal-header { border-bottom: 1px solid rgba(255,255,255,0.08) !important; padding: 25px !important; }
        .modal-body { color: #fff !important; padding: 30px !important; }
        .modal-body label { color: var(--text-dim) !important; font-weight: 800; font-size: 0.7rem; letter-spacing: 1.5px; margin-bottom: 12px; display: block; }
        
        .modal-body .form-control, .modal-body .form-select, .wifi-input, .mac-format, input, select, textarea { 
            background: rgba(255,255,255,0.03) !important; 
            border: 1px solid rgba(255,255,255,0.1) !important; 
            border-radius: 14px !important;
            padding: 12px 18px !important;
            color: #ffffff !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        input::placeholder, textarea::placeholder { color: rgba(255,255,255,0.2) !important; }
        input:focus, select:focus, textarea:focus { 
            background: rgba(255,255,255,0.08) !important; 
            border-color: var(--accent) !important; 
            box-shadow: 0 0 20px var(--accent-glow) !important;
            outline: none !important;
        }
        select option { background: #12141d !important; color: #fff !important; }
        
        .btn-accent { background: var(--accent); color: #fff; border: none; border-radius: 12px; padding: 12px 24px; font-weight: 700; transition: 0.3s; box-shadow: 0 8px 20px var(--accent-glow); }
        .btn-accent:hover { transform: translateY(-3px); box-shadow: 0 12px 25px var(--accent-glow); filter: brightness(1.2); }
        
        .stat-card-link { transition: all 0.3s ease; cursor: pointer; }
        .stat-card-link:hover { transform: translateY(-5px) scale(1.02) !important; background: rgba(255,255,255,0.08) !important; border: 1px solid var(--accent) !important; }

        .password-display {
            font-family: 'Inter', monospace;
            font-size: 2rem;
            font-weight: 900;
            color: var(--accent);
            margin: 15px 0;
            filter: blur(10px);
            cursor: pointer;
            transition: all 0.5s ease;
            user-select: none;
            text-shadow: 0 0 15px var(--accent-glow);
            display: inline-block;
        }
        .password-display:hover { filter: blur(0); letter-spacing: 3px; }

        @keyframes typingDots { 0%, 80%, 100% { transform: scale(0); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-up { animation: fadeIn 0.8s cubic-bezier(0.2, 1, 0.2, 1); }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="traffic-lights">
        <div class="dot dot-red"></div>
        <div class="dot dot-yellow"></div>
        <div class="dot dot-green"></div>
    </div>
    <a href="index.php" class="text-decoration-none">
        <div class="sidebar-header"><i class="fa-solid fa-microchip text-accent me-2"></i>TECHLOG</div>
    </a>
    <div class="mt-4 flex-grow-1">
        <a href="?page=dashboard" class="nav-link <?= $page=='dashboard'?'active':'' ?>"><i class="fa-solid fa-house"></i> Ana Panel</a>
        <a href="?page=arizalar" class="nav-link <?= $page=='arizalar'?'active':'' ?>"><i class="fa-solid fa-screwdriver-wrench"></i> Arıza Kayıtları</a>
        
        <?php if($_SESSION['rol'] == 'Sistem Yöneticisi'): ?>
            <a href="?page=stok" class="nav-link <?= $page=='stok'?'active':'' ?>"><i class="fa-solid fa-boxes-stacked"></i> Kurumsal Envanter</a>
        <?php else: ?>
            <a href="?page=cihazlar" class="nav-link <?= $page=='cihazlar'?'active':'' ?>"><i class="fa-solid fa-laptop-code"></i> Cihazlarım</a>
        <?php endif; ?>

        <a href="?page=harita" class="nav-link <?= $page=='harita'?'active':'' ?>"><i class="fa-solid fa-map-location-dot"></i> Sistem Haritası</a>
        <a href="?page=wifi" class="nav-link <?= $page=='wifi'?'active':'' ?>"><i class="fa-solid fa-wifi"></i> WiFi Portalı</a>
    </div>
    <div class="user-profile mb-3">
        <div class="d-flex align-items-center px-4">
            <div class="bg-accent rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3" style="width:42px; height:42px; box-shadow: 0 0 20px var(--accent-glow); font-size: 1.1rem;">U</div>
            <div class="overflow-hidden">
                <h6 class="mb-0 text-white fw-bold text-truncate" style="font-size: 0.95rem;"><?= $_SESSION['ad_soyad'] ?></h6>
                <small class="text-accent fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?= mb_strtoupper($_SESSION['rol'] === 'Son Kullanıcı' ? $_SESSION['unvan'] : $_SESSION['rol']) ?></small>
            </div>
            <a href="login.php" class="ms-auto text-danger opacity-75 hover-opacity-100 transition-all" title="Çıkış Yap"><i class="fa-solid fa-power-off fs-5"></i></a>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="animate-up">
        
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-black text-white mb-1" style="font-size: 2rem; letter-spacing: -1px;"><i class="fa-solid fa-layer-group text-accent me-3"></i><?= mb_strtoupper($page == 'dashboard' ? 'GÖSTERGE PANELİ' : ($page == 'arizalar' ? 'ARIZA YÖNETİMİ' : ($page == 'stok' ? 'ENVANTER TAKİBİ' : ($page == 'harita' ? 'ISI HARİTASI' : ($page == 'cihazlar' ? 'CİHAZLARIM' : 'WIFI PORTALI'))))) ?></h2>
                <p class="text-dim ms-1 fw-medium">IT operasyonları ve sistem durumu canlı verileri.</p>
            </div>
            <?php if($page == 'arizalar'): ?>
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#yeniArizaModal">
                <i class="fa-solid fa-plus me-2"></i> Yeni Kayıt Oluştur
            </button>
            <?php elseif($page == 'cihazlar'): ?>
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#yeniCihazModal">
                <i class="fa-solid fa-plus me-2"></i> Yeni Cihaz Ekle
            </button>
            <?php endif; ?>
        </div>

        <?php if($page == 'dashboard'): ?>
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <a href="?page=arizalar&filter=Bekliyor" class="text-decoration-none">
                        <div class="glass-card p-4 animate-up stat-card-link" style="animation-delay: 0.1s;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="bg-accent bg-opacity-10 p-3 rounded-4"><i class="fa-solid fa-clock-rotate-left fs-4 text-accent"></i></div>
                                <span class="badge bg-accent bg-opacity-20 text-accent rounded-pill px-3">CANLI</span>
                            </div>
                            <h6 class="text-dim small fw-bold mb-1">BEKLEYEN ARIZALAR</h6>
                            <?php 
                            if($_SESSION['rol'] == 'Sistem Yöneticisi') {
                                $q_count = $pdo->query("SELECT COUNT(*) FROM ARIZA WHERE durum = 'Bekliyor'")->fetchColumn();
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM ARIZA WHERE kullanici_id = ? AND durum = 'Bekliyor'");
                                $stmt->execute([$_SESSION['kullanici_id']]);
                                $q_count = $stmt->fetchColumn();
                            }
                            ?>
                            <h2 class="fw-black text-white mb-0"><?= $q_count ?></h2>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="?page=arizalar&filter=Çözüldü" class="text-decoration-none">
                        <div class="glass-card p-4 animate-up stat-card-link" style="animation-delay: 0.2s;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 p-3 rounded-4"><i class="fa-solid fa-check-double fs-4 text-success"></i></div>
                            </div>
                            <h6 class="text-dim small fw-bold mb-1">ÇÖZÜLEN İŞLER</h6>
                            <?php 
                            if($_SESSION['rol'] == 'Sistem Yöneticisi') {
                                $s_count = $pdo->query("SELECT COUNT(*) FROM ARIZA WHERE durum = 'Çözüldü'")->fetchColumn();
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM ARIZA WHERE kullanici_id = ? AND durum = 'Çözüldü'");
                                $stmt->execute([$_SESSION['kullanici_id']]);
                                $s_count = $stmt->fetchColumn();
                            }
                            ?>
                            <h2 class="fw-black text-white mb-0"><?= $s_count ?></h2>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="?page=<?= $_SESSION['rol']=='Sistem Yöneticisi'?'stok':'cihazlar' ?>" class="text-decoration-none">
                        <div class="glass-card p-4 animate-up stat-card-link" style="animation-delay: 0.3s;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="bg-info bg-opacity-10 p-3 rounded-4"><i class="fa-solid fa-laptop-code fs-4 text-info"></i></div>
                            </div>
                            <h6 class="text-dim small fw-bold mb-1">AKTİF CİHAZLAR</h6>
                            <?php 
                            if($_SESSION['rol'] == 'Sistem Yöneticisi') {
                                $c_count = $pdo->query("SELECT COUNT(*) FROM KULLANICI_CIHAZLARI")->fetchColumn();
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM KULLANICI_CIHAZLARI WHERE kullanici_id = ?");
                                $stmt->execute([$_SESSION['kullanici_id']]);
                                $c_count = $stmt->fetchColumn();
                            }
                            ?>
                            <h2 class="fw-black text-white mb-0"><?= $c_count ?></h2>
                        </div>
                    </a>
                </div>
                <?php if($_SESSION['rol'] !== 'Sistem Yöneticisi'): ?>
                <div class="col-md-3">
                    <div class="glass-card p-4 border border-accent border-opacity-30 animate-up" style="animation-delay: 0.4s; background: linear-gradient(135deg, rgba(255,45,85,0.05) 0%, rgba(255,45,85,0) 100%);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-accent p-3 rounded-4 shadow-lg"><i class="fa-solid fa-user-clock fs-4 text-white"></i></div>
                        </div>
                        <h6 class="text-white opacity-50 small fw-bold mb-1">SIRANIZ</h6>
                        <?php 
                        $my_first_pending = $pdo->prepare("SELECT MIN(ariza_id) FROM ARIZA WHERE kullanici_id = ? AND durum = 'Bekliyor'");
                        $my_first_pending->execute([$_SESSION['kullanici_id']]);
                        $min_id = $my_first_pending->fetchColumn();
                        
                        $queue = 0;
                        if($min_id) {
                            $queue_stmt = $pdo->prepare("SELECT COUNT(*) FROM ARIZA WHERE ariza_id < ? AND durum = 'Bekliyor'");
                            $queue_stmt->execute([$min_id]);
                            $queue = $queue_stmt->fetchColumn() + 1;
                        }
                        ?>
                        <h2 class="fw-black text-white mb-0"><?= $queue > 0 ? $queue . '. Sıradasınız' : 'Sırada Değilsiniz' ?></h2>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        
        <?php if($_SESSION['rol'] == 'Sistem Yöneticisi'): ?>
            <div class="glass-card mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 text-white"><i class="fa-solid fa-chart-line text-accent me-2"></i> CANLI AĞ PERFORMANSI</h5>
                    <div class="d-flex gap-2">
                        <?php 
                        $perf = $pdo->query("SELECT * FROM AG_PERFORMANS ORDER BY olcum_id DESC LIMIT 6")->fetchAll();
                        $last_ping = $perf[0]['ping_ms'] ?? 0;
                        ?>
                        <span class="badge bg-white bg-opacity-5 border border-success border-opacity-25 text-success px-3 py-2" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-microchip me-1"></i> ANLIK PİNG: <?= $last_ping ?>ms
                        </span>
                    </div>
                </div>
                <div style="height: 350px; width: 100%;">
                    <canvas id="netChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php elseif($page == 'cihazlar'): ?>
        
        <div class="glass-card">
            <div class="mb-4"><h5 class="fw-bold text-white"><i class="fa-solid fa-laptop-code text-accent me-2"></i> CİHAZ ENVANTERİM</h5></div>
            <div class="table-responsive">
                <table class="custom-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="px-4">CİHAZ TİPİ</th>
                            <th>MARKA / MODEL</th>
                            <th>MAC ADRESİ</th>
                            <th>QR KOD ID</th>
                            <th class="text-end px-4">İŞLEM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM KULLANICI_CIHAZLARI WHERE kullanici_id = ?");
                        $stmt->execute([$_SESSION['kullanici_id']]);
                        while($row = $stmt->fetch()):
                        ?>
                        <tr class="animate-up">
                            <td class="px-4 py-3">
                                <span class="badge bg-white bg-opacity-10 text-white px-3 py-2">
                                    <i class="fa-solid <?= $row['cihaz_tipi'] == 'Telefon' ? 'fa-mobile-screen' : ($row['cihaz_tipi'] == 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop') ?> me-2 text-accent"></i>
                                    <?= $row['cihaz_tipi'] ?>
                                </span>
                            </td>
                            <td class="fw-bold text-white">
                                <?= $row['marka_model'] ?>
                                <?php if($row['sahiplik'] == 'Kurum'): ?>
                                    <br><small class="text-accent fw-normal" style="font-size: 0.7rem;"><i class="fa-solid fa-building me-1"></i> <?= htmlspecialchars($row['bina']) ?> - <?= htmlspecialchars($row['oda']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-dim font-monospace"><?= $row['mac_adresi'] ?></td>
                            <td><code class="text-accent"><?= $row['qr_kod_id'] ?></code></td>
                            <td class="text-end px-4">
                                <button class="btn btn-sm btn-outline-light border-0 qr-view-btn me-2" data-qr="<?= $row['qr_kod_id'] ?>" data-name="<?= $row['marka_model'] ?>"><i class="fa-solid fa-qrcode fs-5"></i></button>
                                <a href="?page=cihazlar&sil=<?= $row['cihaz_id'] ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Cihazı silmek istediğine emin misin kanka?')"><i class="fa-solid fa-trash-can fs-5"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif($page == 'arizalar'): ?>
        <div class="glass-card p-0 overflow-hidden">
            <div class="p-4 border-bottom border-white border-opacity-5 d-flex gap-2">
                <button class="btn btn-sm btn-accent filter-btn active" data-filter="all">TÜMÜ</button>
                <button class="btn btn-sm btn-outline-light border-white border-opacity-10 filter-btn" data-filter="Bekliyor">BEKLEYENLER</button>
                <button class="btn btn-sm btn-outline-light border-white border-opacity-10 filter-btn" data-filter="Çözüldü">ÇÖZÜLENLER</button>
            </div>
            <div class="table-responsive">
                <table class="custom-table align-middle mb-0">
                    <thead>
                        <tr><th class="px-4">KAYIT NO</th><th>CİHAZ / QR</th><th>KONU BAŞLIĞI</th><th>ÖNCELİK</th><th>DURUM</th><th class="text-end px-4">İŞLEM</th></tr>
                    </thead>
                    <tbody id="arizaListesi">
                        <?php if($pdo): 
                            if ($_SESSION['rol'] == 'Sistem Yöneticisi') {
                                $stmt = $pdo->query("SELECT a.*, c.marka_model, c.qr_kod_id FROM ARIZA a LEFT JOIN KULLANICI_CIHAZLARI c ON a.cihaz_id = c.cihaz_id ORDER BY a.ariza_id DESC");
                            } else {
                                $stmt = $pdo->prepare("SELECT a.*, c.marka_model, c.qr_kod_id FROM ARIZA a LEFT JOIN KULLANICI_CIHAZLARI c ON a.cihaz_id = c.cihaz_id WHERE a.kullanici_id = ? ORDER BY a.ariza_id DESC");
                                $stmt->execute([$_SESSION['kullanici_id']]);
                            }
                            while($row = $stmt->fetch()): 
                                $badgeColor = ($row['durum'] == 'Bekliyor') ? 'warning' : 'success';
                        ?>
                        <tr class="ariza-row animate-up" data-status="<?= $row['durum'] ?>">
                            <td class="px-4 fw-bold text-accent">#<?= $row['ariza_id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-white small fw-bold"><?= $row['marka_model'] ?: ($row['manuel_cihaz_adi'] ?: 'Genel Sorun') ?></div>
                                        <div class="text-accent x-small font-monospace" style="font-size: 0.65rem;"><?= $row['qr_kod_id'] ?? 'QR YOK' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-bold text-white"><?= $row['baslik'] ?></td>
                            <td><span class="badge bg-white bg-opacity-10 text-dim px-3 py-2" style="font-size: 0.7rem;"><?= mb_strtoupper($row['oncelik']) ?></span></td>
                            <td><span class="badge-status text-<?= $badgeColor ?> border-<?= $badgeColor ?>"><?= mb_strtoupper($row['durum']) ?></span></td>
                            <td class="text-end px-4">
                                <?php if($_SESSION['rol'] == 'Sistem Yöneticisi' && $row['durum'] == 'Bekliyor'): ?>
                                    <a href="?page=arizalar&coz=<?= $row['ariza_id'] ?>" class="btn btn-sm btn-accent rounded-pill px-3 me-2"><i class="fa-solid fa-check"></i> Çöz</a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-light opacity-50 ariza-detay-btn border-0" data-bs-toggle="modal" data-bs-target="#arizaDetayModal" data-id="<?= $row['ariza_id'] ?>" data-baslik="<?= htmlspecialchars($row['baslik'], ENT_QUOTES) ?>" data-oncelik="<?= $row['oncelik'] ?>" data-durum="<?= $row['durum'] ?>" data-aciklama="<?= htmlspecialchars($row['aciklama'], ENT_QUOTES) ?>"><i class="fa-solid fa-arrow-right"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif($page == 'stok'): ?>
        
        <div class="glass-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0 text-white"><i class="fa-solid fa-boxes-stacked text-accent me-2"></i> KURUMSAL ENVANTER LİSTESİ</h5>
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text bg-white bg-opacity-10 border-0 text-dim"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="stokArama" class="form-control bg-white bg-opacity-5 border-0 text-white" placeholder="Cihaz ara...">
                </div>
            </div>
            <div class="p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="custom-table align-middle mb-0" id="stokTablosu">
                        <thead>
                            <tr>
                                <th class="px-4">CİHAZ / MODEL</th>
                                <th>KATEGORİ</th>
                                <th>SERİ NO</th>
                                <th>DURUM</th>
                                <th>QR</th>
                                <th class="text-end px-4">ADET</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="px-4 py-3 fw-bold text-white"><div class="d-flex align-items-center"><div class="bg-white bg-opacity-5 text-accent rounded p-2 me-3"><i class="fa-solid fa-laptop"></i></div>Dell Latitude 5520</div></td>
                                <td class="text-dim">Dizüstü Bilgisayar</td>
                                <td class="text-dim">DL-5520-X9Y8</td>
                                <td><span class="badge-status text-success border-success">YETERLİ</span></td>
                                <td><button class="btn btn-sm btn-outline-light border-0 opacity-50 qr-btn" data-info="DELL-5520-X9Y8"><i class="fa-solid fa-qrcode"></i></button></td>
                                <td class="text-end px-4 fw-bold text-white">24</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 fw-bold text-white"><div class="d-flex align-items-center"><div class="bg-white bg-opacity-5 text-accent rounded p-2 me-3"><i class="fa-solid fa-network-wired"></i></div>Cisco Catalyst 9200L</div></td>
                                <td class="text-dim">Ağ Cihazı (Switch)</td>
                                <td class="text-dim">CS-9200-ABC1</td>
                                <td><span class="badge-status text-warning border-warning">AZALIYOR</span></td>
                                <td><button class="btn btn-sm btn-outline-light border-0 opacity-50 qr-btn" data-info="CISCO-9200-ABC1"><i class="fa-solid fa-qrcode"></i></button></td>
                                <td class="text-end px-4 fw-bold text-white">3</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 fw-bold text-white"><div class="d-flex align-items-center"><div class="bg-white bg-opacity-5 text-accent rounded p-2 me-3"><i class="fa-solid fa-desktop"></i></div>Lenovo ThinkVision 24"</div></td>
                                <td class="text-dim">Monitör</td>
                                <td class="text-dim">LN-TV24-77X</td>
                                <td><span class="badge-status text-success border-success">YETERLİ</span></td>
                                <td><button class="btn btn-sm btn-outline-light border-0 opacity-50 qr-btn" data-info="LENOVO-TV24-77X"><i class="fa-solid fa-qrcode"></i></button></td>
                                <td class="text-end px-4 fw-bold text-white">15</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 fw-bold text-white"><div class="d-flex align-items-center"><div class="bg-white bg-opacity-5 text-accent rounded p-2 me-3"><i class="fa-solid fa-hard-drive"></i></div>Samsung 980 PRO 1TB SSD</div></td>
                                <td class="text-dim">Donanım Parçası</td>
                                <td class="text-dim">SM-980P-1T</td>
                                <td><span class="badge-status text-danger border-danger">KRİTİK</span></td>
                                <td><button class="btn btn-sm btn-outline-light border-0 opacity-50 qr-btn" data-info="SAMSUNG-980P-1T"><i class="fa-solid fa-qrcode"></i></button></td>
                                <td class="text-end px-4 fw-bold text-white">1</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 fw-bold text-white"><div class="d-flex align-items-center"><div class="bg-white bg-opacity-5 text-accent rounded p-2 me-3"><i class="fa-solid fa-print"></i></div>HP LaserJet Pro MFP</div></td>
                                <td class="text-dim">Yazıcı</td>
                                <td class="text-dim">HP-LJP-M428</td>
                                <td><span class="badge-status text-success border-success">YETERLİ</span></td>
                                <td><button class="btn btn-sm btn-outline-light border-0 opacity-50 qr-btn" data-info="HP-LJP-M428"><i class="fa-solid fa-qrcode"></i></button></td>
                                <td class="text-end px-4 fw-bold text-white">5</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif($page == 'harita'): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="glass-card h-100">
                    <div class="mb-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-white"><i class="fa-solid fa-map-location-dot text-accent me-2"></i> <span id="mapTitle">Kampüs Yerleşkesi</span></h5>
                            <p class="text-dim small mt-1" id="mapSubTitle">Lütfen incelemek istediğiniz bloğu seçin.</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <button id="mapBackBtn" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2" style="display:none;"><i class="fa-solid fa-arrow-left me-2"></i> Geri Dön</button>
                            <button id="mapBinaEkleBtn" class="btn btn-sm btn-accent rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manuelBinaEkleModal"><i class="fa-solid fa-plus me-1"></i> Bina Ekle</button>
                            <button id="mapOdaEkleBtn" class="btn btn-sm btn-info rounded-pill px-3 text-dark fw-bold ms-2" data-bs-toggle="modal" data-bs-target="#manuelOdaEkleModal" style="display:none;"><i class="fa-solid fa-plus me-1"></i> Oda Ekle</button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center align-items-center p-4 min-vh-50">
                        <div id="mapContent" class="w-100">
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <div class="card-body p-0">
                        <h6 class="fw-bold mb-4 text-white">Harita Lejantı</h6>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success rounded shadow-sm" style="width:20px;height:20px; box-shadow: 0 0 10px rgba(46, 204, 113, 0.3);"></div>
                            <span class="ms-3 text-dim small">Normal Durum</span>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning rounded shadow-sm" style="width:20px;height:20px; box-shadow: 0 0 10px rgba(243, 156, 18, 0.3);"></div>
                            <span class="ms-3 text-dim small">Dikkat Gerekebilir</span>
                        </div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-danger rounded animate__animated animate__flash animate__infinite shadow-sm" style="width:20px;height:20px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                            <span class="ms-3 text-white fw-bold small">KRİTİK / ARIZA</span>
                        </div>
                        
                        <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                            <h6 class="fw-bold text-white small">Detay Paneli</h6>
                            <div id="kabinDetayKutusu" class="bg-black bg-opacity-20 rounded-4 p-4 text-center text-dim small border border-secondary border-opacity-10 shadow-sm">
                                <i class="fa-solid fa-mouse-pointer d-block mb-2 fs-4 opacity-20"></i>
                                İncelemek için harita üzerinden bir öğe seçin.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .server-room-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                padding: 30px;
                background: rgba(255,255,255,0.02);
                border-radius: 24px;
                border: 1px solid rgba(255,255,255,0.08);
                width: 100%;
                max-width: 600px;
                backdrop-filter: blur(10px);
            }
            .server-rack {
                background: rgba(255,255,255,0.03);
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 16px;
                height: 110px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                cursor: pointer;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }
            .server-rack::before {
                content: '';
                position: absolute;
                top: 0; left: 0; width: 100%; height: 4px;
                opacity: 0.8;
            }
            .server-rack.status-normal::before { background: #2ecc71; box-shadow: 0 2px 10px rgba(46,204,113,0.3); }
            .server-rack.status-warning::before { background: #f39c12; box-shadow: 0 2px 10px rgba(243,156,18,0.3); }
            .server-rack.status-danger::before { background: var(--accent); box-shadow: 0 2px 10px var(--accent-glow); animation: rack-pulse 1.5s infinite; }
            
            .server-rack:hover {
                transform: translateY(-8px) scale(1.02);
                background: rgba(255,255,255,0.08);
                border-color: rgba(255,255,255,0.2);
                box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            }
            .server-rack i {
                font-size: 26px;
                margin-bottom: 8px;
                color: var(--text-dim);
                transition: 0.3s;
            }
            .server-rack:hover i { color: #fff; transform: scale(1.1); }
            .server-rack span {
                font-weight: 800;
                font-size: 13px;
                color: #fff;
                letter-spacing: 0.5px;
            }
            .temp-badge {
                position: absolute;
                top: 12px;
                right: 12px;
                font-size: 9px;
                padding: 3px 8px;
                border-radius: 20px;
                font-weight: 900;
                color: #fff;
                background: rgba(0,0,0,0.3);
                border: 1px solid rgba(255,255,255,0.1);
            }
            
            @keyframes rack-pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.6; }
            }
        </style>
        
        <?php elseif($page == 'wifi'): ?>
            <?php if($_SESSION['rol'] == 'Sistem Yöneticisi'): ?>
                
                <div class="glass-card p-0 overflow-hidden mb-5">
                    <div class="p-4 border-bottom border-secondary border-opacity-10">
                        <h5 class="fw-bold mb-0 text-white"><i class="fa-solid fa-clipboard-list text-accent me-2"></i> PENDING APPLICATIONS</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table align-middle mb-0">
                            <thead>
                                <tr><th class="px-4">APPLICANT</th><th>DETAILS</th><th>DATE</th><th class="text-end px-4">ACTION</th></tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stmt = $pdo->query("SELECT * FROM ARIZA WHERE baslik LIKE '[AĞ BAŞVURUSU]%' AND durum = 'Bekliyor' ORDER BY ariza_id DESC");
                                $found = false;
                                while($row = $stmt->fetch()): $found = true;
                                ?>
                                <tr>
                                    <td class="px-4 fw-bold text-white"><?= str_replace('[AĞ BAŞVURUSU] - ', '', $row['baslik']) ?></td>
                                    <td class="small text-dim"><?= nl2br(htmlspecialchars($row['aciklama'])) ?></td>
                                    <td class="text-dim small"><?= date('d.m.Y H:i', strtotime($row['tarih'])) ?></td>
                                    <td class="text-end px-4">
                                        <button class="btn btn-sm btn-accent rounded-pill px-3 me-1 shadow-sm"><i class="fa-solid fa-check"></i></button>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="fa-solid fa-xmark"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; 
                                if(!$found): echo '<tr><td colspan="4" class="text-center py-5 text-dim">No pending applications found.</td></tr>'; endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="glass-card p-5">
                        <div class="text-center mb-5">
                            <div class="bg-accent bg-opacity-10 d-inline-flex p-4 rounded-circle mb-4" style="box-shadow: 0 0 20px var(--accent-glow);"><i class="fa-solid fa-wifi fs-1 text-accent"></i></div>
                            <h3 class="fw-black text-white">KURUMSAL AĞ ERİŞİMİ</h3>
                            <p class="text-dim">Güvenli internet erişimi için başvurunuzu buradan tamamlayın.</p>
                        </div>
                        <form action="wifi-portal.php" method="POST" id="dashWifiForm">
                            <div class="row g-4 text-start">
                                <div class="col-md-12">
                                    <label class="stat-label">KULLANICI TİPİ</label>
                                    <select name="wifi_type" id="dashUserType" class="form-control form-control-lg wifi-input" onchange="toggleDashWifi()">
                                        <option value="guest" selected>Misafir (Anlık Şifre)</option>
                                        <option value="student">Öğrenci (Kayıtlı Cihaz)</option>
                                        <option value="staff">Personel / Akademisyen</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label">AD</label>
                                    <input type="text" name="ad" class="form-control form-control-lg wifi-input" value="<?= explode(' ', $_SESSION['ad_soyad'])[0] ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label">SOYAD</label>
                                    <input type="text" name="soyad" class="form-control form-control-lg wifi-input" value="<?= explode(' ', $_SESSION['ad_soyad'])[1] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label">T.C. KİMLİK NO</label>
                                    <input type="text" name="tc" class="form-control form-control-lg wifi-input" maxlength="11" placeholder="11 haneli TC No" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label">TELEFON</label>
                                    <input type="tel" name="tel" class="form-control form-control-lg wifi-input" placeholder="05XX XXX XX XX" required>
                                </div>
                                <div id="dashExtraNoArea" class="col-md-6" style="display:none;">
                                    <label class="stat-label">ÖĞRENCİ / SİCİL NO</label>
                                    <input type="text" name="extra_no" class="form-control form-control-lg wifi-input" placeholder="Numaranızı giriniz...">
                                </div>
                                <div id="dashMacArea" class="col-md-6" style="display:none;">
                                    <label class="stat-label">CİHAZ MAC ADRESİ</label>
                                    <input type="text" name="mac" class="form-control form-control-lg wifi-input mac-format" placeholder="00:00:00:00:00:00" maxlength="17">
                                </div>
                                <div class="col-12">
                                    <label class="legal-check">
                                        <input type="checkbox" required>
                                        <span class="small">5651 Sayılı Kanun gereği log kayıtlarımın tutulmasını onaylıyorum.</span>
                                    </label>
                                </div>
                                <div class="col-12 mt-5">
                                    <button type="submit" id="dashWifiSubmit" class="btn-accent w-100 py-3"><i class="fa-solid fa-key me-2"></i> ŞİFRE ÜRET</button>
                                </div>
                            </div>
                        </form>
                        <div id="dashWifiResult" style="display:none;" class="mt-5 p-4 rounded-4 border border-accent border-opacity-20 bg-white bg-opacity-5 text-center shadow-lg animate__animated animate__fadeIn">
                            <h6 class="fw-bold text-white mb-2">MİSAFİR ERİŞİM ŞİFRENİZ</h6>
                            <div class="password-display" id="dashPassValue" title="Görmek için üzerine gel">WIFI-XXXX</div>
                            <small class="text-dim">Üzerine gelince görünür. 24 saat geçerlidir.</small>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                function toggleDashWifi() {
                    const type = document.getElementById('dashUserType').value;
                    const extra = document.getElementById('dashExtraNoArea');
                    const mac = document.getElementById('dashMacArea');
                    const btn = document.getElementById('dashWifiSubmit');
                    const result = document.getElementById('dashWifiResult');
                    
                    extra.style.display = (type === 'guest') ? 'none' : 'block';
                    mac.style.display = (type === 'guest') ? 'none' : 'block';
                    btn.innerHTML = (type === 'guest') ? '<i class="fa-solid fa-key me-2"></i> ŞİFRE ÜRET' : '<i class="fa-solid fa-paper-plane me-2"></i> BAŞVURUYU GÖNDER';
                    result.style.display = 'none';
                }
                
                document.getElementById('dashWifiForm').onsubmit = function(e) {
                    const type = document.getElementById('dashUserType').value;
                    if (type === 'guest') {
                        e.preventDefault();
                        const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
                        let pass = "WIFI-";
                        for (let i = 0; i < 4; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
                        document.getElementById('dashPassValue').innerText = pass;
                        document.getElementById('dashWifiResult').style.display = 'block';
                        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                    }
                };
            </script>
            <script>
                document.querySelectorAll('.mac-format').forEach(input => {
                    input.addEventListener('input', function (e) {
                        let value = e.target.value.toUpperCase().replace(/[^0-9A-F]/g, '');
                        let formatted = "";
                        for (let i = 0; i < value.length; i++) {
                            if (i > 0 && i % 2 === 0) formatted += ":";
                            formatted += value[i];
                        }
                        e.target.value = formatted.substring(0, 17);
                    });
                });
            </script>
        <?php else: ?>
        <div class="text-center py-5 mt-5">
            <i class="fa-solid fa-person-digging fs-1 text-secondary opacity-50 mb-3 animate__animated animate__bounce infinite"></i>
            <h3 class="fw-bold text-secondary">Yapım Aşamasında</h3>
            <p class="text-muted">Bu modül henüz sisteme entegre edilmedi.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="floating-chat-btn" id="chatToggleBtn">
    <i class="fa-solid fa-robot"></i>
</div>

<div class="chat-widget" id="chatWidget">
    <div class="chat-header">
        <div class="d-flex align-items-center">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:35px;height:35px;"><i class="fa-solid fa-robot"></i></div>
            <div>
                <h6 class="mb-0 fw-bold">IT Asistanı</h6>
                <small style="font-size:0.7rem; font-weight:normal; opacity:0.8;"><i class="fa-solid fa-circle text-success" style="font-size:0.5rem;"></i> Çevrimiçi</small>
            </div>
        </div>
        <i class="fa-solid fa-xmark close-chat" id="chatCloseBtn"></i>
    </div>
    
    <div class="chat-messages" id="chatBox">
        <div class="msg-bubble msg-bot shadow-sm">
            Selam kanka! TechLog sistemine hoş geldin. Ağ, internet, donanım veya mavi ekran gibi bir derdin varsa bana sorabilirsin. Nasıl yardımcı olayım? 🚀
        </div>
        <div class="typing-indicator" id="typingIndicator">
            <span></span><span></span><span></span>
        </div>
    </div>
    
    <div class="chat-input-container">
        <input type="text" id="userInput" class="chat-input" placeholder="Sorununu buraya yaz..." autocomplete="off">
        <button id="sendBtn" class="send-btn shadow-sm"><i class="fa-solid fa-paper-plane" style="transform: rotate(-45deg);"></i></button>
    </div>
</div>

<div class="modal fade" id="yeniArizaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Yeni Arıza Bildir</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-transparent">
                <input type="hidden" name="islem" value="yeni_ariza">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-accent small letter-spacing-1">SİSTEMDEKİ CİHAZIM</label>
                        <select name="cihaz_id" class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none">
                            <option value="">Seçilmedi</option>
                            <?php 
                            $c_stmt = $pdo->prepare("SELECT cihaz_id, marka_model FROM KULLANICI_CIHAZLARI WHERE kullanici_id = ?");
                            $c_stmt->execute([$_SESSION['kullanici_id']]);
                            while($c = $c_stmt->fetch()):
                            ?>
                            <option value="<?= $c['cihaz_id'] ?>"><?= $c['marka_model'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-accent small letter-spacing-1">VEYA CİHAZ ADI YAZ</label>
                        <input type="text" name="manuel_cihaz_adi" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Örn: iPhone 12">
                    </div>
                </div>
                <div class="mb-4"><label class="form-label fw-bold text-accent small letter-spacing-1">SORUN BAŞLIĞI</label><input type="text" name="baslik" class="form-control form-control-lg bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Kısa bir başlık yazın..." required></div>
                <div class="mb-4"><label class="form-label fw-bold text-accent small letter-spacing-1">DETAYLI AÇIKLAMA</label><textarea name="aciklama" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" rows="4" placeholder="Sorunu detaylandırın..." required></textarea></div>
                <div class="mb-4"><label class="form-label fw-bold text-accent small letter-spacing-1">ÖNCELİK SEVİYESİ</label><select name="oncelik" class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none"><option value="DÜŞÜK">Düşük</option><option value="ORTA" selected>Orta</option><option value="YÜKSEK">Yüksek</option></select></div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn-accent w-100 py-3">KAYDI OLUŞTUR</button>
            </div>
        </form>
    </div>
</div>


<div class="modal fade" id="yeniCihazModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST">
            <div class="modal-header bg-accent text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-laptop-medical me-2"></i> Yeni Cihaz Kaydet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-transparent">
                <input type="hidden" name="islem" value="cihaz_ekle">
                <?php $isAuthorizedModal = (isset($_SESSION['rol']) && $_SESSION['rol'] == 'Sistem Yöneticisi') || (isset($_SESSION['unvan']) && $_SESSION['unvan'] == 'Personel'); ?>
                <?php if($isAuthorizedModal): ?>
                <div class="mb-4">
                    <label class="stat-label">CİHAZ MÜLKİYETİ</label>
                    <select name="sahiplik" id="cihazSahiplik" class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" onchange="document.getElementById('kurumAlanlari').style.display = (this.value === 'Kurum') ? 'block' : 'none';">
                        <option value="Sahsi">Şahsi Cihazım</option>
                        <option value="Kurum">Kurum Bilgisayarı / Cihazı</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="sahiplik" value="Sahsi">
                <?php endif; ?>
                <div class="mb-4">
                    <label class="stat-label">CİHAZ TİPİ</label>
                    <select name="cihaz_tipi" class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none">
                        <option value="Bilgisayar">Bilgisayar</option>
                        <option value="Telefon">Telefon</option>
                        <option value="Tablet">Tablet</option>
                        <option value="Akıllı Saat">Akıllı Saat</option>
                        <option value="Diğer">Diğer</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="stat-label">MARKA / MODEL</label>
                    <input type="text" name="marka_model" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Örn: iPhone 15 Pro, MacBook Air" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">MAC ADRESİ</label>
                    <input type="text" name="mac" class="form-control mac-format bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="00:00:00:00:00:00" maxlength="17" required>
                </div>
                <div id="kurumAlanlari" style="display:none;">
                    <div class="row g-3 mb-1">
                        <div class="col-md-6">
                            <label class="stat-label">BULUNDUĞU BİNA</label>
                            <select name="bina" id="cihazBinaSelect" class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" onchange="updateOdaSelect()">
                                <option value="">Seçiniz...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="stat-label">ODA / LAB NO</label>
                            <select name="oda" id="cihazOdaSelect" class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none">
                                <option value="">Önce Bina Seçin</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn-accent w-100 py-3">CİHAZI SİSTEME EKLE</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="arizaDetayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-info me-2 text-info"></i> Arıza Detayı <span id="mdlKayitNo" class="text-muted ms-2 fs-6"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-transparent">
                <h4 id="mdlBaslik" class="fw-bold text-white mb-4">Yükleniyor...</h4>
                
                <div class="d-flex gap-3 mb-4">
                    <span id="mdlOncelik" class="badge-status">...</span>
                    <span id="mdlDurum" class="badge-status">...</span>
                </div>
                
                <div class="bg-white bg-opacity-5 p-4 rounded-4 border border-white border-opacity-10">
                    <h6 class="fw-bold text-accent small mb-3 letter-spacing-1">AÇIKLAMA</h6>
                    <p id="mdlAciklama" class="mb-0 text-dim" style="white-space: pre-wrap; line-height: 1.6;">Açıklama yükleniyor...</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-outline-light opacity-50 rounded-4 px-4 py-2 fw-bold" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn-accent px-4 py-2"><i class="fa-solid fa-check me-2"></i>Çözüldü İşaretle</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-2">
                <h6 class="modal-title fw-bold">Cihaz QR Kodu</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-5 bg-transparent">
                <div id="qrCodeContainer" class="mb-4 p-3 bg-white rounded-4 d-inline-block"></div>
                <div id="qrInfo" class="fw-bold text-white fs-5 mt-2"></div>
                <hr class="border-secondary border-opacity-25 my-4">
                <button class="btn btn-outline-accent w-100 rounded-4 py-3 fw-bold" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Kodu Yazdır</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manuelBinaEkleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" onsubmit="event.preventDefault(); Swal.fire({title: 'Başarılı', text: 'Bina başarıyla eklendi (Demo)', icon: 'success', confirmButtonColor: '#2ecc71', background: '#12141d', color: '#fff'}); $('#manuelBinaEkleModal').modal('hide');">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-building-circle-check me-2 text-success"></i> Manuel Bina Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-transparent">
                <div class="mb-4">
                    <label class="form-label fw-bold text-accent small letter-spacing-1">BİNA KODU / ID</label>
                    <input type="text" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Örn: D-BLOK" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-accent small letter-spacing-1">BİNA ADI</label>
                    <input type="text" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Örn: Tıp Fakültesi" required>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold text-accent small letter-spacing-1">DURUM</label>
                    <select class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none">
                        <option value="normal">Normal</option>
                        <option value="warning">Dikkat Gerekebilir</option>
                        <option value="danger">Kritik / Arıza</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn-accent w-100 py-3">SİSTEME EKLE</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="manuelOdaEkleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" onsubmit="event.preventDefault(); Swal.fire({title: 'Başarılı', text: 'Oda başarıyla eklendi (Demo)', icon: 'success', confirmButtonColor: '#2ecc71', background: '#12141d', color: '#fff'}); $('#manuelOdaEkleModal').modal('hide');">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-door-open me-2 text-success"></i> Manuel Oda Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-transparent">
                <div class="mb-4">
                    <label class="form-label fw-bold text-accent small letter-spacing-1">ODA KODU / ID</label>
                    <input type="text" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Örn: LAB-202" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-accent small letter-spacing-1">ODA ADI</label>
                    <input type="text" class="form-control bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none" placeholder="Örn: Ağ Laboratuvarı" required>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold text-accent small letter-spacing-1">DURUM</label>
                    <select class="form-select bg-white bg-opacity-5 border-secondary border-opacity-25 text-white shadow-none">
                        <option value="normal">Normal</option>
                        <option value="warning">Dikkat Gerekebilir</option>
                        <option value="danger">Kritik / Arıza</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn-accent w-100 py-3">SİSTEME EKLE</button>
            </div>
        </form>
    </div>
</div>

<script>
    const globalMapData = {
        campus: [
            { id: 'A-BLOK', name: 'Mühendislik Fakültesi', status: 'normal' },
            { id: 'B-BLOK', name: 'İdari Bilimler', status: 'warning' },
            { id: 'C-BLOK', name: 'Rektörlük / IT Merkez', status: 'danger' }
        ],
        rooms: {
            'C-BLOK': [
                { id: 'LAB-101', name: 'Sistem Odası A', status: 'danger' },
                { id: 'LAB-102', name: 'Yazılım Laboratuvarı', status: 'normal' }
            ],
            'A-BLOK': [
                { id: 'ROOM-201', name: 'Bilgisayar Lab 1', status: 'normal' }
            ],
            'B-BLOK': [
                { id: 'AMFI-1', name: 'Büyük Amfi', status: 'warning' }
            ]
        }
    };

    function updateOdaSelect() {
        const bina = document.getElementById("cihazBinaSelect").value;
        const odaSelect = document.getElementById("cihazOdaSelect");
        if(!odaSelect) return;
        odaSelect.innerHTML = '<option value="">Seçiniz...</option>';
        if(bina && globalMapData.rooms[bina]) {
            globalMapData.rooms[bina].forEach(r => {
                odaSelect.innerHTML += `<option value="${r.id}">${r.id} - ${r.name}</option>`;
            });
        } else if (bina) {
            odaSelect.innerHTML = '<option value="">Oda Bulunamadı</option>';
        } else {
            odaSelect.innerHTML = '<option value="">Önce Bina Seçin</option>';
        }
    }

    $(document).ready(function(){
        const binaSelect = document.getElementById("cihazBinaSelect");
        if(binaSelect) {
            globalMapData.campus.forEach(b => {
                binaSelect.innerHTML += `<option value="${b.id}">${b.id} - ${b.name}</option>`;
            });
        }
        
        $(document).on('click', '.filter-btn', function(){
            $(".filter-btn").removeClass("btn-accent active").addClass("btn-outline-light border-white border-opacity-10");
            $(this).removeClass("btn-outline-light").addClass("btn-accent active");
            const filter = $(this).data("filter");
            if(filter === "all") {
                $(".ariza-row").show();
            } else {
                $(".ariza-row").hide();
                $(`.ariza-row[data-status="${filter}"]`).show();
            }
        });

        const urlParams = new URLSearchParams(window.location.search);
        const filterParam = urlParams.get('filter');
        if(filterParam) {
            $(`.filter-btn[data-filter="${filterParam}"]`).click();
        }

        <?php if($page == 'dashboard' && $_SESSION['rol'] == 'Sistem Yöneticisi'): 
            $labels = []; $down = []; $up = [];
            foreach(array_reverse($perf) as $p) {
                $labels[] = date('H:i', strtotime($p['olcum_zamani']));
                $down[] = (float)$p['download_mbps'];
                $up[] = (float)$p['upload_mbps'];
            }
        ?>
        const ctx = document.getElementById('netChart').getContext('2d');
        const downGradient = ctx.createLinearGradient(0, 0, 0, 400);
        downGradient.addColorStop(0, 'rgba(52, 152, 219, 0.2)');
        downGradient.addColorStop(1, 'rgba(52, 152, 219, 0)');

        const upGradient = ctx.createLinearGradient(0, 0, 0, 400);
        upGradient.addColorStop(0, 'rgba(255, 45, 85, 0.2)');
        upGradient.addColorStop(1, 'rgba(255, 45, 85, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Download (Mbps)',
                    data: <?= json_encode($down) ?>,
                    borderColor: '#3498db',
                    backgroundColor: downGradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#3498db'
                }, {
                    label: 'Upload (Mbps)',
                    data: <?= json_encode($up) ?>,
                    borderColor: '#ff2d55',
                    backgroundColor: upGradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#ff2d55'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8', font: { weight: 'bold' } } } },
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });
        <?php endif; ?>


        $(".qr-view-btn").click(function(){
            const qr = $(this).data("qr");
            const name = $(this).data("name");
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qr}`;
            
            Swal.fire({
                title: `<span style="color:#fff">${name}</span>`,
                html: `<div class="p-3 bg-white d-inline-block rounded-4 mb-3"><img src="${qrUrl}" class="img-fluid"></div><br><code class="fs-4 text-accent">${qr}</code>`,
                background: '#12141d',
                confirmButtonColor: '#ff2d55',
                confirmButtonText: 'Kapat'
            });
        });
        

        $("#chatToggleBtn").click(function(){
            $("#chatWidget").addClass("active");
            $(this).css("transform", "scale(0)");
            setTimeout(() => { $("#userInput").focus(); }, 300);
        });
        
        $("#chatCloseBtn").click(function(){
            $("#chatWidget").removeClass("active");
            $("#chatToggleBtn").css("transform", "scale(1)");
        });


        let isTyping = false;
        function sendMessage() {
            if(isTyping) return;
            let msg = $("#userInput").val().trim();
            if(msg === "") return;
            
            isTyping = true;
            $("#userInput").prop("disabled", true);
            $("#sendBtn").prop("disabled", true);

            let userHtml = `<div class="msg-bubble msg-user shadow-sm">${msg}</div>`;
            $("#typingIndicator").before(userHtml);
            $("#userInput").val("");
            scrollToBottom();
            
            $("#typingIndicator").css("display", "flex");
            scrollToBottom();
            
            $.ajax({
                url: "chatbot.php",
                type: "POST",
                data: { message: msg },
                dataType: "json",
                success: function(data){
                    isTyping = false;
                    $("#userInput").prop("disabled", false);
                    $("#sendBtn").prop("disabled", false);
                    $("#userInput").focus();

                    $("#typingIndicator").hide();
                    let botHtml = `<div class="msg-bubble msg-bot shadow-sm">${data.response}</div>`;
                    $("#typingIndicator").before(botHtml);
                    scrollToBottom();
                },
                error: function(){
                    isTyping = false;
                    $("#userInput").prop("disabled", false);
                    $("#sendBtn").prop("disabled", false);
                    $("#userInput").focus();

                    $("#typingIndicator").hide();
                    let errHtml = `<div class="msg-bubble msg-bot shadow-sm" style="background: #e74c3c;">Bağlantı koptu kanka, API dosyanı veya internetini kontrol et!</div>`;
                    $("#typingIndicator").before(errHtml);
                    scrollToBottom();
                }
            });
        }
        

        $("#sendBtn").click(function(){ sendMessage(); });
        $("#userInput").keypress(function(e){ if(e.which == 13) sendMessage(); });
        

        function scrollToBottom() {
            let chatBox = document.getElementById("chatBox");
            chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
        }
        

        $(".ariza-detay-btn").click(function(){
            const id = $(this).data("id");
            const baslik = $(this).data("baslik");
            const oncelik = $(this).data("oncelik");
            const durum = $(this).data("durum");
            const aciklama = $(this).data("aciklama");
            
            $("#mdlKayitNo").text("#" + id);
            $("#mdlBaslik").text(baslik);
            $("#mdlAciklama").text(aciklama);
            

            let oncelikColor = "secondary";
            if(oncelik === "YÜKSEK") oncelikColor = "danger";
            else if(oncelik === "ORTA") oncelikColor = "warning text-dark";
            else if(oncelik === "DÜŞÜK") oncelikColor = "info text-dark";
            $("#mdlOncelik").attr("class", "badge bg-" + oncelikColor + " fs-6 px-3 py-2").text(oncelik);
            

            let durumColor = (durum === "Bekliyor") ? "warning text-dark" : "success";
            $("#mdlDurum").attr("class", "badge bg-" + durumColor + " fs-6 px-3 py-2").text(durum);
        });


        $("#stokArama").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#stokTablosu tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });


        if($("#mapContent").length) {
            let currentLevel = 'campus'; // campus -> room -> racks
            
            function renderMap() {
                let html = '';
                if(currentLevel === 'campus') {
                    $("#mapTitle").text("Kampüs Yerleşkesi");
                    $("#mapSubTitle").text("İncelemek istediğiniz binayı/bloğu seçin.");
                    $("#mapBackBtn").hide();
                    $("#mapBinaEkleBtn").show();
                    $("#mapOdaEkleBtn").hide();
                    html = '<div class="row g-3">';
                    globalMapData.campus.forEach(b => {
                        html += `
                        <div class="col-md-4">
                            <div class="block-card p-4 text-center cursor-pointer map-item" data-type="block" data-id="${b.id}">
                                <i class="fa-solid fa-building fs-1 mb-3 text-${b.status === 'danger' ? 'danger' : (b.status === 'warning' ? 'warning' : 'success')}"></i>
                                <h6 class="fw-bold text-white mb-1">${b.id}</h6>
                                <small class="text-dim">${b.name}</small>
                            </div>
                        </div>`;
                    });
                    html += '</div>';
                } else if(currentLevel === 'room') {
                    const blockId = $("#mapBackBtn").data("target");
                    $("#mapTitle").text(blockId + " Kat Planı");
                    $("#mapSubTitle").text("İncelemek istediğiniz odayı veya laboratuvarı seçin.");
                    $("#mapBackBtn").show();
                    $("#mapBinaEkleBtn").hide();
                    $("#mapOdaEkleBtn").show();
                    html = '<div class="row g-3">';
                    (globalMapData.rooms[blockId] || []).forEach(r => {
                        html += `
                        <div class="col-md-4">
                            <div class="block-card p-4 text-center cursor-pointer map-item" data-type="room" data-id="${r.id}">
                                <i class="fa-solid fa-door-open fs-1 mb-3 text-${r.status === 'danger' ? 'danger' : 'success'}"></i>
                                <h6 class="fw-bold text-white mb-1">${r.id}</h6>
                                <small class="text-dim">${r.name}</small>
                            </div>
                        </div>`;
                    });
                    html += '</div>';
                } else if(currentLevel === 'racks') {
                    const roomId = $("#mapBackBtn").data("target");
                    $("#mapTitle").text(roomId + " Kabin Yerleşimi");
                    $("#mapSubTitle").text("Canlı sıcaklık ve doluluk oranlarını takip edin.");
                    $("#mapBackBtn").show();
                    $("#mapBinaEkleBtn").hide();
                    $("#mapOdaEkleBtn").hide();
                    html = '<div class="server-room-grid p-3" style="background: rgba(255,255,255,0.02); border-radius: 24px; border: 1px solid rgba(255,255,255,0.05);">';
                    const racks = [
                        { id: "RACK-01", temp: 22, status: "normal" },
                        { id: "RACK-02", temp: 24, status: "normal" },
                        { id: "RACK-03", temp: 28, status: "warning" },
                        { id: "RACK-04", temp: 21, status: "normal" },
                        { id: "RACK-05", temp: 23, status: "normal" },
                        { id: "RACK-06", temp: 32, status: "danger" }, 
                        { id: "RACK-07", temp: 25, status: "normal" },
                        { id: "RACK-08", temp: 27, status: "warning" },
                    ];
                    racks.forEach(rack => {
                        html += `
                        <div class="server-rack status-${rack.status} animate__animated animate__zoomIn" data-id="${rack.id}" data-temp="${rack.temp}" data-status="${rack.status}">
                            <div class="temp-badge">${rack.temp}°C</div>
                            <i class="fa-solid fa-server"></i>
                            <span>${rack.id}</span>
                        </div>`;
                    });
                    html += '</div>';
                }
                $("#mapContent").html(html);
                attachMapEvents();
            }

            function attachMapEvents() {
                $(".map-item").click(function() {
                    const type = $(this).data("type");
                    const id = $(this).data("id");
                    if(type === 'block') {
                        currentLevel = 'room';
                        $("#mapBackBtn").data("target", id).data("prev", "campus");
                    } else if(type === 'room') {
                        currentLevel = 'racks';
                        $("#mapBackBtn").data("target", id).data("prev", "room");
                    }
                    renderMap();
                });

                $(".server-rack").hover(function() {
                    const id = $(this).data("id");
                    const temp = $(this).data("temp");
                    const status = $(this).data("status");
                    let color = (status === 'danger' ? 'var(--accent)' : (status === 'warning' ? '#f39c12' : '#2ecc71'));
                    $("#kabinDetayKutusu").html(`
                        <h5 class="fw-black text-white mb-2">${id}</h5>
                        <div class="display-6 fw-bold mb-2" style="color:${color}">${temp}°C</div>
                        <span class="badge rounded-pill bg-white bg-opacity-10 text-white border border-white border-opacity-10 px-3">FAN HIZI: ${status === 'danger' ? '%100' : '%60'}</span>
                    `);
                });
            }

            $("#mapBackBtn").click(function() {
                const prev = $(this).data("prev");
                if(currentLevel === 'racks') {
                    currentLevel = 'room';
                    $(this).data("prev", "campus");
                } else {
                    currentLevel = 'campus';
                }
                renderMap();
            });

            renderMap();
        }

        // Global MAC ve Beyaz Buton Fix
        document.querySelectorAll('.mac-format').forEach(input => {
            input.addEventListener('input', function (e) {
                let value = e.target.value.toUpperCase().replace(/[^0-9A-F]/g, '');
                let formatted = "";
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 2 === 0) formatted += ":";
                    formatted += value[i];
                }
                e.target.value = formatted.substring(0, 17);
            });
        });

        // Admin Wifi Buton Fix (Screenshot 2)
        $("#dashWifiSubmit").addClass("btn-accent").removeClass("btn-light bg-white");


        $(".qr-btn").click(function(){
            const info = $(this).data("info");
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${info}`;
            
            Swal.fire({
                title: `<span style="color:#fff">DİJİTAL KİMLİK</span>`,
                html: `<div class="p-3 bg-white d-inline-block rounded-4 mb-3"><img src="${qrUrl}" class="img-fluid"></div><br><code class="fs-4 text-accent">${info}</code>`,
                background: '#12141d',
                confirmButtonColor: '#ff2d55',
                confirmButtonText: 'Kapat'
            });
        });

    });
</script>

<?php if(isset($_GET['success'])): ?>
<script>
    Swal.fire({ title: 'Sistem Güncellendi', text: 'Arıza kaydınız veritabanına işlendi.', icon: 'success', confirmButtonColor: '#2ecc71', timer: 3000, background: '#fff' });
</script>
<?php endif; ?>
</body>
</html>
