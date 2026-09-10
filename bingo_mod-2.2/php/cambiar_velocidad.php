<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$miId      = $_SESSION['jugador_id'] ?? '';
$codigo    = strtoupper(trim($body['codigo'] ?? ''));
$velocidad = (int)($body['velocidad'] ?? 0);

if (!in_array($velocidad, [5, 10, 15])) jsonError('Velocidad inválida. Usa 5, 10 o 15');

$sala = cargarSala($codigo);
if (!$sala)                          jsonError('Sala no encontrada');
if ($sala['host_id'] !== $miId)      jsonError('Solo el host puede cambiar la velocidad');
if ($sala['estado'] !== 'jugando')   jsonError('La partida no está activa');

$sala['intervalo_fichas'] = $velocidad;
// Ajustar el próximo turno al nuevo intervalo desde ahora
$sala['proxima_ficha_at'] = time() + $velocidad;
guardarSala($sala);

jsonOk(['intervalo_fichas' => $velocidad]);
