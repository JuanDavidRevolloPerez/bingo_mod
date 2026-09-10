<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$nombre     = sanitizar($body['nombre'] ?? '');
$nombreSala = sanitizar($body['nombre_sala'] ?? '');
$tipoSala   = sanitizar($body['tipo_sala'] ?? 'free');
$buyIn      = floatval($body['buy_in'] ?? 0);

if (!$nombre || !$nombreSala)       jsonError('Completa todos los campos');
if (strlen($nombre) > 20)           jsonError('Nombre demasiado largo (máx 20)');
if (strlen($nombreSala) > 30)       jsonError('Nombre de sala demasiado largo (máx 30)');

// Validaciones para Modo Payplay
if ($tipoSala === 'payplay') {
    if ($buyIn < 1000) {
        jsonError('El Buy-in mínimo para salas Payplay es de 1.000 fichas');
    }
    $billetera = obtenerBilletera();
    if ($billetera['saldo_disponible'] < $buyIn) {
        jsonError('Saldo insuficiente en tu billetera para cubrir el Buy-in. Por favor realiza una recarga.');
    }
} else {
    $tipoSala = 'free';
    $buyIn = 0;
}

$codigo    = codigoUnico();
$jugadorId = bin2hex(random_bytes(8));

$_SESSION['jugador_id']     = $jugadorId;
$_SESSION['codigo_sala']    = $codigo;
$_SESSION['jugador_nombre'] = $nombre;
$_SESSION['usuario']['nickname'] = $nombre;

// Retención de fondos en Escrow si es Payplay
if ($tipoSala === 'payplay') {
    retenerSaldoEscrow($buyIn, $codigo);
}

$sala = [
    'codigo'            => $codigo,
    'nombre'            => $nombreSala,
    'tipo_sala'         => $tipoSala,
    'buy_in'            => $buyIn,
    'comision_pct'      => 0.10,
    'pozo_total'        => $buyIn,
    'estado'            => 'esperando',
    'host_id'           => $jugadorId,
    'jugadores'         => [['id' => $jugadorId, 'nombre' => $nombre, 'buy_in_pagado' => $buyIn]],
    'historial'         => [],
    'cartones'          => [],
    'proxima_ficha_at'  => null,
    'intervalo_fichas'  => null,
    'ganador_id'        => null,
    'ganador_nombre'    => null,
    'premio_ganado'     => null,
    'creado_at'         => time(),
    'finalizado_at'     => null,
];

guardarSala($sala);
jsonOk(['codigo' => $codigo, 'tipo_sala' => $tipoSala, 'buy_in' => $buyIn]);
