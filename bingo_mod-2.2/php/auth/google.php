<?php
/**
 * php/auth/google.php - Maneja el flujo de OAuth 2.0 con Google
 *
 * Flujo:
 * 1. GET ?action=login : Redirige al usuario a la URL de Google para iniciar sesión.
 * 2. GET ?code=...     : Google redirige de vuelta aquí con el código de autorización.
 *                        Se intercambia por un token, se obtienen los datos del usuario,
 *                        se registra (si es nuevo) y se inicia sesión.
 *
 * Configuración de redirect_uri:
 * - Se calcula dinámicamente según el host de la petición para soportar:
 *   * localhost (http)
 *   * túneles ngrok/cloudflare (https)
 *   * dominios de producción (https)
 *   * IPs privadas (192.168.x.x, 10.x.x.x, 172.16-31.x.x) - requieren device_id y device_name
 * - GOOGLE_ALLOWED_HOSTS en .env define hosts permitidos para el callback
 */

declare(strict_types=1);

require_once __DIR__ . '/../../php/funciones.php';
require_once __DIR__ . '/../../database/db.php';

$clientId     = $_ENV['GOOGLE_CLIENT_ID']     ?? '';
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

// Si no están configuradas las credenciales, mostrar error amigable
if (empty($clientId) || empty($clientSecret)) {
    die('Error: Credenciales de Google no configuradas en el archivo .env');
}

/**
 * Verifica si un host es una IP privada (RFC 1918)
 */
function esIpPrivada(string $host): bool {
    // Eliminar puerto si existe
    $host = explode(':', $host)[0];
    
    // Verificar rangos de IP privadas
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $host);
        // 10.0.0.0/8
        if ($parts[0] === '10') return true;
        // 172.16.0.0/12
        if ($parts[0] === '172' && $parts[1] >= 16 && $parts[1] <= 31) return true;
        // 192.168.0.0/16
        if ($parts[0] === '192' && $parts[1] === '168') return true;
        // 127.0.0.0/8 (loopback) - tratado como localhost
        if ($parts[0] === '127') return false;
    }
    
    // IPv6 loopback
    if ($host === '::1' || $host === 'localhost') return false;
    
    // IPv6 Unique Local Addresses (fc00::/7) - treat as private
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = inet_pton($host);
        if ($ip !== false) {
            $firstByte = ord($ip[0]);
            if (($firstByte & 0xfe) === 0xfc) return true; // fc00::/7
        }
    }
    
    return false;
}

/**
 * Obtiene o genera un device_id persistente para el navegador/dispositivo
 */
