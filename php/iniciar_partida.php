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

// Consolidación de Fondos Payplay (Fase 2 de Escrow)
if (($sala['tipo_sala'] ?? 'free') === 'payplay') {
    $numJugadores = count($sala['jugadores']);
    $buyIn        = floatval($sala['buy_in'] ?? 0);
    $sala['pozo_total'] = $numJugadores * $buyIn;
    $sala['estado_financiero'] = 'COBRADA';
}

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
jsonOk([
    'pozo_total' => $sala['pozo_total'] ?? 0,
    'tipo_sala'  => $sala['tipo_sala'] ?? 'free'
]);
