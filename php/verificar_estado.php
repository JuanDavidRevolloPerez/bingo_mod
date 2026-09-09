<?php
session_start();
require_once 'funciones.php';
header('Content-Type: application/json');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$codigo = strtoupper(trim($_GET['codigo'] ?? ''));
$miId   = $_SESSION['jugador_id'] ?? '';

// ── GET: estado general de la sala (lobby + juego) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'estado') {
    $sala = cargarSala($codigo);
    if (!$sala) jsonError('Sala no encontrada');

    limpiarSalaExpirada($codigo);

    // Auto-generar ficha si toca
    if ($sala['estado'] === 'jugando') {
        generarSiguienteFicha($sala);
        guardarSala($sala);
    }

    $pozoTotal   = floatval($sala['pozo_total'] ?? (count($sala['jugadores']) * ($sala['buy_in'] ?? 0)));
    $comisionPct = floatval($sala['comision_pct'] ?? 0.10);
    $pozoNeto    = round($pozoTotal * (1 - $comisionPct), 2);

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

        $sala = cargarSala($codigoPost);
        if (!$sala)                          jsonError('Sala no encontrada');
        if ($sala['estado'] !== 'jugando')   jsonError('La partida no está activa');
        if (!isset($sala['cartones'][$miId])) jsonError('Cartón no encontrado');

        // Anti-trampa: la ficha debe estar en el historial
        if (!in_array($valor, $sala['historial'])) jsonError('Esa ficha aún no ha salido');

        // Verificar que la ficha está en el cartón del jugador
        $todasCeldas = array_merge(...$sala['cartones'][$miId]['grid']);
        if (!in_array($valor, $todasCeldas)) jsonError('Esa ficha no está en tu cartón');

        // Marcar (sin duplicados)
        if (!in_array($valor, $sala['cartones'][$miId]['marcadas'])) {
            $sala['cartones'][$miId]['marcadas'][] = $valor;
            guardarSala($sala);
        }
        jsonOk();
    }

    if ($accionPost === 'salir') {
        $sala = cargarSala($codigoPost);
        if ($sala) {
            $tipoSala = $sala['tipo_sala'] ?? 'free';
            $buyIn    = floatval($sala['buy_in'] ?? 0);

            if ($sala['estado'] === 'esperando') {
                // Reembolso de Escrow si es Payplay (RF-20)
                if ($tipoSala === 'payplay' && $buyIn > 0) {
                    reembolsarSaldoEscrow($buyIn, $codigoPost);
                    $sala['pozo_total'] = max(0, ($sala['pozo_total'] ?? 0) - $buyIn);
                }

                // En lobby: eliminar jugador del array
                $sala['jugadores'] = array_values(array_filter($sala['jugadores'], fn($j) => $j['id'] !== $miId));
                if ($sala['host_id'] === $miId && !empty($sala['jugadores'])) {
                    $sala['host_id'] = $sala['jugadores'][0]['id'];
                }
                if (empty($sala['jugadores'])) {
                    eliminarSala($codigoPost);
                } else {
                    guardarSala($sala);
                }

            } elseif ($sala['estado'] === 'jugando') {
                // En partida: marcar jugador como ausente
                foreach ($sala['jugadores'] as &$j) {
                    if ($j['id'] === $miId) { $j['ausente'] = true; break; }
                }
                unset($j);
                // Si TODOS los jugadores están ausentes → cancelar partida
                $activos = array_filter($sala['jugadores'], fn($j) => empty($j['ausente']));
                if (empty($activos)) {
                    $sala['estado']        = 'cancelado';
                    $sala['finalizado_at'] = time();
                }
                guardarSala($sala);
            }
        }
        unset($_SESSION['jugador_id'], $_SESSION['codigo_sala']);
        jsonOk();
    }

    jsonError('Acción no válida');
}

jsonError('Solicitud no válida');
