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
$buyIn      = intval($body['buy_in'] ?? 0);

if (!$nombre || !$nombreSala)       jsonError('Completa todos los campos');
if (strlen($nombre) > 20)           jsonError('Nombre demasiado largo (máx 20)');
if (strlen($nombreSala) > 30)       jsonError('Nombre de sala demasiado largo (máx 30)');

// Validaciones para Modo Payplay
if ($tipoSala === 'payplay') {
    if ($buyIn < 1) {
        jsonError('El Buy-in mínimo para salas Payplay es de 1 ficha ($10.000 COP)');
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

// Retención de fondos en Escrow si es Payplay (sesión)
if ($tipoSala === 'payplay') {
    retenerSaldoEscrow($buyIn, $codigo);
}

// host_id para BD: usar usuario_id real si está logueado, sino 0 (se ignorará en JSON)
$hostIdBD = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

// Para salas payplay NUEVAS, el pozo_total en BD debe ser 0.
// El trigger trg_jugador_sala_insert lo actualizará al agregar el host a jugadores_sala.
$pozoTotalBD = ($tipoSala === 'payplay') ? 0 : $buyIn;

$sala = [
    'codigo'            => $codigo,
    'nombre'            => $nombreSala,
    'tipo_sala'         => $tipoSala,
    'buy_in'            => $buyIn,
    'comision_pct'      => 0.10,
    'pozo_total'        => $buyIn,      // JSON: pozo real desde el inicio
    'estado'            => 'esperando',
    'host_id'           => $jugadorId,  // Para JSON (string aleatorio)
    'host_id_bd'        => $hostIdBD,   // Para BD (integer usuario_id o 0)
    'jugadores'         => [['id' => $jugadorId, 'nombre' => $nombre, 'buy_in_pagado' => $buyIn]],
    'historial'         => [],
    'cartones'          => [],
    'proxima_ficha_at'  => null,
    'intervalo_fichas'  => 10,
    'ganador_id'        => null,
    'ganador_nombre'    => null,
    'premio_ganado'     => null,
    'creado_at'         => time(),
    'finalizado_at'     => null,
];

// ── Sincronizar con BD ─────────────────────────────────────────────────────────
// El JSON de la sala se escribe SOLO si la BD tuvo éxito (ver más abajo),
// para no dejar salas huérfanas en data/ cuando el INSERT falla.
if (isset($_SESSION['usuario_id'])) {
    $usuarioId = (int)$_SESSION['usuario_id'];
    try {
        // Retener escrow en BD ANTES de guardar la sala (para payplay)
        if ($tipoSala === 'payplay') {
            $okEscrow = retenerSaldoEscrowBD($usuarioId, $buyIn, $codigo);
            if (!$okEscrow) {
                // Si falla el escrow en BD, revertir el de sesión
                reembolsarSaldoEscrow($buyIn, $codigo);
                jsonError('Error al reservar fondos en billetera. Intenta de nuevo.');
            }
        }
        
        // Usar pozo_total_bd para BD (0 para payplay nuevo)
        $salaParaBD = $sala;
        $salaParaBD['pozo_total'] = $pozoTotalBD;
        guardarSalaBD($salaParaBD);
        
        $salaId = obtenerSalaIdPorCodigo($codigo);
        if ($salaId) {
            agregarJugadorSalaBD($salaId, $usuarioId, $buyIn, 1);
        }
    } catch (\PDOException $e) {
        // Nota: getSQLState() NO existe en PDOException. El SQLSTATE real
        // está en $e->errorInfo[0]. Usamos eso para no romper el catch.
        $sqlState = $e->errorInfo[0] ?? 'N/A';
        error_log('[CREAR_SALA] Error BD: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | SQLState: ' . $sqlState . ' | HostID_BD: ' . ($hostIdBD ?? 'null') . ' | UsuarioID: ' . ($usuarioId ?? 'null'));
        // Revertir la retención de sesión si había sido hecha (evita fugas de saldo)
        if ($tipoSala === 'payplay') {
            reembolsarSaldoEscrow($buyIn, $codigo);
        }
        jsonError('Error al crear la sala en la base de datos. Intenta de nuevo.');
    }
}

// Solo ahora, con la BD confirmada, persistimos la sala en JSON (data/)
guardarSala($sala);

jsonOk(['codigo' => $codigo, 'tipo_sala' => $tipoSala, 'buy_in' => $buyIn]);
