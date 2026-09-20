<?php
/**
 * php/auth/login.php  –  API endpoint: POST /php/auth/login.php
 *
 * Inicia sesión validando email y contraseña.
 *
 * Entrada (JSON):
 *   { "email": string, "password": string }
 *
 * Salida (JSON):
 *   { "ok": true }
 *   { "ok": false, "error":  string }
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// ── Solo POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload inválido']);
    exit;
}

$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Por favor, ingresa correo y contraseña.']);
    exit;
}

// ── Conexión a la BD ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../../database/db.php';

// ── Buscar usuario ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, password_hash, estado FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Credenciales incorrectas.']);
    exit;
}

if ($user['estado'] !== 'ACTIVO') {
    echo json_encode(['ok' => false, 'error' => 'Tu cuenta está suspendida o inactiva.']);
    exit;
}

// Si la cuenta fue creada con Google, password_hash podría ser nulo
if (empty($user['password_hash'])) {
    echo json_encode(['ok' => false, 'error' => 'Esta cuenta fue creada con Google. Inicia sesión con Google.']);
    exit;
}

// ── Verificar contraseña ─────────────────────────────────────────────────────
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['ok' => false, 'error' => 'Credenciales incorrectas.']);
    exit;
}

// ── Login exitoso ────────────────────────────────────────────────────────────
session_regenerate_id(true); // Prevenir session fixation
$_SESSION['usuario_id'] = $user['id'];

echo json_encode(['ok' => true]);
exit;
