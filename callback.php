<?php
session_start();
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Oauth2;

header('Content-Type: application/json');

$credentialsPath = __DIR__ . "/credentials.json";

if (!file_exists($credentialsPath)) {
    echo json_encode(["error" => "File credentials.json tidak ditemukan."]);
    exit;
}

$client = new Client();
$client->setAuthConfig($credentialsPath);
$client->addScope("https://www.googleapis.com/auth/gmail.modify");
$client->addScope("https://www.googleapis.com/auth/userinfo.email");
$client->setAccessType("offline");

if (!isset($_GET['code'])) {
    echo json_encode(["error" => "Kode otorisasi tidak ditemukan."]);
    exit;
}

$code = $_GET['code'];

try {
    $token = $client->fetchAccessTokenWithAuthCode($code);
    if (isset($token['error'])) {
        echo json_encode(["error" => "Gagal mendapatkan token: " . $token['error']]);
        exit;
    }

    $client->setAccessToken($token);
    $oauth2 = new Oauth2($client);
    $userInfo = $oauth2->userinfo->get();
    $email = $userInfo->email;

    if (!$email) {
        echo json_encode(["error" => "Gagal mendapatkan email pengguna."]);
        exit;
    }

    // Gunakan email dari session jika tersedia
    if (isset($_SESSION['email'])) {
        $sessionEmail = $_SESSION['email'];
    } else {
        $sessionEmail = $email; // Default gunakan email dari Google
    }

    // Simpan token dalam format PKL (Pickle)
    $tokenDir = __DIR__ . "/tokens/";
    if (!file_exists($tokenDir)) {
        mkdir($tokenDir, 0777, true);
    }

    $emailSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sessionEmail);
    $tokenPklPath = $tokenDir . "token_{$emailSafe}.pkl";
    $pickleData = serialize($token);
    file_put_contents($tokenPklPath, $pickleData);

    echo json_encode([
        "message" => "Autentikasi berhasil, token disimpan dalam format PKL",
        "email" => $sessionEmail,
        // "token_file" => $tokenPklPath
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => "Terjadi kesalahan: " . $e->getMessage()]);
}
?>
