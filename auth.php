<?php
session_start();
require 'vendor/autoload.php';

use Google\Client;

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
$client->setPrompt("consent");

// Simpan email di session jika dikirim dari frontend
if (isset($_GET['email'])) {
    $_SESSION['email'] = $_GET['email'];
}

// Redirect ke Google OAuth
$redirectUri = "https://kerjasama.online/authcallback/callback.php";
$client->setRedirectUri($redirectUri);

$authUrl = $client->createAuthUrl();
header("Location: " . $authUrl);
echo json_encode(["auth_url" => $authUrl, "message" => "Silakan buka URL ini di browser untuk login"]);
?>
