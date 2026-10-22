<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$codigo = strtoupper(trim($_GET['codigo'] ?? ''));
$miId   = $_SESSION['jugador_id'] ?? '';

// ── GET: estado general de la sala (lobby + juego) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'estado') {
    if (!file_exists(roomFile($codigo))) {
        // Auto-reembolso de seguridad: mi sesión retenía escrow para ESTA
        // sala y ya no existe (fue cancelada y borrada). Devolverlo.
        autoReembolsarEscrowSesion($codigo);
        jsonError('Sala no encontrada');
    }

    limpiarSalaExpirada($codigo);

    // limpiarSalaExpirada pudo borrar la sala recién (cancelada y expirada):
    // si mi sesión aún retenía escrow de ella, devolverlo antes de responder.
    if (!file_exists(roomFile($codigo))) {
        autoReembolsarEscrowSesion($codigo);
        jsonError('Sala no encontrada');
    }

    // Auto-generar ficha si toca (sección crítica con flock: sin esto,
    // los polls concurrentes se pisaban los marcados y las fichas)
    $fichaNueva = false;
    $sala = modificarSala($codigo, function (array $sala) use (&$fichaNueva) {
        if (($sala['estado'] ?? '') !== 'jugando') return null; // nada que generar
        $antesHistorial = count($sala['historial']);
        $antesEstado    = $sala['estado'];
        generarSiguienteFicha($sala);
        $nueva = count($sala['historial']) > $antesHistorial;
        if ($nueva) $fichaNueva = true;
        // Solo reescribir si algo cambió
        return ($nueva || $sala['estado'] !== $antesEstado) ? $sala : null;
    });
    if (!$sala) {
        // null = sin cambios o sala inexistente: reintentar como lectura
        $sala = cargarSala($codigo);
        if (!$sala) jsonError('Sala no encontrada');
    }

    // Sincronizar SOLO la ficha recién generada con BD
    if ($fichaNueva && isset($_SESSION['usuario_id']) && !empty($sala['historial'])) {
        try {
            require_once __DIR__ . '/../database/db.php';
            global $pdo;
             
            $pdo->beginTransaction();
             
            $salaId = obtenerSalaIdPorCodigo($codigo);
            if ($salaId) {
                $ultimaFicha = end($sala['historial']);
                agregarFichaHistorialBD($salaId, $ultimaFicha);
            }
             
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            error_log('[VERIFICAR_ESTADO] Error sincronizando ficha: ' . $e->getMessage());
        }
    }

    $pozoTotal   = floatval($sala['pozo_total'] ?? (count($sala['jugadores']) * ($sala['buy_in'] ?? 0)));
    $comisionPct = floatval($sala['comision_pct'] ?? 0.10);
    $pozoNeto    = round($pozoTotal * (1 - $comisionPct), 2);

    // Si usuario logueado y sala payplay, sincronizar pozo_total desde BD (trigger lo mantiene actualizado)
    if (isset($_SESSION['usuario_id']) && ($sala['tipo_sala'] ?? 'free') === 'payplay') {
        try {
            require_once __DIR__ . '/../database/db.php';
            global $pdo;
            $salaId = obtenerSalaIdPorCodigo($codigo);
            if ($salaId) {
                $stmt = $pdo->prepare('SELECT pozo_total FROM salas WHERE id = ?');
                $stmt->execute([$salaId]);
                $row = $stmt->fetch();
                if ($row) {
                    $pozoTotalBD = floatval($row['pozo_total']);
                    $pozoActual  = floatval($sala['pozo_total'] ?? 0);
                    if ($pozoTotalBD > 0 && abs($pozoTotalBD - $pozoActual) > 0.001) {
                        $pozoTotal = $pozoTotalBD;
                        $pozoNeto = round($pozoTotal * (1 - $comisionPct), 2);
                        // Actualizar JSON para mantener sincronizado
                        // (bajo candado, para no pisar marcados/fichas de otros)
                        modificarSala($codigo, function (array $s) use ($pozoTotalBD) {
                            $s['pozo_total'] = $pozoTotalBD;
                            return $s;
                        });
                        $sala['pozo_total'] = $pozoTotal;
                    }
                }
            }
        } catch (\PDOException $e) {
            error_log('[VERIFICAR_ESTADO] Error sincronizando pozo: ' . $e->getMessage());
        }
    }

    jsonOk(['sala' => [
        'codigo'            => $sala['codigo'],
        'nombre'            => $sala['nombre'],
        'tipo_sala'         => $sala['tipo_sala'] ?? 'free',
        'buy_in'            => $sala['buy_in'] ?? 0,
        'pozo_total'        => $pozoTotal,
        'pozo_neto'         => $pozoNeto,
        'comision_pct'      => $comisionPct,
        'premio_ganado'     => $sala['premio_ganado'] ?? null,
        'estado'            => $sala['estado'],
        'host_id'           => $sala['host_id'],
        'jugadores'         => $sala['jugadores'],
        'historial'         => $sala['historial'],
        'proxima_ficha_at'  => $sala['proxima_ficha_at'],
        'intervalo_fichas'  => $sala['intervalo_fichas'],
        'ganador_nombre'    => $sala['ganador_nombre'],
        'modo'              => $sala['modo'] ?? 'normal',
    ]]);
}

