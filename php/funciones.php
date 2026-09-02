<?php
/**
 * funciones.php - Funciones compartidas del sistema de Bingo Online
 */

define('DATA_DIR', __DIR__ . '/../data/');

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

function eliminarSala(string $code): void {
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
    if ($sala && isset($sala['finalizado_at']) && (time() - $sala['finalizado_at']) > 60)
        eliminarSala($code);
}
