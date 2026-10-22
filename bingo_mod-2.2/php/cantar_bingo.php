<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) jsonError('Datos inválidos');

$miId   = $_SESSION['jugador_id'] ?? '';
$codigo = strtoupper(trim($body['codigo'] ?? ''));

// ── Lectura + validación + marcado de ganador (sección crítica con flock) ──
$error    = null;
$salaOut  = null;   // ['sala' => array, 'nombre_ganador' => string, 'premio' => int, 'usuario_id' => int|null]

modificarSala($codigo, function (array $sala) use ($miId, &$error, &$salaOut) {
    if (($sala['estado'] ?? '') !== 'jugando') {
        $error = 'La partida no está activa';
        return null;
    }

    // Validar que el jugador esté registrado en la sala
    $jugadorRegistrado = false;
    foreach ($sala['jugadores'] as $jugador) {
        if ($jugador['id'] === $miId) { $jugadorRegistrado = true; break; }
    }
    if (!$jugadorRegistrado) { $error = 'No estás registrado en esta partida'; return null; }

    if (!isset($sala['cartones'][$miId])) { $error = 'No tienes un cartón asignado'; return null; }

    $carton   = $sala['cartones'][$miId];
    $marcadas = $carton['marcadas'];

    // ── Validación 1: todas las marcadas deben estar en el historial ──
    foreach ($marcadas as $m) {
        if (!in_array($m, $sala['historial'])) {
            $error = 'Bingo inválido: contiene fichas no generadas';
            return null;
        }
    }

    // ── Validación 2: debe existir una combinación ganadora ──────────
    if (!validarBingo($carton['grid'], $marcadas)) {
        $error = 'Bingo inválido – necesitas una fila, columna o diagonal completa';
        return null;
    }

    // ── Ganador confirmado ───────────────────────────────────────────
    $nombreGanador = 'Jugador';
    foreach ($sala['jugadores'] as $j) {
        if ($j['id'] === $miId) { $nombreGanador = $j['nombre']; break; }
    }

    $premioNeto = 0;
    // ── Liquidación de Premio en Modo Payplay (RF-21) ─────────────────
    if (($sala['tipo_sala'] ?? 'free') === 'payplay') {
        $pozoTotal    = floatval($sala['pozo_total'] ?? (count($sala['jugadores']) * ($sala['buy_in'] ?? 0)));
        $comisionPct  = floatval($sala['comision_pct'] ?? 0.10);
        $premioNeto   = (int) round($pozoTotal * (1 - $comisionPct));

        $sala['premio_ganado']       = $premioNeto;
        $sala['estado_financiero']   = 'REPARTIDA';
    }

    $sala['estado']         = 'finalizado';
    $sala['ganador_id']     = $miId;
    $sala['ganador_nombre'] = $nombreGanador;
    $sala['finalizado_at']  = time();

    $salaOut = [
        'sala'           => $sala,
        'nombre_ganador' => $nombreGanador,
        'premio'         => $premioNeto,
        'usuario_id'     => isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null,
    ];

    return $sala; // se escribe en disco con la sala finalizada
});

if ($error !== null) jsonError($error);
if ($salaOut === null) jsonError('No se pudo procesar el bingo. Intenta de nuevo.');

// ── Premio en billetera de sesión (solo modo payplay) ─────────────
if (($salaOut['sala']['tipo_sala'] ?? 'free') === 'payplay' && $salaOut['premio'] > 0) {
    acreditarPremioGanador($salaOut['premio'], $codigo);
}

// ── Sincronización con BD (transacción única: ganador + premio + estado) ──
// Si la BD falla, la partida YA quedó registrada en la sala (JSON, fuente de
// verdad del juego); el error se registra en log y se responde igualmente.
// IMPORTANTE: el INSERT en `ganadores` va PRIMERO: su índice único
// (uq_ganadores_sala) garantiza un solo ganador por sala (idempotencia).
if ($salaOut['usuario_id'] !== null) {
    $usuarioId = (int)$salaOut['usuario_id'];
    $premioNeto = $salaOut['premio'];

    try {
        require_once __DIR__ . '/../database/db.php';
        global $pdo;

        if (($salaOut['sala']['tipo_sala'] ?? 'free') === 'payplay' && $premioNeto > 0) {
            $pdo->beginTransaction();

            $salaId = obtenerSalaIdPorCodigo($codigo);
            if ($salaId) {
                $jugadorSalaId = obtenerJugadorSalaId($salaId, $usuarioId);
                // El carton_id debe ser un id REAL de la tabla cartones
                // (o null); pasar un jugador_sala_id viola la FK
                // fk_ganadores_carton y rompía la petición con un 500.
                $cartonId = $jugadorSalaId ? obtenerCartonIdBD($jugadorSalaId) : null;

                registrarGanadorBD($salaId, $usuarioId, $cartonId, $premioNeto);
            }

            acreditarPremioGanadorBDSinTx($usuarioId, $premioNeto, $codigo);
            actualizarSalaBD($salaOut['sala']);

            $pdo->commit();
        } else {
            actualizarSalaBD($salaOut['sala']);
        }
    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[CANTAR_BINGO] Error sincronizando con BD (la partida quedó registrada en la sala): ' . $e->getMessage());
    }
}

jsonOk([
    'ganador'        => true,
    'nombre_ganador' => $salaOut['nombre_ganador'],
    'premio'         => $salaOut['premio'],
    'tipo_sala'      => $salaOut['sala']['tipo_sala'] ?? 'free'
]);
