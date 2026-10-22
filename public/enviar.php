<?php
declare(strict_types=1);

/**
 * Formulario de landings KonscioHousing. Sin mail(); SMTP autenticado.
 * No colocar credenciales en public/ ni en Git.
 */

use PHPMailer\PHPMailer\PHPMailer;

function jsonRequested(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function finish(bool $ok, string $returnPath, int $status = 200): never
{
    header('Cache-Control: no-store');
    http_response_code($status);
    if (jsonRequested()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok], JSON_THROW_ON_ERROR);
    } else {
        header('Location: ' . $returnPath . ($ok ? '?enviado=1' : '?error=1') . '#f', true, 303);
    }
    exit;
}

function cleanField(string $name, int $max = 250, bool $multiline = false): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = strip_tags($value);
    $value = str_replace("\0", '', $value);
    if (!$multiline) {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
    } else {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $value) ?? '';
    }
    return trim(substr($value, 0, $max));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Método no permitido';
    exit;
}

$origin = cleanField('origen', 80);
$landing = match ($origin) {
    'Socios Industriales' => '/socios-industriales/',
    'Programa de Terrenos' => '/terrenos/',
    default => '/terrenos/',
};

// El cliente conserva honeypot invisible. Evita entregar confirmación a bots.
if (cleanField('web') !== '') {
    finish(true, $landing);
}

// Origin es informativo, pero solo aceptamos formularios de landings conocidas.
if (!in_array($origin, ['Programa de Terrenos', 'Socios Industriales'], true)) {
    finish(false, $landing, 422);
}

$name = cleanField('nombre', 120);
$contact = cleanField('contacto', 250);
$location = cleanField('ubicacion', 200);
$hectares = cleanField('hectareas', 40);
$ownership = cleanField('titularidad', 200);
$situation = cleanField('situacion', 200);
$message = cleanField('mensaje', 4000, true);
$consent = cleanField('consent', 20);

if ($name === '' || $contact === '' || $location === '' || $consent !== 'on') {
    finish(false, $landing, 422);
}

$isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
$isPhone = preg_match('/^\+?[0-9][0-9 ()\.\-]{6,}$/D', $contact) === 1;
if (!$isEmail && !$isPhone) {
    finish(false, $landing, 422);
}

$configPath = dirname(__DIR__) . '/config/mail.local.php';
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($configPath) || !is_file($autoloadPath)) {
    error_log('KonscioHousing landings: falta configuración privada o Composer.');
    finish(false, $landing, 503);
}

$config = require $configPath;
if (!is_array($config)) {
    error_log('KonscioHousing landings: configuración SMTP inválida.');
    finish(false, $landing, 503);
}

$required = ['host', 'port', 'encryption', 'username', 'password', 'from_email', 'to_email'];
foreach ($required as $key) {
    if (empty($config[$key]) || (is_string($config[$key]) && str_contains($config[$key], 'PON_AQUI'))) {
        error_log('KonscioHousing landings: falta valor SMTP en ' . $key);
        finish(false, $landing, 503);
    }
}

require $autoloadPath;

$body = "Nueva solicitud desde konsciohousing.org\n"
    . "------------------------------------------------\n"
    . "Origen:             {$origin}\n"
    . "Nombre:             {$name}\n"
    . "Email / WhatsApp:   {$contact}\n"
    . "Ubicación:          {$location}\n"
    . "Superficie (ha):    {$hectares}\n"
    . "Titularidad:        {$ownership}\n"
    . "Situación urb.:     {$situation}\n"
    . "------------------------------------------------\n"
    . "Mensaje:\n{$message}\n";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) $config['host'];
    $mail->Port = (int) $config['port'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $config['username'];
    $mail->Password = (string) $config['password'];
    $mail->SMTPSecure = ($config['encryption'] ?? 'smtps') === 'starttls'
        ? PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer::ENCRYPTION_SMTPS;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;
    $mail->setFrom((string) $config['from_email'], (string) ($config['from_name'] ?? 'Konscio Housing'));
    $mail->addAddress((string) $config['to_email']);
    if ($isEmail) {
        $mail->addReplyTo($contact, $name);
    }
    $mail->isHTML(false);
    $mail->Subject = 'Solicitud web - ' . $origin . ' - ' . $location;
    $mail->Body = $body;
    $mail->send();
    finish(true, $landing);
} catch (Throwable $exception) {
    // No exponer fallos SMTP al visitante ni guardar datos privados del usuario en logs.
    error_log('KonscioHousing landings: fallo de envío SMTP (' . get_class($exception) . ').');
    finish(false, $landing, 502);
}
