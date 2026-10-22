<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

/**
 * cancelar_sala.php - El anfitrión cancela la sala ANTES de iniciar la partida.
 *
 * El escrow retenido se devuelve EN SU TOTALIDAD de inmediato:
 *  - A TODOS los jugadores con cuenta vía BD (reembolsarSaldoEscrowBD),
 *    que es donde vive el saldo real; su sesión se re-sincroniza sola.
 *  - Al anfitrión en su sesión de esta petición (la única alcanzable aquí).
 * Los invitados reciben su reembolso de sesión vía el auto-reembolso
 * (verificar_estado.php accion=reembolso_cancelacion) que dispara su lobby.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$miId   = $_SESSION['jugador_id'] ?? '';
$codigo = strtoupper(trim($body['codigo'] ?? ''));

if (!file_exists(roomFile($codigo))) jsonError('Sala no encontrada');

$error   = null;
$resumen = null; // ['jugadores' => int, 'total' => float]

// Sección crítica con flock: validación + reembolsos + cancelación atómicos
modificarSala($codigo, function (array $sala) use ($miId, &$error, &$resumen) {
    // Solo el anfitrión puede cancelar
    if (($sala['host_id'] ?? '') !== $miId) {
        $error = 'Solo el anfitrión puede cancelar la sala';
        return null;
    }

    // Idempotencia: si ya se reembolsó, no repetir
    if (!empty($sala['escrow_reembolsado'])) {
        $error = 'La sala ya fue cancelada y reembolsada';
        return null;
    }

    // Solo ANTES de iniciar la partida
    if (($sala['estado'] ?? '') !== 'esperando') {
        $error = 'La partida ya fue iniciada; no se puede cancelar';
        return null;
    }

    $reembolsados     = 0;
    $totalReembolsado = 0.0;

    foreach ($sala['jugadores'] as $jugador) {
        $buyInPagado = floatval($jugador['buy_in_pagado'] ?? 0);
        if ($buyInPagado <= 0) continue;

        // Sesión: solo alcanzable para el jugador de ESTA sesión (el anfitrión)
        if (isset($_SESSION['jugador_id']) && $_SESSION['jugador_id'] === $jugador['id']) {
            reembolsarSaldoEscrow($buyInPagado, $sala['codigo']);
        }

        // BD: alcanzable para TODOS los jugadores con cuenta (saldo real).
        // El tope min(fichas, saldo_retenido) lo hace idempotente.
        if (isset($jugador['usuario_id']) && $jugador['usuario_id'] > 0) {
            try {
                reembolsarSaldoEscrowBD((int)$jugador['usuario_id'], $buyInPagado, $sala['codigo']);
            } catch (\Throwable $e) {
                // Un fallo puntual no aborta los reembolsos de los demás
                error_log('[CANCELAR_SALA] Error reembolsando en BD al usuario ' . $jugador['usuario_id'] . ': ' . $e->getMessage());
            }
        }

        $reembolsados++;
        $totalReembolsado += $buyInPagado;
    }

    $sala['estado']             = 'cancelado';
    $sala['pozo_total']         = 0;
    $sala['escrow_reembolsado'] = true;
    $sala['estado_financiero']  = 'REEMBOLSADA';
    $sala['cancelada_por']      = 'anfitrion';
    $sala['finalizado_at']      = time();

    $resumen = ['jugadores' => $reembolsados, 'total' => $totalReembolsado];

    return $sala;
});

if ($error !== null) jsonError($error);
if ($resumen === null) jsonError('No se pudo cancelar la sala. Intenta de nuevo.');

// Sincronizar estado (cancelado, pozo 0) en BD — la partida ya quedó
// cancelada en la sala (JSON, fuente de verdad); un fallo de BD se loguea.
$salaCancelada = cargarSala($codigo);
if ($salaCancelada && isset($_SESSION['usuario_id'])) {
    try {
        actualizarSalaBD($salaCancelada);
    } catch (\Throwable $e) {
        error_log('[CANCELAR_SALA] Error sincronizando estado con BD: ' . $e->getMessage());
    }
}

jsonOk([
    'cancelada'         => true,
    'reembolsados'      => $resumen['jugadores'],
    'total_reembolsado' => $resumen['total'],
]);
