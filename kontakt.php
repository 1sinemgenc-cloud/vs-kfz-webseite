<?php
/* ===================================================================
   ANFRAGEFORMULAR – verschickt jede Anfrage als E-Mail
   Nur diese zwei Zeilen anpassen:
   =================================================================== */
$EMPFAENGER = 'info@ihre-domain.de';   // hier kommen die Anfragen an
$ABSENDER   = 'webseite@ihre-domain.de'; // eine Adresse eurer eigenen Domain (muss nicht als Postfach existieren)
$FIRMA      = 'SV Sachverständiger';
$TELEFON    = '0000 / 000 000 00';
$BESTAETIGUNG_AN_KUNDEN = true;          // Kunde bekommt automatisch eine Eingangsbestätigung

/* ---------- ab hier nichts ändern nötig ---------- */
date_default_timezone_set('Europe/Berlin');
header('Content-Type: application/json; charset=utf-8');

function antwort($ok, $text = '') { echo json_encode(['ok' => $ok, 'text' => $text]); exit; }
function feld($name) {
  $v = isset($_POST[$name]) ? trim((string)$_POST[$name]) : '';
  $v = str_replace(["\r", "\0"], '', $v);
  return mb_substr($v, 0, 3000);
}
function zeile($v) { return str_replace("\n", ' ', $v); } // keine Zeilenumbrüche in Kopfzeilen

if ($_SERVER['REQUEST_METHOD'] !== 'POST') antwort(false, 'Ungültiger Aufruf.');
if (feld('_gotcha') !== '') antwort(true); // Spam-Schutz: Bots füllen das unsichtbare Feld aus

foreach (['Vorname', 'Nachname', 'Strasse', 'PLZ', 'Ort', 'Telefon', 'E-Mail', 'Hersteller', 'Modell', 'Kennzeichen', 'Beschreibung'] as $pflicht) {
  if (feld($pflicht) === '') antwort(false, 'Bitte alle Pflichtfelder ausfüllen.');
}
$kundenmail = filter_var(feld('E-Mail'), FILTER_VALIDATE_EMAIL);
if (!$kundenmail) antwort(false, 'Bitte eine gültige E-Mail-Adresse angeben.');

$abschnitte = [
  'GUTACHTEN'    => ['Gutachtenart'],
  'KUNDE'        => ['Vorname', 'Nachname', 'Strasse', 'PLZ', 'Ort', 'Telefon', 'E-Mail', 'Erreichbarkeit', 'Anwalt'],
  'FAHRZEUG'     => ['Hersteller', 'Modell', 'Kennzeichen', 'Erstzulassung', 'Kilometerstand', 'Fahrbereit', 'Halter', 'Vorsteuerabzug'],
  'SCHADEN'      => ['Schadendatum', 'Schadenort', 'Schuldfrage', 'Kennzeichen Gegner', 'Versicherung Gegner', 'Schadennummer', 'Polizei', 'Abtretung', 'Beschreibung'],
  'BESICHTIGUNG' => ['Besichtigungsort', 'Besichtigungsadresse', 'Wunschtermin', 'Uhrzeit'],
];
// PHP wandelt Leerzeichen und Bindestriche in Feldnamen in "_" um
function wert($name) { return feld(str_replace([' ', '-'], '_', $name)) ?: feld($name); }

$name  = zeile(wert('Vorname') . ' ' . wert('Nachname'));
$text  = "Neue Gutachten-Anfrage über die Webseite\n";
$text .= "Eingegangen am " . date('d.m.Y \u\m H:i') . " Uhr\n";
foreach ($abschnitte as $titel => $felder) {
  $teil = '';
  foreach ($felder as $f) {
    $v = wert($f);
    if ($f === 'Kennzeichen' || $f === 'Kennzeichen Gegner') $v = mb_strtoupper($v);
    if ($v !== '') $teil .= str_pad($f . ':', 22) . str_replace("\n", "\n" . str_repeat(' ', 22), $v) . "\n";
  }
  if ($teil !== '') $text .= "\n— " . $titel . " —\n" . $teil;
}
$text .= "\nTipp: Einfach auf „Antworten“ klicken – die Antwort geht direkt an den Kunden.\n";

$betreff = 'Neue Anfrage: ' . zeile(wert('Gutachtenart')) . ' – ' . $name . ' – ' . zeile(mb_strtoupper(wert('Kennzeichen')));
$kopf  = "From: " . $FIRMA . " Webseite <" . $ABSENDER . ">\r\n";
$kopf .= "Reply-To: " . $name . " <" . $kundenmail . ">\r\n";
$kopf .= "Content-Type: text/plain; charset=UTF-8\r\n";

$gesendet = mail($EMPFAENGER, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $text, $kopf, '-f' . $ABSENDER);
if (!$gesendet) antwort(false, 'Die Anfrage konnte nicht gesendet werden.');

if ($BESTAETIGUNG_AN_KUNDEN) {
  $b  = "Guten Tag " . $name . ",\n\n";
  $b .= "vielen Dank für Ihre Anfrage. Sie ist bei uns eingegangen, wir melden uns in der Regel noch am selben Werktag zur Terminvereinbarung.\n\n";
  $b .= "Fotos vom Schaden können Sie uns gern als Antwort auf diese E-Mail schicken.\n\n";
  $b .= "Bei Fragen erreichen Sie uns unter " . $TELEFON . ".\n\n";
  $b .= "Freundliche Grüße\n" . $FIRMA . "\n";
  $kb  = "From: " . $FIRMA . " <" . $ABSENDER . ">\r\nReply-To: " . $EMPFAENGER . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
  @mail($kundenmail, '=?UTF-8?B?' . base64_encode('Ihre Anfrage bei ' . $FIRMA) . '?=', $b, $kb, '-f' . $ABSENDER);
}
antwort(true);
