<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$miId   = $_SESSION['jugador_id'] ?? '';
$codigo = strtoupper(trim($body['codigo'] ?? ''));

$sala = cargarSala($codigo);
if (!$sala)                          jsonError('Sala no encontrada');
if ($sala['host_id'] !== $miId)      jsonError('Solo el host puede iniciar la partida');
if ($sala['estado'] !== 'esperando') jsonError('La partida ya fue iniciada');
if (count($sala['jugadores']) < 1)   jsonError('Necesitas al menos un jugador');

// Modo de nomenclatura: 'normal' o 'reducido'
$modo = ($body['modo'] ?? 'normal') === 'reducido' ? 'reducido' : 'normal';
// Auto-forzar reducido si hay menos de 5 jugadores (regla de negocio)
if (count($sala['jugadores']) < 5) $modo = 'reducido';

$sala['modo'] = $modo;

// Generar cartón para cada jugador
foreach ($sala['jugadores'] as $jugador) {
    $sala['cartones'][$jugador['id']] = [
        'grid'    => generarCarton($modo),
        'marcadas' => [],
    ];
}

$intervalo = in_array((int)($body['velocidad'] ?? 10), [5, 10, 15]) ? (int)$body['velocidad'] : 10;
$sala['estado']           = 'jugando';
$sala['intervalo_fichas'] = $intervalo;
$sala['proxima_ficha_at'] = time() + $intervalo;

guardarSala($sala);
jsonOk();
