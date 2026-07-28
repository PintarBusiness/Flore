<?php
// Floré – povpraševanje gostincev
header('Content-Type: text/plain; charset=utf-8');

$to = "ouichef.co@gmail.com"; // TODO: potrdi/zamenjaj s pravim naslovom za Floré

$ime      = isset($_POST['ime']) ? trim($_POST['ime']) : '';
$lokal    = isset($_POST['lokal']) ? trim($_POST['lokal']) : '';
$email    = isset($_POST['email']) ? trim($_POST['email']) : '';
$telefon  = isset($_POST['telefon']) ? trim($_POST['telefon']) : '';
$sporocilo = isset($_POST['sporocilo']) ? trim($_POST['sporocilo']) : '';

if ($ime === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Manjkajo obvezni podatki.";
    exit;
}

$subject = "Floré – novo povpraševanje" . ($lokal !== '' ? " ($lokal)" : "");

$body  = "Novo povpraševanje s spletne strani Floré:\n\n";
$body .= "Ime in priimek: $ime\n";
$body .= "Naziv lokala: " . ($lokal !== '' ? $lokal : '-') . "\n";
$body .= "E-pošta: $email\n";
$body .= "Telefon: " . ($telefon !== '' ? $telefon : '-') . "\n";
$body .= "Sporočilo:\n" . ($sporocilo !== '' ? $sporocilo : '-') . "\n";

$headers = "From: Floré spletna stran <no-reply@ouichef.si>\r\n";
$headers .= "Reply-To: $ime <$email>\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

if (mail($to, $subject, $body, $headers)) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Napaka pri pošiljanju.";
}
