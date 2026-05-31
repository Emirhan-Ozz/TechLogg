<?php
require_once 'db.php';
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wifi_type'])) {
    $type = $_POST['wifi_type'];
    $ad = $_POST['ad'] ?? '';
    $soyad = $_POST['soyad'] ?? '';
    $tc = $_POST['tc'] ?? '';
    $tel = $_POST['tel'] ?? '';
    $no = $_POST['extra_no'] ?? '';
    $mac = $_POST['mac'] ?? '';

    if ($type !== 'guest') {

        $baslik = "[AĞ BAŞVURUSU] - $ad $soyad";
        $detay = "Tip: $type\nTC: $tc\nTel: $tel\nNo: $no\nMAC: $mac";
        
        try {
            $stmt = $pdo->prepare("INSERT INTO ARIZA (baslik, aciklama, oncelik, durum) VALUES (?, ?, 'ORTA', 'Bekliyor')");
            $stmt->execute([$baslik, $detay]);
            $msg = "success";
        } catch (Exception $e) {
            $msg = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechLog - Güvenli İnternet Ağı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
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
            margin: 0;
            padding: 20px;
            color: var(--text-main);
        }

        .wifi-container {
            width: 100%;
            max-width: 500px;
            z-index: 10;
        }

        .wifi-card {
            background: var(--card-bg);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 32px;
            padding: 50px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            transition: all 0.4s ease;
        }

        .wifi-card h2 {
            font-weight: 900;
            letter-spacing: -1.5px;
            margin-bottom: 30px;
            text-align: center;
            color: #fff;
            text-shadow: 0 0 20px rgba(255,45,85,0.2);
        }

        .wifi-form-label {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-dim);
            margin-bottom: 10px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .wifi-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 16px 20px;
            color: #fff;
            width: 100%;
            margin-bottom: 25px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }

        .wifi-input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent);
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .wifi-select {
            appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23ff2d55'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 18px;
        }

        .wifi-select option {
            background: #1a1b2e;
            color: #fff;
        }

        .wifi-btn {
            background: var(--accent);
            border: none;
            border-radius: 16px;
            padding: 18px;
            color: #fff;
            font-weight: 800;
            letter-spacing: 1px;
            width: 100%;
            margin-top: 15px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px var(--accent-glow);
        }

        .wifi-btn:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px var(--accent-glow);
            filter: brightness(1.2);
        }

        #guestResult {
            display: none;
            background: rgba(255, 45, 85, 0.05);
            border: 1px dashed var(--accent);
            border-radius: 24px;
            padding: 30px;
            margin-top: 30px;
            text-align: center;
            animation: slideInUp 0.6s ease;
        }

        .password-display {
            font-family: 'Inter', monospace;
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--accent);
            margin: 20px 0;
            filter: blur(10px);
            cursor: pointer;
            transition: all 0.5s ease;
            user-select: none;
            text-shadow: 0 0 15px var(--accent-glow);
        }

        .password-display:hover {
            filter: blur(0);
            letter-spacing: 4px;
        }

        .validity-text {
            font-size: 0.8rem;
            color: var(--text-dim);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .legal-check {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            font-size: 0.85rem;
            color: var(--text-dim);
            cursor: pointer;
            background: rgba(255,255,255,0.03);
            padding: 12px 20px;
            border-radius: 12px;
            transition: 0.3s;
        }
        .legal-check:hover { background: rgba(255,255,255,0.06); color: #fff; }

        .legal-check input {
            width: 20px;
            height: 20px;
            accent-color: var(--accent);
            cursor: pointer;
        }
    </style>

        /* Responsive */
        @media (max-width: 480px) {
            .wifi-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>

<div class="wifi-container">
    <div class="wifi-card animate__animated animate__zoomIn">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-accent bg-opacity-10 rounded-circle mb-4" style="width:80px; height:80px; box-shadow: 0 0 30px var(--accent-glow);">
                <i class="fa-solid fa-wifi text-accent fs-1"></i>
            </div>
            <h2>TechLog Güvenli İnternet</h2>
        </div>

        <form id="wifiForm" method="POST">
            <label class="wifi-form-label">Kullanıcı Tipi</label>
            <select class="wifi-input wifi-select" id="userType" name="wifi_type" onchange="toggleFields()">
                <option value="guest" selected>Misafir</option>
                <option value="student">Öğrenci</option>
                <option value="staff">Öğretim Görevlisi / Personel</option>
            </select>

            <div class="row g-2">
                <div class="col-6">
                    <label class="wifi-form-label">Ad</label>
                    <input type="text" name="ad" class="wifi-input" placeholder="Ahmet" required>
                </div>
                <div class="col-6">
                    <label class="wifi-form-label">Soyad</label>
                    <input type="text" name="soyad" class="wifi-input" placeholder="Yılmaz" required>
                </div>
            </div>

            <label class="wifi-form-label">T.C. Kimlik No</label>
            <input type="text" name="tc" class="wifi-input" maxlength="11" placeholder="11122233344" required>

            <label class="wifi-form-label">Telefon</label>
            <input type="tel" name="tel" class="wifi-input" placeholder="05XX XXX XX XX" required>

           
            <div id="studentFields">
                <label class="wifi-form-label">Öğrenci No</label>
                <input type="text" name="extra_no" id="studNo" class="wifi-input" placeholder="202410XX">
            </div>

            <div id="staffFields" style="display: none;">
                <label class="wifi-form-label">Sicil No</label>
                <input type="text" name="extra_no_staff" id="staffNo" class="wifi-input" placeholder="BT-998877">
            </div>

            <div id="macArea">
                <label class="wifi-form-label">Cihaz MAC Adresi</label>
                <input type="text" id="macInput" name="mac" class="wifi-input" placeholder="00:00:00:00:00:00" maxlength="17">
            </div>

            <label class="legal-check">
                <input type="checkbox" required>
                <span>5651 Sayılı Kanun gereği log kayıtlarımın tutulmasını onaylıyorum.</span>
            </label>

            <button type="submit" class="wifi-btn" id="submitBtn">ERİŞİMİ BAŞLAT</button>
        </form>

      
        <div id="guestResult">
            <h6 class="fw-bold mb-2">Wi-Fi Giriş Bilgileriniz</h6>
            <div class="validity-text">Ağ Adı: TechLog_Secure_Guest</div>
            <div class="password-display" id="generatedPass" title="Görmek için üzerine gel">WIFI-8822</div>
            <div class="validity-text"><i class="fa-regular fa-clock me-1"></i> Geçerlilik Süresi: 24 Saat</div>
            <small class="text-info mt-2 d-block" style="font-size: 10px;">Lütfen bu şifreyi not ediniz.</small>
        </div>
    </div>
</div>

<script>
   
    function toggleFields() {
        const type = document.getElementById('userType').value;
        const studentFields = document.getElementById('studentFields');
        const staffFields = document.getElementById('staffFields');
        const macArea = document.getElementById('macArea');
        const submitBtn = document.getElementById('submitBtn');
        const guestResult = document.getElementById('guestResult');

        
        studentFields.style.display = 'none';
        staffFields.style.display = 'none';
        macArea.style.display = 'block';
        submitBtn.innerText = 'ERİŞİMİ BAŞLAT';
        guestResult.style.display = 'none';

        if (type === 'student') {
            studentFields.style.display = 'block';
        } else if (type === 'staff') {
            staffFields.style.display = 'block';
        } else if (type === 'guest') {
            macArea.style.display = 'none';
            submitBtn.innerText = 'ŞİFRE AL';
        }
    }

    
    document.getElementById('macInput').addEventListener('input', function (e) {
        let value = e.target.value.toUpperCase().replace(/[^0-9A-F]/g, '');
        let formatted = "";
        
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 2 === 0) formatted += ":";
            formatted += value[i];
        }
        
        e.target.value = formatted.substring(0, 17);
    });

    
    <?php if ($msg == 'success'): ?>
        Swal.fire({
            title: 'Başvuru Alındı!',
            text: 'WiFi başvuru talebiniz IT birimine iletildi. Onay sonrası internetiniz açılacaktır.',
            icon: 'success',
            background: '#1e293b',
            color: '#fff',
            confirmButtonColor: '#38bdf8'
        });
    <?php endif; ?>

    document.getElementById('wifiForm').onsubmit = function(e) {
        const type = document.getElementById('userType').value;

        if (type === 'guest') {
            e.preventDefault();
            
            const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
            let pass = "WIFI-";
            for (let i = 0; i < 4; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
            
            document.getElementById('generatedPass').innerText = pass;
            document.getElementById('guestResult').style.display = 'block';
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
        
    };
    toggleFields();
</script>

</body>
</html>
