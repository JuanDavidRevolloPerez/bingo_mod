<?php
/**
 * funciones.php - Funciones compartidas del sistema de BANG ONLINE (V2.2)
 */

require_once __DIR__ . '/auth/init.php';

define('DATA_DIR', __DIR__ . '/../data/');
define('VALOR_FICHA', 10000); // 1 Ficha = $10,000 COP

// Inicializar sesión con datos de BD (si hay usuario logueado)
inicializarSesionConBD();

function obtenerBilletera(): array {
    inicializarSesionConBD();
    return $_SESSION['billetera'];
}

function registrarTransaccion(string $tipo, int $monto, int $saldoResultante, string $descripcion, ?string $ref = null): void {
    inicializarSesionConBD();
    $tx = [
        'id' => count($_SESSION['billetera']['transacciones']) + 1,
        'tipo' => $tipo,
        'monto' => $monto,
        'saldo_resultante' => $saldoResultante,
        'descripcion' => $descripcion,
        'referencia' => $ref ?? 'TX-' . strtoupper(bin2hex(random_bytes(4))),
        'fecha' => date('Y-m-d H:i:s')
    ];
    array_unshift($_SESSION['billetera']['transacciones'], $tx);
}

/**
 * Reembolso de seguridad para la SESIÓN del solicitante: si esta sesión
 * retenía escrow para $codigo (sala cancelada y borrada), devolverlo en su
 * totalidad. Idempotente gracias al marcador por sala (escrow_sala).
 */
function autoReembolsarEscrowSesion(string $codigo): void {
    if (!$codigo) return;
    if (($_SESSION['codigo_sala'] ?? '') !== $codigo) return; // el escrow retenido es de ESTA sala
    $retenido = floatval($_SESSION['escrow_sala'][$codigo] ?? 0);
    if ($retenido <= 0) return;

    reembolsarSaldoEscrow($retenido, $codigo);

    // BD también (usuarios logueados): el tope min(fichas, saldo_retenido)
    // lo hace idempotente aunque el reembolso por cancelación ya se hiciera.
    if (isset($_SESSION['usuario_id'])) {
        try {
            reembolsarSaldoEscrowBD((int)$_SESSION['usuario_id'], $retenido, $codigo);
        } catch (\Throwable $e) {
            error_log('[AUTO_REEMBOLSO_SESION] Error devolviendo escrow en BD: ' . $e->getMessage());
        }
    }
}

function retenerSaldoEscrow(int $fichas, string $codigoSala): bool {
    inicializarSesionConBD();
    if ($_SESSION['billetera']['saldo_disponible'] < $fichas) {
        return false;
    }
    $_SESSION['billetera']['saldo_disponible'] -= $fichas;
    $_SESSION['billetera']['saldo_retenido']   += $fichas;
    // Marcador por sala: cuánto retiene ESTA sala en la sesión (permite
    // reembolsar con exactitud el escrow de una sala cancelada/borrada)
    $_SESSION['escrow_sala'][$codigoSala] = floatval($_SESSION['escrow_sala'][$codigoSala] ?? 0) + $fichas;
    registrarTransaccion('BUY_IN', -$fichas, $_SESSION['billetera']['saldo_disponible'], "Retención Escrow Buy-in Sala {$codigoSala}");
    return true;
}

function reembolsarSaldoEscrow(int $fichas, string $codigoSala): void {
    inicializarSesionConBD();
    if ($_SESSION['billetera']['saldo_retenido'] >= $fichas) {
        $_SESSION['billetera']['saldo_retenido'] -= $fichas;
    }
    $_SESSION['billetera']['saldo_disponible'] += $fichas;
    // Actualizar marcador por sala (nunca negativo)
    $retenidoSala = floatval($_SESSION['escrow_sala'][$codigoSala] ?? 0);
    $_SESSION['escrow_sala'][$codigoSala] = max(0, $retenidoSala - $fichas);
    registrarTransaccion('REEMBOLSO', $fichas, $_SESSION['billetera']['saldo_disponible'], "Reembolso Buy-in Sala {$codigoSala}");
}

