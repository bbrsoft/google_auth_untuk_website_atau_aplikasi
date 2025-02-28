<?php
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;
use Google\Service\PeopleService;

// Cek apakah parameter email tersedia
if (!isset($_GET['email'])) {
    die(json_encode(["error" => "Parameter email diperlukan."]));
}

$email = $_GET['email'];

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
$client->addScope(Gmail::GMAIL_READONLY);
$client->addScope(PeopleService::CONTACTS);
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

$gmailService = new Gmail($client);
$peopleService = new PeopleService($client);

try {
    // Ambil daftar email terbaru dari inbox
    $messages = $gmailService->users_messages->listUsersMessages("me", ['maxResults' => 10]);

    if (count($messages->getMessages()) == 0) {
        die(json_encode(["message" => "Tidak ada email yang ditemukan di inbox."]));
    }

    $newContacts = [];

    foreach ($messages->getMessages() as $message) {
        $emailData = $gmailService->users_messages->get("me", $message->getId());
        $headers = $emailData->getPayload()->getHeaders();
        $fromEmail = null;

        foreach ($headers as $header) {
            if ($header->getName() == "From") {
                if (preg_match('/<(.*?)>/', $header->getValue(), $matches)) {
                    $fromEmail = $matches[1];
                } else {
                    $fromEmail = $header->getValue();
                }
                break;
            }
        }

        if ($fromEmail) {
            // Cek apakah email sudah ada di kontak
            $existingContacts = $peopleService->people_connections->listPeopleConnections('people/me', ['personFields' => 'emailAddresses'])->getConnections();
            $contactExists = false;

            foreach ($existingContacts as $contact) {
                if (isset($contact->getEmailAddresses()[0]) && $contact->getEmailAddresses()[0]->getValue() === $fromEmail) {
                    $contactExists = true;
                    break;
                }
            }

            if (!$contactExists) {
                // Tambahkan kontak baru
                $newContact = new PeopleService\Person();
                $newContact->setEmailAddresses([new PeopleService\EmailAddress(["value" => $fromEmail])]);

                $peopleService->people->createContact($newContact);
                $newContacts[] = $fromEmail;
            }
        }
    }

    echo json_encode(["message" => "Kontak baru ditambahkan.", "contacts" => $newContacts]);

} catch (Exception $e) {
    die(json_encode(["error" => "Gagal mengambil atau menambahkan kontak: " . $e->getMessage()]));
}
?>
