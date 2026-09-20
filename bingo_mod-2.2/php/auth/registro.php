<?php
/**
 * php/auth/registro.php  –  API endpoint: POST /php/auth/registro.php
 *
 * Crea un nuevo usuario en la tabla `usuarios` y su billetera en `billeteras`.
 *
 * Entrada (JSON):
 *   { "nickname": string, "email": string, "password": string }
 *
 * Salida (JSON):
 *   { "ok": true,  "mensaje": string }
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

// ── Leer cuerpo JSON ─────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload inválido']);
    exit;
}

$nickname = trim($body['nickname'] ?? '');
$email    = strtolower(trim($body['email']    ?? ''));
$password = $body['password'] ?? '';

// ── Validaciones del servidor ─────────────────────────────────────────────────
$errors = [];

if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $nickname)) {
    $errors[] = 'El nickname debe tener entre 3 y 30 caracteres (letras, números y _).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $errors[] = 'El correo electrónico no es válido.';
}

if (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors[] = 'La contraseña debe tener mínimo 8 caracteres, incluir letras y números.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Conexión a la BD ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../../database/db.php';   // define $pdo

// ── Verificar duplicados ─────────────────────────────────────────────────────
$stmtCheck = $pdo->prepare(
    'SELECT id FROM usuarios WHERE email = ? OR nickname = ? LIMIT 1'
);
$stmtCheck->execute([$email, $nickname]);
$existente = $stmtCheck->fetch();

if ($existente) {
    // Detectar cuál duplicado es para dar un mensaje preciso
    $stmtEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmtEmail->execute([$email]);
    if ($stmtEmail->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Ese correo electrónico ya está registrado.']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Ese nickname ya está en uso. Elige otro.']);
    }
    exit;
}

// ── Crear usuario ─────────────────────────────────────────────────────────────
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $pdo->beginTransaction();

    // 1. Insertar usuario
    $stmtUser = $pdo->prepare(
        'INSERT INTO usuarios (nickname, email, password_hash, nivel_verificacion, rol, estado)
         VALUES (?, ?, ?, 0, \'JUGADOR\', \'ACTIVO\')'
    );
    $stmtUser->execute([$nickname, $email, $passwordHash]);
    $nuevoId = (int) $pdo->lastInsertId();

    // 2. Crear billetera vacía automáticamente
    $stmtBill = $pdo->prepare(
        'INSERT INTO billeteras (usuario_id, saldo_disponible, saldo_retenido)
         VALUES (?, 0.00, 0.00)'
    );
    $stmtBill->execute([$nuevoId]);

    // 3. Crear registro KYC pendiente
    $stmtKyc = $pdo->prepare(
        'INSERT INTO verificaciones_kyc (usuario_id, estado)
         VALUES (?, \'PENDIENTE\')'
    );
    $stmtKyc->execute([$nuevoId]);

    $pdo->commit();

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log('[REGISTRO] Error BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno. Por favor intenta más tarde.']);
    exit;
}

// ── Respuesta exitosa ─────────────────────────────────────────────────────────
echo json_encode([
    'ok'      => true,
    'mensaje' => '¡Cuenta creada exitosamente! Bienvenido a Bingo Online.',
    'usuario' => ['id' => $nuevoId, 'nickname' => $nickname]
]);
exit;
