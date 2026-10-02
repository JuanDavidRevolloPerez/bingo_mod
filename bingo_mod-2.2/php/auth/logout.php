<?php
/**
 * php/auth/logout.php - API endpoint: POST/GET /php/auth/logout.php
 *
 * Cierra la sesión actual: limpia $_SESSION, invalida la cookie
 * y destruye la sesión en el servidor.
 */
session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: ../../index.php');
exit;
