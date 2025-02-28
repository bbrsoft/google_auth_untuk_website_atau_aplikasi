<?php
header('Content-Type: application/json');
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

$credentialsPath = __DIR__ . "/credentials.json";
$tokenDir = __DIR__ . "/tokens/";

if (!isset($_GET['email']) || !isset($_GET['from'])) {
    echo json_encode(["error" => "Parameter email dan from diperlukan."]);
    exit;
}

$email = $_GET['email'];
$from = $_GET['from'];

$emailSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $email);
$tokenPath = $tokenDir . "token_{$emailSafe}.pkl";

if (!file_exists($tokenPath)) {
    echo json_encode(["error" => "Token tidak ditemukan untuk email ini."]);
    exit;
}

$token = unserialize(file_get_contents($tokenPath));

$client = new Client();
$client->setAuthConfig($credentialsPath);
$client->addScope(Gmail::GMAIL_MODIFY);
$client->setAccessToken($token);

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($tokenPath, serialize($newToken));
        $client->setAccessToken($newToken);
    } else {
        echo json_encode(["error" => "Token kedaluwarsa dan tidak dapat diperbarui."]);
        exit;
    }
}

$service = new Gmail($client);

try {
    // Ambil daftar email berdasarkan pengirim
    $messages = $service->users_messages->listUsersMessages("me", ['q' => 'from:' . $from, 'maxResults' => 50]);

    if (count($messages->getMessages()) == 0) {
        echo json_encode(["error" => "Tidak ada email ditemukan dari pengirim ini."]);
        exit;
    }

    $deletedEmails = [];
    foreach ($messages->getMessages() as $message) {
        $service->users_messages->delete("me", $message->getId());
        $deletedEmails[] = $message->getId();
    }

    echo json_encode(["success" => "Email berhasil dihapus.", "deleted_emails" => $deletedEmails]);
} catch (Exception $e) {
    echo json_encode(["error" => "Gagal menghapus email: " . $e->getMessage()]);
}
?>