function acreditarPremioGanador(int $fichas, string $codigoSala): void {
    inicializarSesionConBD();
    $_SESSION['billetera']['saldo_disponible'] += $fichas;
    registrarTransaccion('PREMIO', $fichas, $_SESSION['billetera']['saldo_disponible'], "Premio Pozo Neto Bingo Sala {$codigoSala}");
}

function roomFile(string $code): string {
    return DATA_DIR . 'room_' . preg_replace('/[^A-Z0-9]/', '', $code) . '.json';
}

function cargarSala(string $code): ?array {
    $f = roomFile($code);
    if (!file_exists($f)) return null;
    $data = json_decode(file_get_contents($f), true);
    return is_array($data) ? $data : null;
}

function guardarSala(array $sala): void {
    file_put_contents(roomFile($sala['codigo']), json_encode($sala, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Ejecuta una modificación ATÓMICA sobre el archivo de una sala.
 *
 * El archivo se bloquea (flock) durante toda la sección crítica
 * leer → modificar → escribir, de modo que escrituras concurrentes
 * (polls de verificar_estado, marcar casilla, cantar bingo) no se
 * pisan entre sí y se pierdan marcados o fichas.
 *
 * @param string   $codigo Código de la sala
 * @param callable $fn     Recibe la sala (array). Debe devolver la sala
 *                         modificada, o null para abortar SIN escribir.
 * @return array|null La sala resultante, o null si no existe / se abortó.
 */
function modificarSala(string $codigo, callable $fn): ?array {
    $f = roomFile($codigo);
    // 'r+' no crea el archivo: si otro proceso lo borró, fallamos limpiamente
    $fp = @fopen($f, 'r+');
    if (!$fp) return null;

    try {
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return null;
        }

        $raw = stream_get_contents($fp);
        $sala = json_decode($raw, true);
        if (!is_array($sala)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return null;
        }

        $resultado = $fn($sala);

        if ($resultado !== null) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
        fclose($fp);
        return is_array($resultado) ? $resultado : $sala;
    } catch (\Throwable $e) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
        throw $e;
    }
}

/**
 * Reembolsar escrow a TODOS los jugadores de una sala (sesión + BD)
 * Se usa cuando la sala se cancela o elimina
 */
function reembolsarEscrowSalaCompleta(array $sala): void {
    if (($sala['tipo_sala'] ?? 'free') !== 'payplay') return;
    $buyIn = floatval($sala['buy_in'] ?? 0);
    if ($buyIn <= 0) return;

    // Blindaje: si el escrow ya fue reembolsado (cancelación explícita),
    // no pagar dos veces aunque la sala se elimine más tarde.
    if (!empty($sala['escrow_reembolsado'])) return;
    
    foreach ($sala['jugadores'] as $jugador) {
        $buyInPagado = floatval($jugador['buy_in_pagado'] ?? $buyIn);
        if ($buyInPagado <= 0) continue;
        
        // Reembolso en sesión (si el usuario está en esta sesión)
        if (isset($_SESSION['jugador_id']) && $_SESSION['jugador_id'] === $jugador['id']) {
            reembolsarSaldoEscrow($buyInPagado, $sala['codigo']);
        }
        
        // Reembolso en BD (si el jugador tiene usuario_id real)
        if (isset($jugador['usuario_id']) && $jugador['usuario_id'] > 0) {
            reembolsarSaldoEscrowBD((int)$jugador['usuario_id'], $buyInPagado, $sala['codigo']);
        }
    }
}

function eliminarSala(string $code): void {
    $sala = cargarSala($code);
    if ($sala) {
        reembolsarEscrowSalaCompleta($sala);
    }
    $f = roomFile($code);
    if (file_exists($f)) @unlink($f);
}

function generarCodigo(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 4; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    return $code;
}

function codigoUnico(): string {
    $code = generarCodigo();
    $tries = 0;
    while (file_exists(roomFile($code)) && $tries < 30) { $code = generarCodigo(); $tries++; }
    return $code;
}

function rangosNomenclatura(string $modo): array {
    if ($modo === 'reducido') {
        return ['A' => [0,19], 'B' => [20,39], 'C' => [40,59], 'D' => [60,79], 'E' => [80,99]];
    }
    return ['A' => [0,99], 'B' => [0,99], 'C' => [0,99], 'D' => [0,99], 'E' => [0,99]];
}

function generarCarton(string $modo = 'normal'): array {
    $rangos = rangosNomenclatura($modo);
    $carton = [];
    foreach ($rangos as $l => [$min, $max]) {
        $pool = range($min, $max); shuffle($pool);
        $col = [];
        for ($i = 0; $i < 5; $i++) $col[] = sprintf('%s%02d', $l, $pool[$i]);
        $carton[] = $col;
    }
    return $carton;
}

function todasLasFichas(string $modo = 'normal'): array {
    $rangos = rangosNomenclatura($modo);
    $fichas = [];
    foreach ($rangos as $l => [$min, $max])
        for ($i = $min; $i <= $max; $i++) $fichas[] = sprintf('%s%02d', $l, $i);
    return $fichas;
}

function generarSiguienteFicha(array &$sala): void {
    if ($sala['estado'] !== 'jugando' || !$sala['proxima_ficha_at'] || time() < $sala['proxima_ficha_at']) return;
    $modo = $sala['modo'] ?? 'normal';
    $disponibles = array_values(array_diff(todasLasFichas($modo), $sala['historial']));
    if (empty($disponibles)) {
        $sala['estado'] = 'finalizado';
        $sala['ganador_nombre'] = 'Nadie (fichas agotadas)';
        $sala['finalizado_at'] = time();
        return;
    }
    shuffle($disponibles);
    $sala['historial'][] = $disponibles[0];
    $intervalo = in_array($sala['intervalo_fichas'] ?? 0, [5, 10, 15]) ? (int)$sala['intervalo_fichas'] : 10;
    $sala['proxima_ficha_at'] = time() + $intervalo;
}

function validarBingo(array $cartonGrid, array $marcadas): bool {
    $grid = [];
    for ($fila = 0; $fila < 5; $fila++) {
        $grid[$fila] = [];
        for ($col = 0; $col < 5; $col++) $grid[$fila][$col] = $cartonGrid[$col][$fila];
    }
    for ($fila = 0; $fila < 5; $fila++)
        if (count(array_intersect($grid[$fila], $marcadas)) === 5) return true;
    for ($col = 0; $col < 5; $col++)
        if (count(array_intersect(array_column($grid, $col), $marcadas)) === 5) return true;
    $d1 = []; $d2 = [];
    for ($i = 0; $i < 5; $i++) { $d1[] = $grid[$i][$i]; $d2[] = $grid[$i][4-$i]; }
    if (count(array_intersect($d1, $marcadas)) === 5) return true;
    if (count(array_intersect($d2, $marcadas)) === 5) return true;
    return false;
}

function sanitizar(string $s): string {
    return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
}

function jsonOk(array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function jsonError(string $msg): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function requireSession(): void {
    if (!isset($_SESSION['jugador_id']) || !isset($_SESSION['codigo_sala'])) {
        header('Location: ../index.php'); exit;
    }
}

function limpiarSalaExpirada(string $code): void {
    $sala = cargarSala($code);
    if ($sala && isset($sala['finalizado_at']) && (time() - $sala['finalizado_at']) > 60) {
        // Reembolsar escrow antes de eliminar (para salas payplay canceladas/terminadas)
        reembolsarEscrowSalaCompleta($sala);
        eliminarSala($code);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// FUNCIONES DE SINCRONIZACIÓN CON BASE DE DATOS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Obtener o crear la billetera del usuario en BD
 */
function obtenerBilleteraBD(int $usuarioId): ?array {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT * FROM billeteras WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?: null;
}

/**
 * Sincronizar saldo de sesión con BD
 */
function sincronizarBilleteraSesion(int $usuarioId): void {
    inicializarSesionConBD();
    $billeteraBD = obtenerBilleteraBD($usuarioId);
    if ($billeteraBD) {
        $_SESSION['billetera']['saldo_disponible'] = floatval($billeteraBD['saldo_disponible']);
        $_SESSION['billetera']['saldo_retenido'] = floatval($billeteraBD['saldo_retenido']);
    }
}

/**
 * Registrar transacción en BD
 */
function registrarTransaccionBD(int $usuarioId, string $tipo, float $monto, float $saldoResultante, string $descripcion, ?string $referencia = null): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare(
        'INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
         VALUES ((SELECT id FROM billeteras WHERE usuario_id = ?), ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$usuarioId, $tipo, $monto, $saldoResultante, $descripcion, $referencia]);
}

/**
 * Retener saldo en escrow en BD
 */
function retenerSaldoEscrowBD(int $usuarioId, float $fichas, string $codigoSala): bool {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Bloquear fila para evitar race conditions (S-05)
        $stmt = $pdo->prepare('SELECT id, saldo_disponible FROM billeteras WHERE usuario_id = ? FOR UPDATE');
        $stmt->execute([$usuarioId]);
        $billetera = $stmt->fetch();
        
        if (!$billetera || floatval($billetera['saldo_disponible']) < $fichas) {
            $pdo->rollBack();
            return false;
        }
        
        // Actualizar saldo
        $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = saldo_disponible - ?, saldo_retenido = saldo_retenido + ? WHERE id = ?');
        $stmt->execute([$fichas, $fichas, $billetera['id']]);
        
        // Registrar transacción
        $nuevoSaldo = floatval($billetera['saldo_disponible']) - $fichas;
        registrarTransaccionBD($usuarioId, 'BUY_IN', -$fichas, $nuevoSaldo, "Retención Escrow Buy-in Sala {$codigoSala}", 'BUYIN-' . $codigoSala . '-' . time());
        
        $pdo->commit();
        return true;
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log('[ESCROW] Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Reembolsar saldo de escrow en BD
 */
function reembolsarSaldoEscrowBD(int $usuarioId, float $fichas, string $codigoSala): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('SELECT id, saldo_disponible, saldo_retenido FROM billeteras WHERE usuario_id = ? FOR UPDATE');
        $stmt->execute([$usuarioId]);
        $billetera = $stmt->fetch();
        
        if (!$billetera) {
            $pdo->rollBack();
            return;
        }
        
        $reembolso = min($fichas, floatval($billetera['saldo_retenido']));
        
        $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = saldo_disponible + ?, saldo_retenido = saldo_retenido - ? WHERE id = ?');
        $stmt->execute([$reembolso, $reembolso, $billetera['id']]);
        
        $nuevoSaldo = floatval($billetera['saldo_retenido']) - $reembolso + floatval($billetera['saldo_disponible']) + $reembolso;
        registrarTransaccionBD($usuarioId, 'REEMBOLSO', $reembolso, $nuevoSaldo, "Reembolso Buy-in Sala {$codigoSala}", 'REFUND-' . $codigoSala . '-' . time());
        
        $pdo->commit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log('[REFUND] Error: ' . $e->getMessage());
    }
}

/**
 * Acreditar premio a ganador en BD
 */
function acreditarPremioGanadorBD(int $usuarioId, float $fichas, string $codigoSala): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('SELECT id, saldo_disponible FROM billeteras WHERE usuario_id = ? FOR UPDATE');
        $stmt->execute([$usuarioId]);
        $billetera = $stmt->fetch();
        
        if (!$billetera) {
            $pdo->rollBack();
            return;
        }
        
        $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = saldo_disponible + ? WHERE id = ?');
        $stmt->execute([$fichas, $billetera['id']]);
        
        $nuevoSaldo = floatval($billetera['saldo_disponible']) + $fichas;
        registrarTransaccionBD($usuarioId, 'PREMIO', $fichas, $nuevoSaldo, "Premio Pozo Neto Bingo Sala {$codigoSala}", 'PREMIO-' . $codigoSala . '-' . time());
        
        $pdo->commit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log('[PREMIO] Error: ' . $e->getMessage());
    }
}

/**
 * Acreditar premio a ganador en BD DENTRO de una transacción ya abierta.
 * A diferencia de acreditarPremioGanadorBD(), NO gestiona su propia
 * transacción: el llamador decide commit/rollback (todo o nada).
 * Lanza excepción si falla, para que el llamador revierta la operación.
 */
function acreditarPremioGanadorBDSinTx(int $usuarioId, float $fichas, string $codigoSala): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;

    $stmt = $pdo->prepare('SELECT id, saldo_disponible FROM billeteras WHERE usuario_id = ? FOR UPDATE');
    $stmt->execute([$usuarioId]);
    $billetera = $stmt->fetch();

    if (!$billetera) {
        throw new \RuntimeException("Billetera no encontrada para el usuario {$usuarioId}");
    }

    $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = saldo_disponible + ? WHERE id = ?');
    $stmt->execute([$fichas, $billetera['id']]);

    $nuevoSaldo = floatval($billetera['saldo_disponible']) + $fichas;
    registrarTransaccionBD($usuarioId, 'PREMIO', $fichas, $nuevoSaldo, "Premio Pozo Neto Bingo Sala {$codigoSala}", 'PREMIO-' . $codigoSala . '-' . time());
}

