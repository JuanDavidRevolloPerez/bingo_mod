<?php
/**
 * init.php - Inicialización centralizada de sesión con datos de BD
 * 
 * Carga las variables de entorno y sincroniza los datos del usuario
 * logueado desde la base de datos a la sesión.
 */

declare(strict_types=1);

// ── Cargar variables de entorno ─────────────────────────────────────────────
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}

/**
 * Inicializa la sesión del usuario con datos de BD si está logueado
 */
function inicializarSesionConBD(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Si hay un usuario logueado, cargar sus datos desde BD
    if (isset($_SESSION['usuario_id'])) {
        try {
            require_once __DIR__ . '/../../database/db.php';
            global $pdo;

            // Cargar datos del usuario
            $stmt = $pdo->prepare('SELECT id, nickname, email, nivel_verificacion, rol FROM usuarios WHERE id = ?');
            $stmt->execute([$_SESSION['usuario_id']]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $_SESSION['usuario'] = [
                    'id' => (int)$usuario['id'],
                    'nickname' => $usuario['nickname'],
                    'email' => $usuario['email'],
                    'nivel_kyc' => (int)$usuario['nivel_verificacion'],
                    'rol' => $usuario['rol'],
                    'documento' => null
                ];

                // Cargar billetera
                $stmt = $pdo->prepare('SELECT saldo_disponible, saldo_retenido FROM billeteras WHERE usuario_id = ?');
                $stmt->execute([$_SESSION['usuario_id']]);
                $billetera = $stmt->fetch();

                if ($billetera) {
                    $_SESSION['billetera'] = [
                        'saldo_disponible' => floatval($billetera['saldo_disponible']),
                        'saldo_retenido' => floatval($billetera['saldo_retenido']),
                        'transacciones' => []
                    ];
                }

                // Cargar KYC
                $stmt = $pdo->prepare('SELECT * FROM verificaciones_kyc WHERE usuario_id = ?');
                $stmt->execute([$_SESSION['usuario_id']]);
                $kyc = $stmt->fetch();

                if ($kyc) {
                    $_SESSION['kyc'] = [
                        'nivel' => (int)$kyc['nivel'],
                        'estado' => $kyc['estado'],
                        'otp_enviado' => $kyc['otp_enviado'],
                        'documento_tipo' => $kyc['documento_tipo'],
                        'documento_num' => $kyc['documento_num'],
                        'estado_doc' => $kyc['estado']
                    ];
                }
            }
        } catch (\PDOException $e) {
            error_log('[INIT] Error cargando datos de BD: ' . $e->getMessage());
        }
    }

    // Inicializar estructuras por defecto si no existen
    if (!isset($_SESSION['usuario'])) {
        $_SESSION['usuario'] = [
            'id' => null,
            'nickname' => $_SESSION['jugador_nombre'] ?? 'Jugador',
            'email' => 'jugador@bingo.local',
            'nivel_kyc' => 0,
            'rol' => 'JUGADOR',
            'documento' => null
        ];
    }

    if (!isset($_SESSION['billetera'])) {
        $_SESSION['billetera'] = [
            'saldo_disponible' => 5,
            'saldo_retenido' => 0,
            'transacciones' => []
        ];
    }

    if (!isset($_SESSION['kyc'])) {
        $_SESSION['kyc'] = [
            'nivel' => 0,
            'estado' => 'PENDIENTE',
            'otp_enviado' => null,
            'documento_tipo' => 'CC',
            'documento_num' => '',
            'estado_doc' => 'PENDIENTE'
        ];
    }

    if (!isset($_SESSION['solicitudes_retiro'])) {
        $_SESSION['solicitudes_retiro'] = [];
    }
}
