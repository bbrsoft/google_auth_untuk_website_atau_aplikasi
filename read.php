<?php
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

$credentialsPath = __DIR__ . "/credentials.json";
$tokenDir = __DIR__ . "/tokens/";

// Pastikan email diberikan
if (!isset($_GET['email'])) {
    die("Parameter email diperlukan.");
}

$email = $_GET['email'];
$emailSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $email);
$tokenPath = $tokenDir . "token_{$emailSafe}.pkl";

// Pastikan token tersedia
if (!file_exists($tokenPath)) {
    die("Token tidak ditemukan untuk email ini.");
}

// **Muat token dari file**
$token = unserialize(file_get_contents($tokenPath));

$client = new Client();
$client->setAuthConfig($credentialsPath);
$client->addScope(Gmail::GMAIL_READONLY);
$client->setAccessToken($token);

// **Periksa apakah token kedaluwarsa**
if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($tokenPath, serialize($newToken));
        $client->setAccessToken($newToken);
    } else {
        die("Token kedaluwarsa dan tidak dapat diperbarui.");
    }
}

// **Buat layanan Gmail**
$service = new Gmail($client);

try {
    $messages = $service->users_messages->listUsersMessages("me", ['maxResults' => 10]);

    if (count($messages->getMessages()) == 0) {
        die("Tidak ada email ditemukan.");
    }

    $emails = [];
    foreach ($messages->getMessages() as $message) {
        $emailData = $service->users_messages->get("me", $message->getId());
        $headers = $emailData->getPayload()->getHeaders();
        
        $emailInfo = [
            "id" => $message->getId(),
            "snippet" => $emailData->getSnippet(),
        ];

        // Ambil informasi pengirim dan subjek
        foreach ($headers as $header) {
            if ($header->getName() == "From") {
                $emailInfo["from"] = $header->getValue();
            }
            if ($header->getName() == "Subject") {
                $emailInfo["subject"] = $header->getValue();
            }
        }

        $emails[] = $emailInfo;
    }

    // **Tampilkan dalam HTML**
    echo "<html><head><title>Daftar Email</title></head><body>";
    echo "<h2>Daftar Email</h2>";
    echo "<table border='1' cellpadding='10' cellspacing='0'>";
    echo "<tr><th>Pengirim</th><th>Subjek</th><th>Cuplikan</th><th>Aksi</th></tr>";
    
    foreach ($emails as $email) {
        echo "<tr>";
        echo "<td>{$email['from']}</td>";
        echo "<td>{$email['subject']}</td>";
        echo "<td>{$email['snippet']}</td>";
        echo "<td><a href='?email={$emailSafe}&messageId={$email['id']}'>Lihat Isi</a></td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</body></html>";

} catch (Exception $e) {
    die("Gagal mendapatkan email: " . $e->getMessage());
}

// **Tampilkan isi email jika messageId diberikan**
if (isset($_GET['messageId'])) {
    $messageId = $_GET['messageId'];

    try {
        $emailData = $service->users_messages->get("me", $messageId);
        $payload = $emailData->getPayload();
        $headers = $payload->getHeaders();
        $body = "";

        // Cek apakah email memiliki bagian teks atau HTML
        if ($payload->getBody() && $payload->getBody()->getData()) {
            $body = base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
        } else {
            foreach ($payload->getParts() as $part) {
                if ($part->getMimeType() == "text/plain") {
                    $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    break;
                }
            }
        }

        echo "<h2>Detail Email</h2>";
        foreach ($headers as $header) {
            if ($header->getName() == "From") {
                echo "<p><strong>From:</strong> " . $header->getValue() . "</p>";
            }
            if ($header->getName() == "Subject") {
                echo "<p><strong>Subject:</strong> " . $header->getValue() . "</p>";
            }
        }
        echo "<p><strong>Body:</strong><br>" . nl2br(htmlspecialchars($body)) . "</p>";

    } catch (Exception $e) {
        die("Gagal membaca email: " . $e->getMessage());
    }
}
