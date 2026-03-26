<?php
/**
 * mailer.php — CDI Desarrollos contact form handler
 *
 * Expects: POST request with JSON body { nombre, email, mensaje, website? }
 * Returns: JSON { success: bool, message: string }
 *
 * PHPMailer v6.9.3 standalone files live in ./phpmailer/
 */

declare(strict_types=1);

// ─── CORS ────────────────────────────────────────────────────────────────────

$allowed_origins = [
    'https://cdidesarrollos.com',
    'https://www.cdidesarrollos.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Allow same-domain (with/without www) and local dev
if (in_array($origin, $allowed_origins, true) || preg_match('/^https?:\/\/localhost(:\d+)?$/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . $allowed_origins[0]);
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Only accept POST ────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ─── Rate limiting (session-based: max 3 submissions per 5 min) ──────────────

session_start();

$now = time();
$window = 5 * 60; // 5 minutes
$max_submissions = 3;

if (!isset($_SESSION['cdi_submissions'])) {
    $_SESSION['cdi_submissions'] = [];
}

// Purge entries older than the window
$_SESSION['cdi_submissions'] = array_filter(
    $_SESSION['cdi_submissions'],
    fn($ts) => ($now - $ts) < $window
);

if (count($_SESSION['cdi_submissions']) >= $max_submissions) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos. Por favor esperá unos minutos antes de intentar de nuevo.',
    ]);
    exit;
}

// ─── Parse JSON body ─────────────────────────────────────────────────────────

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

// ─── Honeypot check ──────────────────────────────────────────────────────────
// If the hidden "website" field is filled, it's a bot — fake success silently.

if (!empty($data['website'])) {
    echo json_encode(['success' => true, 'message' => 'Mensaje enviado con éxito.']);
    exit;
}

// ─── reCAPTCHA v3 verification ───────────────────────────────────────────────

$recaptcha_secret = '6Lf_cZksAAAAAMFyQ2na4WPaw2_Le2M78-dFiiCe';
$recaptcha_token  = trim($data['recaptcha_token'] ?? '');

if ($recaptcha_token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Verificación de seguridad fallida. Recargá la página e intentá de nuevo.']);
    exit;
}

$recaptcha_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
    'secret'   => $recaptcha_secret,
    'response' => $recaptcha_token,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
]));

$recaptcha_result = json_decode($recaptcha_response, true);

if (
    !$recaptcha_result
    || empty($recaptcha_result['success'])
    || ($recaptcha_result['score'] ?? 0) < 0.5
    || ($recaptcha_result['action'] ?? '') !== 'contact'
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Verificación de seguridad fallida. Intentá de nuevo.']);
    exit;
}

// ─── Field validation ────────────────────────────────────────────────────────

$nombre  = trim($data['nombre']  ?? '');
$email   = trim($data['email']   ?? '');
$asunto  = trim($data['asunto']  ?? '');
$mensaje = trim($data['mensaje'] ?? '');

$errors = [];

if ($nombre === '') {
    $errors[] = 'El nombre es requerido.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email es inválido.';
}

if ($asunto === '') {
    $errors[] = 'El asunto es requerido.';
}

if ($mensaje === '') {
    $errors[] = 'El mensaje es requerido.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ─── Sanitize inputs ─────────────────────────────────────────────────────────

$nombre  = htmlspecialchars($nombre,  ENT_QUOTES, 'UTF-8');
$email   = htmlspecialchars($email,   ENT_QUOTES, 'UTF-8');
$asunto  = htmlspecialchars($asunto,  ENT_QUOTES, 'UTF-8');
$mensaje = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

// ─── Send via PHPMailer ───────────────────────────────────────────────────────

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

try {
    $mail = new PHPMailer(true);

    // SMTP configuration
    $mail->isSMTP();
    $mail->Host       = 'a0090955.ferozo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'joaquinvesapa@cdidesarrollos.com';
    $mail->Password   = '*7duz/tF';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL on port 465
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    // From / Reply-To
    $mail->setFrom('joaquinvesapa@cdidesarrollos.com', 'CDI Desarrollos');
    $mail->addReplyTo($email, $nombre);

    // Recipients
    $mail->addAddress('joaquinvesapa@cdidesarrollos.com', 'Joaquin Vesapa');
    $mail->addAddress('vescoivan@cdidesarrollos.com',     'Ivan Vescoivan');

    // Content
    $mail->isHTML(true);
    $mail->Subject = "[CDI Web] {$asunto}";

    $mail->Body = "
        <html>
        <body style=\"font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;\">
            <h2 style=\"color: #E8831A; border-bottom: 2px solid #E8831A; padding-bottom: 8px;\">
                Nuevo mensaje de contacto
            </h2>
            <table style=\"width: 100%; border-collapse: collapse;\">
                <tr>
                    <td style=\"padding: 8px 0; font-weight: bold; width: 100px;\">Nombre:</td>
                    <td style=\"padding: 8px 0;\">{$nombre}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; font-weight: bold;\">Email:</td>
                    <td style=\"padding: 8px 0;\"><a href=\"mailto:{$email}\">{$email}</a></td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; font-weight: bold;\">Asunto:</td>
                    <td style=\"padding: 8px 0;\">{$asunto}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; font-weight: bold; vertical-align: top;\">Mensaje:</td>
                    <td style=\"padding: 8px 0; white-space: pre-wrap;\">{$mensaje}</td>
                </tr>
            </table>
            <hr style=\"border: none; border-top: 1px solid #eee; margin: 24px 0;\">
            <p style=\"color: #999; font-size: 12px;\">
                Enviado desde cdidesarrollos.com
            </p>
        </body>
        </html>
    ";

    $mail->AltBody = "Nuevo mensaje de contacto\n\nNombre: {$nombre}\nEmail: {$email}\nAsunto: {$asunto}\n\nMensaje:\n{$mensaje}";

    $mail->send();

    // Record this submission for rate limiting
    $_SESSION['cdi_submissions'][] = $now;

    echo json_encode(['success' => true, 'message' => 'Mensaje enviado con éxito. Te respondemos en menos de 24 horas.']);

} catch (PHPMailerException $e) {
    error_log('CDI Mailer error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el mensaje. Por favor intentá nuevamente o escribinos directamente.',
    ]);
}
