<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$nombre    = sanitizar($body['nombre'] ?? '');
$nombreSala = sanitizar($body['nombre_sala'] ?? '');

if (!$nombre || !$nombreSala)       jsonError('Completa todos los campos');
if (strlen($nombre) > 20)           jsonError('Nombre demasiado largo (máx 20)');
if (strlen($nombreSala) > 30)       jsonError('Nombre de sala demasiado largo (máx 30)');

$codigo    = codigoUnico();
$jugadorId = bin2hex(random_bytes(8));

$_SESSION['jugador_id']   = $jugadorId;
$_SESSION['codigo_sala']  = $codigo;
$_SESSION['jugador_nombre'] = $nombre;

$sala = [
    'codigo'            => $codigo,
    'nombre'            => $nombreSala,
    'estado'            => 'esperando',
    'host_id'           => $jugadorId,
    'jugadores'         => [['id' => $jugadorId, 'nombre' => $nombre]],
    'historial'         => [],
    'cartones'          => [],
    'proxima_ficha_at'  => null,
    'intervalo_fichas'  => null,
    'ganador_id'        => null,
    'ganador_nombre'    => null,
    'creado_at'         => time(),
    'finalizado_at'     => null,
];

guardarSala($sala);
jsonOk(['codigo' => $codigo]);