// ── GET: obtener cartón del jugador ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'carton') {
    $sala = cargarSala($codigo);
    if (!$sala) jsonError('Sala no encontrada');
    if (!isset($sala['cartones'][$miId])) jsonError('Cartón no encontrado');
    $c = $sala['cartones'][$miId];
    jsonOk(['grid' => $c['grid'], 'marcadas' => $c['marcadas']]);
}

// ── POST: marcar casilla ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) jsonError('Datos inválidos');

    $accionPost = $body['accion'] ?? '';
    $codigoPost = strtoupper(trim($body['codigo'] ?? ''));

    if ($accionPost === 'marcar') {
        $valor = trim($body['valor'] ?? '');
        if (!preg_match('/^[ABCDE][0-9]{2}$/', $valor)) jsonError('Valor de ficha inválido');

        if (!file_exists(roomFile($codigoPost))) jsonError('Sala no encontrada');

        $errorMarcar   = null;
        $marcadasNuevas = null;

        // Sección crítica con flock: la lectura, la marcada y la escritura
        // son atómicas frente a los polls concurrentes de otros jugadores.
        modificarSala($codigoPost, function (array $sala) use ($miId, $valor, &$errorMarcar, &$marcadasNuevas) {
            if (($sala['estado'] ?? '') !== 'jugando') { $errorMarcar = 'La partida no está activa'; return null; }
            if (!isset($sala['cartones'][$miId]))      { $errorMarcar = 'Cartón no encontrado'; return null; }

            // Anti-trampa: la ficha debe estar en el historial
            if (!in_array($valor, $sala['historial'])) { $errorMarcar = 'Esa ficha aún no ha salido'; return null; }

            // Verificar que la ficha está en el cartón del jugador
            $todasCeldas = array_merge(...$sala['cartones'][$miId]['grid']);
            if (!in_array($valor, $todasCeldas)) { $errorMarcar = 'Esa ficha no está en tu cartón'; return null; }

            // Marcar (sin duplicados)
            if (!in_array($valor, $sala['cartones'][$miId]['marcadas'])) {
                $sala['cartones'][$miId]['marcadas'][] = $valor;
                $marcadasNuevas = $sala['cartones'][$miId]['marcadas'];
                return $sala;
            }
            return null; // ya estaba marcada: nada que escribir
        });

        if ($errorMarcar !== null) jsonError($errorMarcar);

        // Sincronizar con BD usando transacción
        if ($marcadasNuevas !== null && isset($_SESSION['usuario_id'])) {
            try {
                require_once __DIR__ . '/../database/db.php';
                global $pdo;
                 
                $pdo->beginTransaction();
                 
                $salaId = obtenerSalaIdPorCodigo($codigoPost);
                if ($salaId) {
                    $jugadorSalaId = obtenerJugadorSalaId($salaId, (int)$_SESSION['usuario_id']);
                    if ($jugadorSalaId) {
                        // actualizarCartonBD espera un id REAL de cartones,
                        // no un jugador_sala_id
                        $cartonId = obtenerCartonIdBD($jugadorSalaId);
                        if ($cartonId) {
                            actualizarCartonBD($cartonId, $marcadasNuevas);
                        }
                    }
                }
                 
                $pdo->commit();
            } catch (\PDOException $e) {
                $pdo->rollBack();
                error_log('[MARCAR_CASILLA] Error sincronizando con BD: ' . $e->getMessage());
            }
        }
        jsonOk();
    }

    if ($accionPost === 'salir') {
        // Sección crítica con flock; la eliminación de la sala (si era el
        // último jugador) se hace DESPUÉS de liberar el candado.
        $salaFinal = modificarSala($codigoPost, function (array $sala) use ($miId, $codigoPost) {
            $tipoSala = $sala['tipo_sala'] ?? 'free';
            $buyIn    = floatval($sala['buy_in'] ?? 0);

            if ($sala['estado'] === 'esperando') {
                // Reembolso de Escrow si es Payplay (RF-20)
                if ($tipoSala === 'payplay' && $buyIn > 0) {
                    reembolsarSaldoEscrow($buyIn, $codigoPost);
                    $sala['pozo_total'] = max(0, ($sala['pozo_total'] ?? 0) - $buyIn);

                    // Reembolso en BD si usuario logueado
                    if (isset($_SESSION['usuario_id'])) {
                        $usuarioId = (int)$_SESSION['usuario_id'];
                        reembolsarSaldoEscrowBD($usuarioId, $buyIn, $codigoPost);

                        // Actualizar pozo_total en BD (no hay trigger para DELETE)
                        try {
                            require_once __DIR__ . '/../database/db.php';
                            global $pdo;
                            $salaId = obtenerSalaIdPorCodigo($codigoPost);
                            if ($salaId) {
                                $stmt = $pdo->prepare('UPDATE salas SET pozo_total = pozo_total - ? WHERE id = ? AND pozo_total >= ?');
                                $stmt->execute([$buyIn, $salaId, $buyIn]);
                            }
                        } catch (\PDOException $e) {
                            error_log('[SALIR_SALA] Error actualizando pozo BD: ' . $e->getMessage());
                        }
                    }
                }

                // En lobby: eliminar jugador del array
                $sala['jugadores'] = array_values(array_filter($sala['jugadores'], fn($j) => $j['id'] !== $miId));
                if (($sala['host_id'] ?? '') === $miId && !empty($sala['jugadores'])) {
                    $sala['host_id'] = $sala['jugadores'][0]['id'];
                }
                return $sala;

            } elseif ($sala['estado'] === 'jugando') {
                // En partida: marcar jugador como ausente
                foreach ($sala['jugadores'] as &$j) {
                    if ($j['id'] === $miId) { $j['ausente'] = true; break; }
                }
                unset($j);
                // Si TODOS los jugadores están ausentes → cancelar partida y reembolsar escrow
                $activos = array_filter($sala['jugadores'], fn($j) => empty($j['ausente']));
                if (empty($activos)) {
                    $sala['estado']        = 'cancelado';
                    $sala['finalizado_at'] = time();
                    $sala['pozo_total']    = 0; // Pozo a cero al cancelar
                    // Reembolsar escrow a todos los jugadores (RF-20)
                    if (($sala['tipo_sala'] ?? 'free') === 'payplay') {
                        reembolsarEscrowSalaCompleta($sala);
                    }
                    // Sincronizar estado cancelado a BD
                    if (isset($_SESSION['usuario_id'])) {
                        actualizarSalaBD($sala);
                    }
                }
                return $sala;
            }

            return null; // finalizado/cancelado: nada que hacer
        });

        // Si era el último jugador del lobby → eliminar la sala completa
        if (is_array($salaFinal)
            && ($salaFinal['estado'] ?? '') === 'esperando'
            && empty($salaFinal['jugadores'])) {
            eliminarSala($codigoPost);
        }

        unset($_SESSION['jugador_id'], $_SESSION['codigo_sala']);
        jsonOk();
    }

    if ($accionPost === 'reembolso_cancelacion') {
        // Auto-reembolso del propio usuario: su lobby detectó que la sala
        // fue cancelada. Reembolsa SU escrow (sesión + BD) de forma idempotente.
        $sala = cargarSala($codigoPost);
        if (!$sala) jsonError('Sala no encontrada');

        // Solo aplica a salas canceladas
        if (($sala['estado'] ?? '') !== 'cancelado') jsonError('La sala no está cancelada');

        // Localizar MI entrada de jugador en la sala
        $miEntrada = null;
        foreach ($sala['jugadores'] as $j) {
            if ($j['id'] === $miId) { $miEntrada = $j; break; }
        }
        if (!$miEntrada) jsonError('No estás registrado en esta sala');

        $buyInPagado    = floatval($miEntrada['buy_in_pagado'] ?? 0);
        $yaReembolsado  = !empty($miEntrada['reembolsado']);

        if ($buyInPagado > 0 && !$yaReembolsado) {
            // Sesión (invitados: aquí vive su dinero)
            reembolsarSaldoEscrow($buyInPagado, $codigoPost);
            // BD (usuarios logueados): cubierto por el tope saldo_retenido
            // → si el cancel ya lo pagó, esto no paga dos veces
            if (isset($_SESSION['usuario_id'])) {
                try {
                    reembolsarSaldoEscrowBD((int)$_SESSION['usuario_id'], $buyInPagado, $codigoPost);
                } catch (\Throwable $e) {
                    error_log('[REEMBOLSO_CANCELACION] Error BD: ' . $e->getMessage());
                }
            }
            // Marcar mi entrada como reembolsada (idempotencia explícita)
            modificarSala($codigoPost, function (array $s) use ($miId) {
                foreach ($s['jugadores'] as &$j) {
                    if ($j['id'] === $miId) { $j['reembolsado'] = true; break; }
                }
                unset($j);
                return $s;
            });
        }

        jsonOk(['reembolsado' => true, 'monto' => $buyInPagado]);
    }

    jsonError('Acción no válida');
}

jsonError('Solicitud no válida');
