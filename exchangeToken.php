<?php
require 'vendor/autoload.php';

use Google\Client;

function exchangeAuthToken($authCode, $email) {
    $credentialsPath = __DIR__ . "/config/credentials.json";

    if (!file_exists($credentialsPath)) {
        return json_encode(["error" => "File credentials.json tidak ditemukan."]);
    }

    $client = new Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope("https://www.googleapis.com/auth/gmail.modify");

    $token = $client->fetchAccessTokenWithAuthCode($authCode);

    if (isset($token['error'])) {
        return json_encode(["error" => "Gagal mendapatkan token: " . $token['error']]);
    }

    // Simpan token dalam file
    $tokenFilename = "token_" . str_replace(['@', '.'], '_', $email) . ".json";
    file_put_contents($tokenFilename, json_encode($token));

    return json_encode(["message" => "Token berhasil disimpan untuk $email"]);
}

// Contoh penggunaan:
$authCode = $_GET['code'] ?? null;
$email = $_GET['email'] ?? null;

if (!$authCode || !$email) {
    echo json_encode(["error" => "Kode otorisasi dan email diperlukan."]);
    exit;
}

echo exchangeAuthToken($authCode, $email);
?>
