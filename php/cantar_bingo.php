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
if (!$sala)                           jsonError('Sala no encontrada');
if ($sala['estado'] !== 'jugando')    jsonError('La partida no está activa');
if (!isset($sala['cartones'][$miId])) jsonError('No estás registrado en esta partida');

$carton   = $sala['cartones'][$miId];
$marcadas = $carton['marcadas'];
$modo     = $sala['modo'] ?? 'normal';

// ── Validación 1: todas las marcadas deben estar en el historial ──
foreach ($marcadas as $m) {
    if (!in_array($m, $sala['historial'])) {
        jsonError('Bingo inválido: contiene fichas no generadas');
    }
}

// ── Validación 2: debe existir una combinación ganadora ──────────
if (!validarBingo($carton['grid'], $marcadas)) {
    jsonError('Bingo inválido – necesitas una fila, columna o diagonal completa');
}

// ── Ganador confirmado ───────────────────────────────────────────
$nombreGanador = 'Jugador';
foreach ($sala['jugadores'] as $j) {
    if ($j['id'] === $miId) { $nombreGanador = $j['nombre']; break; }
}

$sala['estado']          = 'finalizado';
$sala['ganador_id']      = $miId;
$sala['ganador_nombre']  = $nombreGanador;
$sala['finalizado_at']   = time();

$premioNeto = 0;
// ── Liquidación de Premio en Modo Payplay (RF-21) ─────────────────
if (($sala['tipo_sala'] ?? 'free') === 'payplay') {
    $pozoTotal = floatval($sala['pozo_total'] ?? (count($sala['jugadores']) * ($sala['buy_in'] ?? 0)));
    $comisionPct = floatval($sala['comision_pct'] ?? 0.10);
    $premioNeto = round($pozoTotal * (1 - $comisionPct), 2);
    
    $sala['premio_ganado'] = $premioNeto;
    $sala['estado_financiero'] = 'REPARTIDA';
    
    acreditarPremioGanador($premioNeto, $codigo);
}

guardarSala($sala);

jsonOk([
    'ganador'        => true,
    'nombre_ganador' => $nombreGanador,
    'premio'         => $premioNeto,
    'tipo_sala'      => $sala['tipo_sala'] ?? 'free'
]);