function obtenerDeviceId(): string {
    $cookieName = 'google_oauth_device_id';
    
    if (isset($_COOKIE[$cookieName]) && !empty($_COOKIE[$cookieName])) {
        return $_COOKIE[$cookieName];
    }
    
    // Generar un UUID v4
    $deviceId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0x8000, 0xbfff), // variant 10xx
        random_int(0x4000, 0x4fff), // version 4
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
    
    // Guardar en cookie por 1 año, HttpOnly, Secure (si HTTPS), SameSite=Lax
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? true : false;
    setcookie($cookieName, $deviceId, [
        'expires' => time() + 365 * 24 * 60 * 60,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    return $deviceId;
}

/**
 * Obtiene un device_name legible para el usuario
 */
function obtenerDeviceName(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Dispositivo desconocido';
    
    // Detectar tipo de dispositivo básico
    $platform = 'Desconocido';
    if (preg_match('/android/i', $ua)) $platform = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';
    elseif (preg_match('/windows/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'macOS';
    elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';
    
    // Detectar navegador básico
    $browser = 'Navegador';
    if (preg_match('/chrome|crios/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/firefox|fxios/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/edge|edg/i', $ua)) $browser = 'Edge';
    
    return "{$browser} en {$platform}";
}

// ── Calcular redirect_uri dinámicamente ────────────────────────────────────────
/**
 * Calcula la URI de redirección basándose en el host de la petición actual.
 * Esto permite que la app funcione en localhost, túneles (ngrok) y producción
 * sin cambiar la configuración en Google Cloud Console (solo añadir los hosts
 * a "Authorized redirect URIs" en la consola de Google).
 */
function calcularRedirectUri(): string {
    $scheme = 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Forzar HTTPS para hosts que no sean localhost/127.0.0.1
    if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        $scheme = 'https';
    }
    
    // Permitir override con APP_FORCE_HTTPS
    $forceHttps = $_ENV['APP_FORCE_HTTPS'] ?? '';
    if ($forceHttps === 'true') $scheme = 'https';
    if ($forceHttps === 'false') $scheme = 'http';
    
    // Si hay APP_BASE_URL configurado, usarlo como base
    $baseUrl = $_ENV['APP_BASE_URL'] ?? '';
    if ($baseUrl) {
        return rtrim($baseUrl, '/') . '/php/auth/google.php';
    }
    
    // Construir URI dinámicamente
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $scriptDir = rtrim($scriptDir, '/');
    return "{$scheme}://{$host}{$scriptDir}/google.php";
}

$redirectUri = calcularRedirectUri();

// Detectar si estamos en una IP privada para agregar device_id y device_name
$currentHost = parse_url($redirectUri, PHP_URL_HOST);
$esIpPrivada = esIpPrivada($currentHost);
$deviceId = $esIpPrivada ? obtenerDeviceId() : '';
$deviceName = $esIpPrivada ? obtenerDeviceName() : '';

// Validar que el host esté en la lista de permitidos (seguridad extra)
$allowedHosts = array_map('trim', explode(',', $_ENV['GOOGLE_ALLOWED_HOSTS'] ?? 'localhost,127.0.0.1'));
$currentHost = parse_url($redirectUri, PHP_URL_HOST);
if (!in_array($currentHost, $allowedHosts, true)) {
    error_log('[GOOGLE OAUTH] Host no permitido: ' . $currentHost . ' - Permitidos: ' . implode(', ', $allowedHosts));
    // No morimos aquí, solo logueamos; Google validará el redirect_uri en su lado
}

// ── 1. Redirigir a Google ───────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth2state'] = $state;
    
    $params = [
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account'
    ];
    
    // Agregar device_id y device_name solo para IPs privadas (requerido por Google)
    if ($esIpPrivada) {
        $params['device_id'] = $deviceId;
        $params['device_name'] = $deviceName;
    }
    
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

// ── 2. Manejar Callback de Google ───────────────────────────────────────────
if (isset($_GET['code'])) {
    
    // Validar state para prevenir CSRF
    if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
        $_SESSION['login_error'] = 'Error de seguridad al iniciar sesión con Google (State mismatch).';
        header('Location: ../../login.php');
        exit;
    }
    unset($_SESSION['oauth2state']);
    
    $code = $_GET['code'];
    
    // Intercambiar código por Token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $_SESSION['login_error'] = 'Error de conexión con Google.';
        header('Location: ../../login.php');
        exit;
    }
    curl_close($ch);
    
    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['access_token'])) {
        $_SESSION['login_error'] = 'No se pudo obtener el token de Google.';
        header('Location: ../../login.php');
        exit;
    }
    
    $accessToken = $tokenData['access_token'];
    
    // Obtener datos del usuario
    $userUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userResponse = curl_exec($ch);
    curl_close($ch);
    
    $googleUser = json_decode($userResponse, true);
    
    if (!isset($googleUser['id']) || !isset($googleUser['email'])) {
        $_SESSION['login_error'] = 'No se pudo obtener la información del usuario de Google.';
        header('Location: ../../login.php');
        exit;
    }
    
    $googleId = $googleUser['id'];
    $email    = strtolower($googleUser['email']);
    // Si no tiene un "name", creamos un nickname basado en el email
    $name     = $googleUser['name'] ?? explode('@', $email)[0]; 
    
    // ── Loguear o Registrar al usuario ───────────────────────────────────────
    try {
        // Buscar por google_id O por email
        $stmt = $pdo->prepare('SELECT id, estado FROM usuarios WHERE google_id = ? OR email = ? LIMIT 1');
        $stmt->execute([$googleId, $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Usuario existe
            if ($user['estado'] !== 'ACTIVO') {
                $_SESSION['login_error'] = 'Tu cuenta está suspendida o inactiva.';
                header('Location: ../../login.php');
                exit;
            }
            
            // Si existe por email pero no tiene google_id, lo actualizamos (vincular cuenta)
            $stmtUpdate = $pdo->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?');
            $stmtUpdate->execute([$googleId, $user['id']]);
            
            $userId = $user['id'];
        } else {
            // Usuario NO existe, lo creamos (Registro)
            
            // Asegurarnos de que el nickname sea único, o agregarle números
            $baseNickname = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name));
            if (strlen($baseNickname) < 3) $baseNickname = 'User' . random_int(100, 999);
            
            $nickname = substr($baseNickname, 0, 25);
            $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE nickname = ? LIMIT 1');
            $stmtCheck->execute([$nickname]);
            if ($stmtCheck->fetch()) {
                $nickname .= random_int(1000, 9999);
            }
            
            // Usar función centralizada para crear usuario completo
            $userId = crearUsuarioCompleto($nickname, $email, null, $googleId);
            
            $_SESSION['login_success'] = '¡Cuenta creada exitosamente con Google!';
        }
        
        // Loguear
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $userId;
        
        header('Location: ../../index.php');
        exit;
        
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[GOOGLE OAUTH] Error BD: ' . $e->getMessage());
        $_SESSION['login_error'] = 'Error interno en el servidor. Intenta más tarde.';
        header('Location: ../../login.php');
        exit;
    }
}

// Si llega aquí sin action o code
header('Location: ../../login.php');
exit;
