<?php
// Floré – oddaja naročila gostincev
header('Content-Type: text/plain; charset=utf-8');

$to = "ouichef.co@gmail.com";

$ime       = isset($_POST['ime']) ? trim($_POST['ime']) : '';
$lokal     = isset($_POST['lokal']) ? trim($_POST['lokal']) : '';
$email     = isset($_POST['email']) ? trim($_POST['email']) : '';
$telefon   = isset($_POST['telefon']) ? trim($_POST['telefon']) : '';
$opomba    = isset($_POST['opomba']) ? trim($_POST['opomba']) : '';
$narocilo  = isset($_POST['narocilo']) ? $_POST['narocilo'] : '';
$tip       = isset($_POST['tip_narocila']) ? trim($_POST['tip_narocila']) : 'Naročilo po meri';

// odstrani prelome vrstic iz podatkov, ki se lahko pojavijo v glavi e-pošte
$ime     = preg_replace('/[\r\n]+/', ' ', $ime);
$lokal   = preg_replace('/[\r\n]+/', ' ', $lokal);
$email   = preg_replace('/[\r\n]+/', ' ', $email);
$telefon = preg_replace('/[\r\n]+/', ' ', $telefon);
$tip     = preg_replace('/[\r\n]+/', ' ', $tip);

if ($ime === '' || $lokal === '' || $email === '' || $telefon === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Manjkajo obvezni podatki.";
    exit;
}

$items = json_decode($narocilo, true);

if (!is_array($items) || empty($items)) {
    http_response_code(400);
    echo "Naročilo ni pravilno izpolnjeno.";
    exit;
}

/*
 * Cene se preverjajo na strežniku, zato uporabnik ne more spremeniti
 * cene naročila samo z urejanjem podatkov v brskalniku.
 */
$prices = [
    'Tiramisu' => 2.90,
    'Tiramisu jagoda' => 3.50,
    'Tiramisu čokolada' => 3.90,
    'Tiramisu pistacija' => 3.50,
];

$totalQty = 0;
$cleanItems = [];

foreach ($items as $item) {
    if (!is_array($item) || !isset($item['okus'], $item['kolicina'])) {
        continue;
    }

    $flavor = trim((string)$item['okus']);
    $qty = filter_var($item['kolicina'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!isset($prices[$flavor]) || $qty === false) {
        continue;
    }

    $totalQty += $qty;
    $cleanItems[] = [
        'okus' => $flavor,
        'kolicina' => $qty,
        'cena' => $prices[$flavor],
    ];
}

if ($totalQty < 12) {
    http_response_code(400);
    echo "Minimalno naročilo je 12 kozarčkov.";
    exit;
}

$discount = 0.00;
if ($totalQty >= 96) {
    $discount = 0.20;
} elseif ($totalQty >= 48) {
    $discount = 0.10;
}

/*
 * Testni paket je veljaven samo, če vsebuje točno 6 kosov vsakega okusa.
 * Njegova cena je 72,00 € brez DDV.
 */
$isStarter =
    $totalQty === 24 &&
    count($cleanItems) === 4;

if ($isStarter) {
    $expected = [
        'Tiramisu' => 6,
        'Tiramisu jagoda' => 6,
        'Tiramisu čokolada' => 6,
        'Tiramisu pistacija' => 6,
    ];

    foreach ($expected as $flavor => $expectedQty) {
        $found = 0;
        foreach ($cleanItems as $item) {
            if ($item['okus'] === $flavor) {
                $found = $item['kolicina'];
                break;
            }
        }
        if ($found !== $expectedQty) {
            $isStarter = false;
            break;
        }
    }
}

$priceTotal = $isStarter
    ? 72.00
    : array_reduce($cleanItems, function ($sum, $item) use ($discount) {
        return $sum + ($item['kolicina'] * max(0, $item['cena'] - $discount));
    }, 0.00);

$subject = "Floré – novo naročilo" . ($lokal !== '' ? " ($lokal)" : "");

$body  = "Novo naročilo s spletne strani Floré:\n\n";
$body .= "Vrsta naročila: " . ($isStarter ? "Testni paket" : "Naročilo po meri") . "\n";
$body .= "Ime in priimek: $ime\n";
$body .= "Naziv lokala: $lokal\n";
$body .= "E-pošta: $email\n";
$body .= "Telefon: $telefon\n\n";
$body .= "NAROČILO:\n";

foreach ($cleanItems as $item) {
    $linePrice = $item['kolicina'] * max(0, $item['cena'] - $discount);
    $body .= "- {$item['okus']}: {$item['kolicina']} × " . number_format(max(0, $item['cena'] - $discount), 2, ',', '.') . " € = " . number_format($linePrice, 2, ',', '.') . " €\n";
}

$body .= "\nSkupaj kozarčkov: $totalQty\n";
$body .= "Količinski popust: " . number_format($discount, 2, ',', '.') . " € / kos\n";
$body .= "Skupna cena brez DDV: " . number_format($priceTotal, 2, ',', '.') . " €\n";

if ($opomba !== '') {
    $body .= "\nOpomba:\n$opomba\n";
}

$headers  = "From: Floré spletna stran <no-reply@flore.si>\r\n";
$headers .= "Reply-To: $ime <$email>\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

if (mail($to, $subject, $body, $headers)) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Napaka pri pošiljanju.";
}
?>
