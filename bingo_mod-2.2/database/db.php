<?php
/**
 * db.php - Conexión PDO a MySQL para Bingo Online
 *
 * Carga credenciales desde variables de entorno (archivo .env)
 * o usa valores por defecto de XAMPP local.
 *
 * Uso:
 *   require_once __DIR__ . '/db.php';
 *   $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
 */

// ── Carga .env si existe ─────────────────────────────────────────────────────
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}

// ── Parámetros de conexión ───────────────────────────────────────────────────
define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_PORT',    $_ENV['DB_PORT']    ?? '3306');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'bingo_db');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');       // XAMPP: sin contraseña por defecto
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ── DSN y opciones PDO ───────────────────────────────────────────────────────
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Lanza excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // Arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                      // Prepared statements reales
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

// ── Instancia PDO (singleton simple) ────────────────────────────────────────
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // En producción: loguear el error, no mostrarlo al cliente
    error_log('[DB] Error de conexión: ' . $e->getMessage());
    http_response_code(503);
    die(json_encode([
        'ok'    => false,
        'error' => 'No se pudo conectar a la base de datos. Intenta más tarde.'
    ]));
}