/**
 * Guardar sala en BD
 */
function guardarSalaBD(array $sala): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    // Usar host_id_bd (integer usuario_id) en lugar de host_id (string aleatorio)
    $hostIdBD = $sala['host_id_bd'] ?? ($sala['host_id'] ?? 0);
    
    // Para salas payplay NUEVAS, insertar con pozo_total = 0.
    // El trigger trg_jugador_sala_insert se encargará de sumar al agregar jugadores.
    // Para UPDATE o salas free, usar el pozo_total real.
    $stmtCheck = $pdo->prepare('SELECT 1 FROM salas WHERE codigo = ?');
    $stmtCheck->execute([$sala['codigo']]);
    $isNew = !$stmtCheck->fetch();
    
    $pozoTotal = ($isNew && ($sala['tipo_sala'] ?? '') === 'payplay') ? 0 : ($sala['pozo_total'] ?? 0);
    
    $stmt = $pdo->prepare(
        'INSERT INTO salas (codigo, nombre, tipo_sala, buy_in, comision_pct, pozo_total, estado, host_id, modo, intervalo_fichas)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         nombre = VALUES(nombre),
         estado = VALUES(estado),
         pozo_total = VALUES(pozo_total),
         modo = VALUES(modo),
         intervalo_fichas = VALUES(intervalo_fichas)'
    );
    $stmt->execute([
        $sala['codigo'],
        $sala['nombre'],
        $sala['tipo_sala'],
        $sala['buy_in'],
        $sala['comision_pct'] ?? 0.10,
        $pozoTotal,
        $sala['estado'],
        $hostIdBD,
        $sala['modo'] ?? 'normal',
        $sala['intervalo_fichas'] ?? 10
    ]);
}

