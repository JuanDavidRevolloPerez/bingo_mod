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

require_once __DIR__ . '/../../php/funciones.php';
require_once __DIR__ . '/../../database/db.php';

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
$tipoDocumento = strtoupper(trim($body['tipo_documento'] ?? ''));
$numeroDocumento = trim($body['numero_documento'] ?? '');
$telefono = trim($body['telefono'] ?? '');
$fechaNacimiento = trim($body['fecha_nacimiento'] ?? '');

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

if (!in_array($tipoDocumento, ['CC', 'CE'])) {
    $errors[] = 'Tipo de documento inválido. Solo se aceptan CC o CE.';
}

if (empty($numeroDocumento) || strlen($numeroDocumento) > 20) {
    $errors[] = 'El número de documento es requerido (máximo 20 caracteres).';
}

if (empty($telefono) || !preg_match('/^[0-9]{7,15}$/', $telefono)) {
    $errors[] = 'Ingresa un número de teléfono válido.';
}

if (empty($fechaNacimiento)) {
    $errors[] = 'La fecha de nacimiento es requerida.';
} else {
    $fechaNac = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
    if (!$fechaNac || $fechaNac->format('Y-m-d') !== $fechaNacimiento) {
        $errors[] = 'Fecha de nacimiento inválida.';
    } else {
        $hoy = new DateTime();
        $edad = $hoy->diff($fechaNac)->y;
        if ($edad < 18) {
            $errors[] = 'Debes ser mayor de edad (18+) para registrarte.';
        }
        if ($edad > 120) {
            $errors[] = 'Fecha de nacimiento no válida.';
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Verificar duplicados ─────────────────────────────────────────────────────
$stmtCheck = $pdo->prepare(
    'SELECT id FROM usuarios WHERE email = ? OR nickname = ? LIMIT 1'
);
$stmtCheck->execute([$email, $nickname]);
$existente = $stmtCheck->fetch();

if ($existente) {
    $stmtEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmtEmail->execute([$email]);
    if ($stmtEmail->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Ese correo electrónico ya está registrado.']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Ese nickname ya está en uso. Elige otro.']);
    }
    exit;
}

// ── Verificar número de documento duplicado (columna consolidada en usuarios) ──
$stmtDoc = $pdo->prepare('SELECT id FROM usuarios WHERE documento_num = ? LIMIT 1');
$stmtDoc->execute([$numeroDocumento]);
if ($stmtDoc->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Ese número de documento ya está registrado.']);
    exit;
}

// ── Crear usuario usando función centralizada ─────────────────────────────────
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $nuevoId = crearUsuarioCompleto(
        $nickname, 
        $email, 
        $passwordHash, 
        null,
        $tipoDocumento,
        $numeroDocumento,
        $telefono,
        $fechaNacimiento
    );
} catch (\PDOException $e) {
    error_log('[REGISTRO] Error BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno. Por favor intenta más tarde.']);
    exit;
}

// ── Respuesta exitosa ─────────────────────────────────────────────────────────
echo json_encode([
    'ok'      => true,
    'mensaje' => '¡Cuenta creada exitosamente! Bienvenido a BANG ONLINE.',
    'usuario' => ['id' => $nuevoId, 'nickname' => $nickname]
]);
exit;
