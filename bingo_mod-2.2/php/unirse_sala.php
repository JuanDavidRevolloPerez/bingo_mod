<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$nombre = sanitizar($body['nombre'] ?? '');
$codigo = strtoupper(trim($body['codigo'] ?? ''));

if (!$nombre || !$codigo)      jsonError('Completa todos los campos');
if (strlen($nombre) > 20)      jsonError('Nombre demasiado largo (máx 20)');

$sala = cargarSala($codigo);
if (!$sala)                    jsonError('Sala no encontrada. Verifica el código');
if ($sala['estado'] !== 'esperando') jsonError('La partida ya comenzó');
if (count($sala['jugadores']) >= 20) jsonError('La sala está llena (máx 20)');

// Si es sala Payplay, validar y retener Buy-in
$tipoSala = $sala['tipo_sala'] ?? 'free';
$buyIn    = floatval($sala['buy_in'] ?? 0);

if ($tipoSala === 'payplay' && $buyIn > 0) {
    $billetera = obtenerBilletera();
    if ($billetera['saldo_disponible'] < $buyIn) {
        jsonError("Esta sala es Payplay con Buy-in de " . number_format($buyIn, 0, ',', '.') . " fichas. Tu saldo disponible es insuficiente.");
    }
    retenerSaldoEscrow($buyIn, $codigo);
    $sala['pozo_total'] = ($sala['pozo_total'] ?? 0) + $buyIn;
}

$jugadorId = bin2hex(random_bytes(8));
$_SESSION['jugador_id']     = $jugadorId;
$_SESSION['codigo_sala']    = $codigo;
$_SESSION['jugador_nombre'] = $nombre;
$_SESSION['usuario']['nickname'] = $nombre;

$sala['jugadores'][] = ['id' => $jugadorId, 'nombre' => $nombre, 'buy_in_pagado' => $buyIn];
guardarSala($sala);

jsonOk([
    'codigo'     => $codigo,
    'tipo_sala'  => $tipoSala,
    'buy_in'     => $buyIn,
    'pozo_total' => $sala['pozo_total'] ?? 0
]);