/**
 * Cargar sala desde BD
 */
function cargarSalaBD(string $codigo): ?array {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT * FROM salas WHERE codigo = ?');
    $stmt->execute([$codigo]);
    $sala = $stmt->fetch();
    
    if (!$sala) return null;
    
    // Cargar jugadores
    $stmt = $pdo->prepare('SELECT * FROM jugadores_sala WHERE sala_id = ? ORDER BY posicion');
    $stmt->execute([$sala['id']]);
    $sala['jugadores'] = $stmt->fetchAll();
    
    // Cargar historial de fichas
    $stmt = $pdo->prepare('SELECT ficha FROM historial_fichas WHERE sala_id = ? ORDER BY cantada_at');
    $stmt->execute([$sala['id']]);
    $sala['historial'] = array_column($stmt->fetchAll(), 'ficha');
    
    return $sala;
}

/**
 * Actualizar sala en BD
 */
function actualizarSalaBD(array $sala): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare(
        'UPDATE salas SET estado = ?, pozo_total = ?, modo = ?, intervalo_fichas = ?, iniciado_at = ?, finalizado_at = ? WHERE codigo = ?'
    );
    $stmt->execute([
        $sala['estado'],
        $sala['pozo_total'] ?? 0,
        $sala['modo'] ?? 'normal',
        $sala['intervalo_fichas'] ?? 10,
        $sala['iniciado_at'] ?? null  ? date('Y-m-d H:i:s', $sala['iniciado_at']) : null,
        $sala['finalizado_at'] ?? null ? date('Y-m-d H:i:s', $sala['finalizado_at']) : null,
        $sala['codigo']
    ]);
}

