<?php
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

$credentialsPath = __DIR__ . "/credentials.json";
$tokenDir = __DIR__ . "/tokens/";

if (!isset($_GET['email'])) {
    die(json_encode(["error" => "Parameter email diperlukan."]));
}

$email = $_GET['email'];
$emailSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $email);
$tokenPath = $tokenDir . "token_{$emailSafe}.pkl";

if (!file_exists($tokenPath)) {
    die(json_encode(["error" => "Token tidak ditemukan untuk email ini."]));
}

$token = unserialize(file_get_contents($tokenPath));

$client = new Client();
$client->setAuthConfig($credentialsPath);
$client->addScope(Gmail::GMAIL_READONLY);
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
    // Menampilkan email hanya dari folder Spam
    $messages = $service->users_messages->listUsersMessages("me", ['q' => 'in:spam', 'maxResults' => 10]);

    if (count($messages->getMessages()) == 0) {
        die(json_encode(["message" => "Tidak ada email di folder spam."]));
    }

    $emails = [];
    foreach ($messages->getMessages() as $message) {
        $emailData = $service->users_messages->get("me", $message->getId());
        $headers = $emailData->getPayload()->getHeaders();
        
        $emailInfo = [
            "id" => $message->getId(),
            "snippet" => $emailData->getSnippet(),
        ];

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

    // Mengembalikan hasil dalam format JSON
    header('Content-Type: application/json');
    echo json_encode(["emails" => $emails]);

} catch (Exception $e) {
    die(json_encode(["error" => "Gagal mendapatkan email: " . $e->getMessage()]));
}
?>
