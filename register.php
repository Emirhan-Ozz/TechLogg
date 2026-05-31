<?php
session_start();
require_once 'db.php';

$hata = "";
$basari = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $email = trim($_POST['email']);
    $tc = trim($_POST['tc']);
    $sifre = trim($_POST['sifre']);
    $tel = trim($_POST['tel'] ?? '');
    $rol = $_POST['rol'];
    $ek_no = $_POST['ek_no'] ?? '';

    if (empty($ad) || empty($soyad) || empty($email) || empty($tc) || empty($sifre)) {
        $hata = "Lütfen tüm zorunlu alanları doldurun!";
    } elseif (strlen($tc) !== 11 || !ctype_digit($tc)) {
        $hata = "T.C. Kimlik numarası tam 11 haneli olmalı ve sadece rakamlardan oluşmalıdır!";
    } elseif (!empty($tel) && (strlen($tel) < 10 || strlen($tel) > 15 || !ctype_digit($tel))) {
        $hata = "Telefon numarası geçersiz! Sadece rakam giriniz (Örn: 05551234567).";
    } else {
        try {
            // E-posta, TC veya Okul/Sicil No kontrolü
            $check = $pdo->prepare("SELECT * FROM KULLANICI WHERE e_posta = ? OR tc_no = ? OR (okul_no = ? AND okul_no != '')");
            $check->execute([$email, $tc, $ek_no]);
            if ($check->rowCount() > 0) {
                $hata = "Bu e-posta, T.C. numarası veya Okul/Sicil numarası zaten sisteme kayıtlı!";
            } else {
                // Kayıt işlemi (Senin login.php'deki sifre_hash mantığına göre düz metin saklıyoruz)
                $stmt = $pdo->prepare("INSERT INTO KULLANICI (ad, soyad, e_posta, tc_no, sifre_hash, rol, unvan, okul_no, tel_no) VALUES (?, ?, ?, ?, ?, 'Son Kullanıcı', ?, ?, ?)");
                $stmt->execute([$ad, $soyad, $email, $tc, $sifre, $rol, $ek_no, $tel]);
                header("Location: login.php?msg=registered");
                exit;
            }
        } catch (PDOException $e) {
            $hata = "Bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechLog - Kayıt Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --bg: #0d0f14; 
            --card-bg: rgba(255, 255, 255, 0.03); 
            --accent: #ff2d55; 
            --accent-glow: rgba(255, 45, 85, 0.4);
            --text-main: #ffffff;
            --text-dim: #94a3b8;
        }

        body {
            background: radial-gradient(circle at top right, #1e1b4b, #0d0f14); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Inter', -apple-system, sans-serif; 
            overflow-x: hidden; 
            padding: 40px 0;
            color: #fff;
            margin: 0;
        }

        /* Hareketli Arka Plan Parçacıkları */
        .bg-bubbles { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: hidden; pointer-events: none; }
        .bg-bubbles li { position: absolute; list-style: none; display: block; width: 40px; height: 40px; background-color: rgba(255, 45, 85, 0.03); bottom: -160px; animation: square 25s infinite; transition-timing-function: linear; border-radius: 50%; }
        .bg-bubbles li:nth-child(1) { left: 10%; } .bg-bubbles li:nth-child(2) { left: 20%; width: 80px; height: 80px; animation-delay: 2s; animation-duration: 17s; } 
        @keyframes square { 0% { transform: translateY(0) rotate(0deg); opacity: 1; } 100% { transform: translateY(-1200px) rotate(600deg); opacity: 0; } }

        .register-card {
            background: var(--card-bg); 
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 32px; 
            padding: 50px; 
            width: 100%; 
            max-width: 600px; 
            z-index: 1; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }
        .register-card h2 { 
            color: #fff; 
            font-weight: 900; 
            letter-spacing: -1.5px; 
            text-align: center; 
            margin-bottom: 40px; 
            font-size: 2.2rem;
            text-shadow: 0 0 20px rgba(255,45,85,0.2);
        }
        .form-control, .form-select { 
            background: rgba(255, 255, 255, 0.05); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 16px; 
            color: #fff; 
            padding: 14px 18px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .form-control:focus, .form-select:focus { 
            background: rgba(255, 255, 255, 0.08); 
            box-shadow: 0 0 20px var(--accent-glow); 
            border-color: var(--accent);
            color: #fff; 
            transform: translateY(-2px);
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.3); }
        .btn-register { 
            background: var(--accent); 
            border: none; 
            border-radius: 16px; 
            color: white; 
            padding: 18px; 
            font-weight: 800; 
            letter-spacing: 1px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            width: 100%; 
            margin-top: 20px;
            box-shadow: 0 10px 25px var(--accent-glow);
        }
        .btn-register:hover { 
            transform: translateY(-5px) scale(1.02); 
            box-shadow: 0 15px 35px var(--accent-glow); 
            filter: brightness(1.2);
        }
        .label-custom { color: var(--text-dim); font-size: 0.75rem; margin-bottom: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; display: block; }
        .form-select { cursor: pointer; }
        .form-select option { background: #1a1b2e; color: #fff; }
        .login-link { color: var(--text-dim); text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: 0.3s; }
        .login-link:hover { color: var(--accent); text-shadow: 0 0 10px var(--accent-glow); }
    </style>
</head>
<body>

<ul class="bg-bubbles"><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li></ul>

<div class="register-card animate__animated animate__fadeInUp">
    <h2><i class="fa-solid fa-user-plus text-accent me-3"></i>HESAP OLUŞTUR</h2>
    <form method="POST">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="label-custom">Adınız</label>
                <input type="text" name="ad" class="form-control" placeholder="Örn: Umut" required>
            </div>
            <div class="col-md-6">
                <label class="label-custom">Soyadınız</label>
                <input type="text" name="soyad" class="form-control" placeholder="Örn: Kaya" required>
            </div>
            <div class="col-md-12">
                <label class="label-custom">E-Posta Adresi</label>
                <input type="email" name="email" class="form-control" placeholder="ornek@techlog.com" required>
            </div>
            <div class="col-md-6">
                <label class="label-custom">T.C. Kimlik Numarası</label>
                <input type="text" name="tc" class="form-control" maxlength="11" minlength="11" pattern="[0-9]{11}" title="Lütfen 11 haneli T.C. kimlik numaranızı girin" placeholder="11 haneli kimlik numarası" required>
            </div>
            <div class="col-md-6">
                <label class="label-custom">Telefon Numarası</label>
                <input type="tel" name="tel" class="form-control" maxlength="15" pattern="[0-9]{10,15}" title="Telefon numaranızı başında 0 ile bitişik yazınız" placeholder="Örn: 05551234567">
            </div>
            <div class="col-md-6">
                <label class="label-custom">Kullanıcı Rolü</label>
                <select name="rol" class="form-select" id="rolSelect" onchange="toggleExtraField()">
                    <option value="Öğrenci">Öğrenci</option>
                    <option value="Personel">Personel</option>
                </select>
            </div>
            <div class="col-md-6" id="extraField">
                <label class="label-custom" id="extraLabel">Okul Numarası</label>
                <input type="text" name="ek_no" class="form-control" placeholder="2024XXX">
            </div>
            <div class="col-md-12">
                <label class="label-custom">Güvenli Şifre</label>
                <input type="password" name="sifre" class="form-control" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" class="btn-register"><i class="fa-solid fa-rocket me-2"></i> KAYDI TAMAMLA</button>
        <div class="text-center mt-4">
            <a href="login.php" class="login-link">Zaten bir hesabınız var mı? Giriş Yapın &rarr;</a>
        </div>
    </form>
</div>

<script>
    function toggleExtraField() {
        const rol = document.getElementById('rolSelect').value;
        const label = document.getElementById('extraLabel');
        if (rol === 'Öğrenci') {
            label.innerText = 'OKUL NO';
        } else {
            label.innerText = 'SİCİL NO';
        }
    }

    <?php if(!empty($hata)): ?>
    Swal.fire({ icon: 'error', title: 'Hata!', text: '<?= $hata ?>', background: '#1e282c', color: '#fff' });
    <?php endif; ?>

    <?php if(!empty($basari)): ?>
    Swal.fire({ icon: 'success', title: 'Harika!', text: '<?= $basari ?>', background: '#1e282c', color: '#fff' }).then(() => {
        window.location.href = 'login.php';
    });
    <?php endif; ?>
</script>

</body>
</html>
