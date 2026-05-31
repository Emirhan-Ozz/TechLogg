<?php
session_start();
require_once 'db.php';
$hata = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $sifre = trim($_POST['sifre']);

    
    if ($email === 'admin' && $sifre === '1234') {
        $_SESSION['kullanici_id'] = 1;
        $_SESSION['ad_soyad'] = 'Umut (Admin)';
        $_SESSION['rol'] = 'Sistem Yöneticisi';
        header("Location: index.php");
        exit;
    }

    if (empty($email) || empty($sifre)) {
        $hata = "Lütfen tüm alanları doldurun!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM KULLANICI WHERE e_posta = ?");
        $stmt->execute([$email]);
        $kullanici = $stmt->fetch();

        if ($kullanici && $sifre === $kullanici['sifre_hash']) {
            $_SESSION['kullanici_id'] = $kullanici['kullanici_id'];
            $_SESSION['ad_soyad'] = $kullanici['ad'] . ' ' . $kullanici['soyad'];
            $_SESSION['rol'] = $kullanici['rol'];
            $_SESSION['unvan'] = $kullanici['unvan'];
            header("Location: index.php");
            exit;
        } else {
            $hata = "Hatalı giriş! Bilgilerini kontrol et.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechLog - Sisteme Giriş</title>
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
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Inter', -apple-system, sans-serif; 
            overflow: hidden;
            margin: 0;
            color: #fff;
        }

        .bg-bubbles { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: hidden; }
        .bg-bubbles li { position: absolute; list-style: none; display: block; width: 40px; height: 40px; background-color: rgba(255, 45, 85, 0.05); bottom: -160px; animation: square 25s infinite; transition-timing-function: linear; border-radius: 50%; }
        .bg-bubbles li:nth-child(1) { left: 10%; } .bg-bubbles li:nth-child(2) { left: 20%; width: 80px; height: 80px; animation-delay: 2s; animation-duration: 17s; } .bg-bubbles li:nth-child(3) { left: 25%; animation-delay: 4s; }
        .bg-bubbles li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-duration: 22s; background-color: rgba(255, 255, 255, 0.03); }
        .bg-bubbles li:nth-child(5) { left: 70%; animation-delay: 7s; }
        @keyframes square { 0% { transform: translateY(0) rotate(0deg); opacity: 1; } 100% { transform: translateY(-1000px) rotate(600deg); opacity: 0; } }

        
        .login-card {
            background: var(--card-bg); 
            backdrop-filter: blur(40px); 
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 32px; 
            padding: 60px 45px; 
            width: 100%; 
            max-width: 440px; 
            z-index: 1; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            text-align: center;
        }
        .login-card h2 { 
            color: #fff; 
            font-weight: 900; 
            letter-spacing: -1.5px; 
            margin-bottom: 40px; 
            font-size: 2.2rem;
            text-shadow: 0 0 20px rgba(255,45,85,0.2);
        }
        .form-control { 
            background: rgba(255, 255, 255, 0.05); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 16px; 
            color: #fff; 
            padding: 16px 20px; 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            font-size: 1rem;
        }
        .form-control:focus { 
            background: rgba(255, 255, 255, 0.08); 
            border-color: var(--accent); 
            box-shadow: 0 0 20px var(--accent-glow); 
            color: #fff; 
            transform: translateY(-2px);
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.3); }
        .input-icon { position: absolute; right: 20px; top: 18px; color: rgba(255,255,255,0.2); transition: 0.3s; }
        .form-control:focus + .input-icon { color: var(--accent); text-shadow: 0 0 10px var(--accent-glow); }
        
        .btn-login { 
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
        .btn-login:hover { 
            transform: translateY(-5px) scale(1.02); 
            box-shadow: 0 15px 35px var(--accent-glow); 
            filter: brightness(1.2); 
        }
        .register-link { color: var(--text-dim); text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: 0.3s; }
        .register-link:hover { color: var(--accent); text-shadow: 0 0 10px var(--accent-glow); }
    </style>
</head>
<body>

<ul class="bg-bubbles"><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li></ul>

<div class="login-card animate__animated animate__fadeInUp">
    <h2><i class="fa-solid fa-microchip text-accent me-3"></i>TECHLOG</h2>
    <form method="POST" action="">
        <div class="mb-4 position-relative">
            <input type="text" name="email" class="form-control" placeholder="E-posta veya Kullanıcı Adı" value="admin" required>
            <i class="fa-solid fa-user input-icon"></i>
        </div>
        <div class="mb-5 position-relative">
            <input type="password" name="sifre" class="form-control" placeholder="Şifre" value="1234" required>
            <i class="fa-solid fa-lock input-icon"></i>
        </div>
        <button type="submit" class="btn-login"><i class="fa-solid fa-right-to-bracket me-2"></i> PANEL GİRİŞİ</button>
        <div class="text-center mt-5">
            <a href="register.php" class="register-link">Yeni bir hesap oluşturun &rarr;</a>
        </div>
    </form>
</div>

<?php if(!empty($hata)): ?>
<script>
    Swal.fire({ icon: 'error', title: 'Hoppala!', text: '<?= $hata ?>', background: '#1e282c', color: '#fff', confirmButtonColor: '#e74c3c' });
</script>
<?php endif; ?>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'registered'): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Kayıt Başarılı!', text: 'Hesabınız başarıyla oluşturuldu, sisteme giriş yapabilirsiniz.', background: '#1e282c', color: '#fff', confirmButtonColor: '#2ecc71' });
</script>
<?php endif; ?>

</body>
</html>