<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['response' => 'Buraya doğrudan erişemezsin kanka!']);
    exit;
}


$user_message = trim($_POST['message'] ?? '');
$kullanici_id = $_SESSION['kullanici_id'] ?? 0;

if (empty($user_message)) {
    echo json_encode(['response' => 'Lütfen geçerli bir soru sorun kanka.']);
    exit;
}

// --- ARIZA KAPATMA MANTIĞI ---
if ($kullanici_id > 0) {
    $devices_stmt = $pdo->prepare("SELECT cihaz_id, marka_model, qr_kod_id FROM KULLANICI_CIHAZLARI WHERE kullanici_id = ?");
    $devices_stmt->execute([$kullanici_id]);
    $user_devices = $devices_stmt->fetchAll();

    $found_device_id = null;
    $found_device_name = "";
    $is_solved_intent = (bool)preg_match('/(çözüldü|tamam|bitti|halloldu|hallettim|düzelti)/iu', $user_message);

    if ($is_solved_intent) {
        if (preg_match('/(TECHLOG-QR-[A-Z0-9]+)/i', $user_message, $matches)) {
            $qr_code = strtoupper($matches[1]);
            foreach ($user_devices as $d) {
                if (strtoupper($d['qr_kod_id']) == $qr_code) {
                    $found_device_id = $d['cihaz_id'];
                    $found_device_name = $d['marka_model'];
                    break;
                }
            }
        }
        if (!$found_device_id) {
            foreach ($user_devices as $d) {
                if (stripos($user_message, $d['marka_model']) !== false) {
                    $found_device_id = $d['cihaz_id'];
                    $found_device_name = $d['marka_model'];
                    break;
                }
            }
        }
        if ($found_device_id) {
            $stmt = $pdo->prepare("UPDATE ARIZA SET durum = 'Çözüldü' WHERE kullanici_id = ? AND cihaz_id = ? AND durum = 'Bekliyor' ORDER BY ariza_id DESC LIMIT 1");
            $stmt->execute([$kullanici_id, $found_device_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['response' => "Kanka harika! <b>{$found_device_name}</b> cihazın için arıza kaydını 'ÇÖZÜLDÜ' olarak güncelledim. 🚀"]);
                exit;
            }
        }
    }
}


$api_key = '';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $api_key;

$data = [
    "contents" => [[
        "role" => "user",
        "parts" => [["text" => $user_message]]
    ]],
    "system_instruction" => [
        "parts" => [["text" => "Sen TechLog IT yönetim sisteminin resmi asistanısın. Adın 'TechLog Bot'. Eğlenceli, samimi ('kanka', 'dostum' diyebilirsin) ama profesyonel bir dil kullan. SADECE IT sorunları, bilgisayar donanımı, ağ (network), internet kesintileri, yazılım hataları ve siber güvenlik hakkında destek ver. Kullanıcı fan ısınması gibi donanımsal sorunlar yazarsa, macun yenileme, temizlik ve sıcaklık takibi gibi profesyonel tavsiyeler ver. Eğer kullanıcı sana tarih, coğrafya, yemek tarifi veya IT dışı alakasız bir soru sorarsa, 'Kanka ben sadece IT işlerine bakıyorum, sunucu odasından çıkamam. Teknolojiyle ilgili bir sorunun varsa sorabilirsin!' diyerek konuyu kapat. Cevaplarını çok uzun tutma, anlaşılır ol."]]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['response' => 'Sunucuya bağlanırken bir hata oluştu kanka: ' . $err]);
    exit;
}

$responseData = json_decode($response, true);

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $bot_reply = $responseData['candidates'][0]['content']['parts'][0]['text'];
    $bot_reply = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $bot_reply);
    $bot_reply = preg_replace('/\*(.*?)\*/', '<i>$1</i>', $bot_reply);
    $bot_reply = str_replace("\n", "<br>", $bot_reply);
    echo json_encode(['response' => $bot_reply]);
} else {
    $status = $responseData['error']['status'] ?? '';
    if ($status === 'RESOURCE_EXHAUSTED') {
        echo json_encode(['response' => "Kanka şu an beynim biraz yandı, çok fazla soru sordun! 🧠⚡ 30 saniye sonra tekrar dener misin?"]);
    } else {
        echo json_encode(['response' => "Kanka şu an bir bağlantı sorunu var, birazdan tekrar dene! 🦾"]);
    }
}