/**
 * Agregar jugador a sala en BD
 */
function agregarJugadorSalaBD(int $salaId, int $usuarioId, float $buyIn, int $posicion): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare(
        'INSERT INTO jugadores_sala (sala_id, usuario_id, buy_in_pagado, posicion)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE buy_in_pagado = VALUES(buy_in_pagado)'
    );
    $stmt->execute([$salaId, $usuarioId, $buyIn, $posicion]);
}

/**
 * Agregar ficha al historial en BD
 */
function agregarFichaHistorialBD(int $salaId, string $ficha): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('INSERT INTO historial_fichas (sala_id, ficha) VALUES (?, ?)');
    $stmt->execute([$salaId, $ficha]);
}

/**
 * Guardar cartón en BD (idempotente: actualiza si ya existe, si no inserta)
 */
function guardarCartonBD(int $jugadorSalaId, int $numeroCarton, array $grid, array $marcadas): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM cartones WHERE jugador_sala_id = ? AND numero_carton = ?');
    $stmt->execute([$jugadorSalaId, $numeroCarton]);
    $existente = $stmt->fetch();

    if ($existente) {
        $stmt = $pdo->prepare('UPDATE cartones SET grid = ?, marcadas = ? WHERE id = ?');
        $stmt->execute([json_encode($grid), json_encode($marcadas), $existente['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO cartones (jugador_sala_id, numero_carton, grid, marcadas)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$jugadorSalaId, $numeroCarton, json_encode($grid), json_encode($marcadas)]);
    }
}

/**
 * Obtener el ID REAL del cartón de un jugador en BD.
 * Devuelve null si el jugador no tiene cartón registrado (invitados, etc.).
 * Ojo: $jugadorSalaId es un id de jugadores_sala, NO un id de cartones.
 */
function obtenerCartonIdBD(int $jugadorSalaId, int $numeroCarton = 1): ?int {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM cartones WHERE jugador_sala_id = ? AND numero_carton = ?');
    $stmt->execute([$jugadorSalaId, $numeroCarton]);
    $result = $stmt->fetch();
    return $result ? (int)$result['id'] : null;
}

/**
 * Obtener cartón de jugador desde BD
 */
function obtenerCartonBD(int $jugadorSalaId, int $numeroCarton = 1): ?array {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT * FROM cartones WHERE jugador_sala_id = ? AND numero_carton = ?');
    $stmt->execute([$jugadorSalaId, $numeroCarton]);
    $carton = $stmt->fetch();
    
    if (!$carton) return null;
    
    return [
        'id' => $carton['id'],
        'grid' => json_decode($carton['grid'], true),
        'marcadas' => json_decode($carton['marcadas'], true),
        'es_ganador' => (bool)$carton['es_ganador']
    ];
}

/**
 * Actualizar cartón en BD
 */
function actualizarCartonBD(int $cartonId, array $marcadas): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('UPDATE cartones SET marcadas = ? WHERE id = ?');
    $stmt->execute([json_encode($marcadas), $cartonId]);
}

/**
 * Registrar ganador en BD (tabla dedicada partidas_resultado).
 * - UNIQUE(uq_resultado_sala) garantiza un solo resultado por sala (idempotencia igual
 *   que la antigua uq_ganadores_sala): si la sala ya tiene resultado, se actualiza.
 * - $cartonId se mantiene en la firma por compatibilidad; el cartón ganador ya queda
 *   marcado en cartones.es_ganador.
 */
function registrarGanadorBD(int $salaId, int $usuarioId, ?int $cartonId, float $premio): void {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare(
        'INSERT INTO partidas_resultado (sala_id, ganador_id, premio_ganado)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE ganador_id = VALUES(ganador_id), premio_ganado = VALUES(premio_ganado)'
    );
    $stmt->execute([$salaId, $usuarioId, $premio]);
}

/**
 * Obtener ID de sala por código
 */
function obtenerSalaIdPorCodigo(string $codigo): ?int {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT id FROM salas WHERE codigo = ?');
    $stmt->execute([$codigo]);
    $result = $stmt->fetch();
    return $result ? (int)$result['id'] : null;
}

/**
 * Obtener ID de jugador_sala por usuario y sala
 */
function obtenerJugadorSalaId(int $salaId, int $usuarioId): ?int {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT id FROM jugadores_sala WHERE sala_id = ? AND usuario_id = ?');
    $stmt->execute([$salaId, $usuarioId]);
    $result = $stmt->fetch();
    return $result ? (int)$result['id'] : null;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FUNCIÓN CENTRALIZADA: Crear usuario completo (usuario + billetera + KYC + bono)
// Elimina código duplicado entre registro.php y google.php
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Crea un usuario completo en la base de datos con su billetera, KYC y bono de bienvenida
 * 
 * @param string $nickname Nickname del usuario
 * @param string $email Email del usuario
 * @param string|null $passwordHash Hash de contraseña (null si es cuenta Google)
 * @param string|null $googleId ID de Google (null si es registro normal)
 * @return int ID del usuario creado
 * @throws \PDOException Si hay error en la base de datos
 */
function crearUsuarioCompleto(
    string $nickname, 
    string $email, 
    ?string $passwordHash = null, 
    ?string $googleId = null,
    ?string $tipoDocumento = null,
    ?string $numeroDocumento = null,
    ?string $telefono = null,
    ?string $fechaNacimiento = null
): int {
    require_once __DIR__ . '/../database/db.php';
    global $pdo;
    
    $bonoBienvenida = 0.00; // Sin bono de bienvenida: el usuario recarga desde la app
    
    $pdo->beginTransaction();
    
    try {
        // 1. Insertar usuario (Nivel 1 KYC automático si se registra con documento;
        //    los datos de documento viven ahora en la propia tabla usuarios)
        $nivelKycInicial = (!empty($tipoDocumento) && !empty($numeroDocumento)) ? 1 : 0;
        $stmtUser = $pdo->prepare(
            'INSERT INTO usuarios (nickname, email, password_hash, google_id, nivel_verificacion, documento_tipo, documento_num, rol, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'JUGADOR\', \'ACTIVO\')'
        );
        $stmtUser->execute([$nickname, $email, $passwordHash, $googleId, $nivelKycInicial, $tipoDocumento, $numeroDocumento]);
        $userId = (int) $pdo->lastInsertId();
        
        // 2. Crear billetera con bono de bienvenida
        $stmtBill = $pdo->prepare(
            'INSERT INTO billeteras (usuario_id, saldo_disponible, saldo_retenido)
             VALUES (?, ?, 0.00)'
        );
        $stmtBill->execute([$userId, $bonoBienvenida]);
        
        // 3. Registrar transacción de bono de bienvenida (solo si hay bono)
        if ($bonoBienvenida > 0) {
            $stmtTx = $pdo->prepare(
                'INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
                 VALUES ((SELECT id FROM billeteras WHERE usuario_id = ?), \'BONO\', ?, ?, \'Bono de bienvenida plataforma V2.2\', ?)'
            );
            $stmtTx->execute([$userId, $bonoBienvenida, $bonoBienvenida, 'BONO-WELCOME-' . str_pad($userId, 6, '0', STR_PAD_LEFT)]);
        }
        
        $pdo->commit();
        
        return $userId;
    } catch (\PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}
