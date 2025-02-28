<?php
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

// Cek apakah parameter yang dibutuhkan tersedia
if (!isset($_GET['email']) || !isset($_GET['from']) || !isset($_GET['content'])) {
    die(json_encode(["error" => "Parameter email, from, dan content diperlukan."]));
}

$email = $_GET['email'];
$fromEmail = $_GET['from'];
$contentFilePath = $_GET['content'];

// Pastikan file content_mail_auto.txt ada
if (!file_exists($contentFilePath)) {
    die(json_encode(["error" => "File content_mail_auto.txt tidak ditemukan."]));
}

// Baca isi balasan dari file
$replyContent = file_get_contents($contentFilePath);

// Konfigurasi OAuth2
$credentialsPath = __DIR__ . "/credentials.json";
$tokenDir = __DIR__ . "/tokens/";
$emailSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $email);
$tokenPath = $tokenDir . "token_{$emailSafe}.pkl";

if (!file_exists($tokenPath)) {
    die(json_encode(["error" => "Token tidak ditemukan untuk email ini."]));
}

$token = unserialize(file_get_contents($tokenPath));

$client = new Client();
$client->setAuthConfig($credentialsPath);
$client->addScope(Gmail::MAIL_GOOGLE_COM);
$client->setAccessToken($token);

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($tokenPath, serialize($newToken));
        $client->setAccessToken($newToken);
    } else {
        die(json_encode(["error" => "Token kedaluwarsa dan tidak dapat diperbarui."]));
    }
}

$service = new Gmail($client);

try {
    // Ambil email terbaru dari pengirim tertentu
    $messages = $service->users_messages->listUsersMessages("me", ['q' => "from:$fromEmail", 'maxResults' => 1]);

    if (count($messages->getMessages()) == 0) {
        die(json_encode(["message" => "Tidak ada email dari $fromEmail yang perlu dibalas."]));
    }

    foreach ($messages->getMessages() as $message) {
        $emailData = $service->users_messages->get("me", $message->getId());
        $headers = $emailData->getPayload()->getHeaders();
        
        $subject = "Re: (Auto Reply)";
        
        foreach ($headers as $header) {
            if ($header->getName() == "Subject") {
                $subject = "Re: " . $header->getValue();
                break;
            }
        }

        // Format isi email balasan
        $emailContent = "To: $fromEmail\r\n" .
                        "Subject: $subject\r\n" .
                        "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
                        $replyContent;

        $encodedMessage = base64_encode($emailContent);
        $encodedMessage = str_replace(['+', '/', '='], ['-', '_', ''], $encodedMessage); // Format Gmail API

        $msg = new Google\Service\Gmail\Message();
        $msg->setRaw($encodedMessage);

        $service->users_messages->send("me", $msg);

        echo json_encode(["message" => "Balasan terkirim ke $fromEmail"]);
        exit;
    }

    echo json_encode(["message" => "Tidak ada email yang perlu dibalas."]);

} catch (Exception $e) {
    die(json_encode(["error" => "Gagal membalas email: " . $e->getMessage()]));
}
?>
