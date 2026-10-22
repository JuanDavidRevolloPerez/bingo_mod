<?php
/**
 * historial.php - Historial completo de transacciones
 * BINGO ONLINE V2.2
 */
session_start();
require_once 'php/funciones.php';
inicializarSesionConBD();

// Solo accesible con sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario   = $_SESSION['usuario'];
$kyc       = $_SESSION['kyc'];
$billetera = $_SESSION['billetera'];

// Verificar si es admin (para panel V2.2)
$esAdmin = false;
if (isset($_SESSION['usuario_id'])) {
    require_once __DIR__ . '/database/db.php';
    global $pdo;
    $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['usuario_id']]);
    $userData = $stmt->fetch();
    $esAdmin = ($userData && $userData['rol'] === 'ADMIN');
}

// Paginación
$porPagina = 20;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $porPagina;

// Filtros opcionales
$filtroTipo = $_GET['tipo'] ?? '';
$filtroFechaInicio = $_GET['fecha_inicio'] ?? '';
$filtroFechaFin = $_GET['fecha_fin'] ?? '';

// Cargar transacciones desde BD con paginación y filtros
require_once __DIR__ . '/database/db.php';
global $pdo;

$where = ['b.usuario_id = ?'];
$params = [$_SESSION['usuario_id']];

if ($filtroTipo) {
    $where[] = 't.tipo = ?';
    $params[] = $filtroTipo;
}
if ($filtroFechaInicio) {
    $where[] = 'DATE(t.fecha) >= ?';
    $params[] = $filtroFechaInicio;
}
if ($filtroFechaFin) {
    $where[] = 'DATE(t.fecha) <= ?';
    $params[] = $filtroFechaFin;
}

$whereSql = implode(' AND ', $where);

// Total de registros
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM transacciones t
    JOIN billeteras b ON t.billetera_id = b.id
    WHERE $whereSql
");
$stmt->execute($params);
$totalRegistros = (int)$stmt->fetchColumn();
$totalPaginas = max(1, ceil($totalRegistros / $porPagina));

// Transacciones de la página actual
$paramsPaginados = array_merge($params, [$porPagina, $offset]);
$stmt = $pdo->prepare("
    SELECT t.tipo, t.monto, t.saldo_resultante, t.descripcion, t.referencia, t.fecha
    FROM transacciones t
    JOIN billeteras b ON t.billetera_id = b.id
    WHERE $whereSql
    ORDER BY t.fecha DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($paramsPaginados);
$transacciones = $stmt->fetchAll();

// Tipos para filtro
$tiposDisponibles = ['RECARGA', 'BUY_IN', 'PREMIO', 'RETIRO', 'REEMBOLSO', 'BONO', 'AJUSTE'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historial Completo – BANG V2.2</title>
<link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<!-- Navbar -->
<nav class="app-nav">
  <a href="index.php" class="nav-brand">
    <div class="logo-sm" style="font-size:1.5rem">BANG</div>
    <span class="tagline" style="margin:0;font-size:0.65rem;color:var(--yellow)">V2.2 PAYPLAY</span>
  </a>
  <div class="nav-actions">
    <a href="wallet.php" class="chip-saldo" title="Ver mi billetera">
      <span><i class="fa-solid fa-coins" aria-hidden="true"></i> $ <?= number_format($billetera['saldo_disponible'], 0, ',', '.') ?></span>
    </a>
    <a href="wallet.php" class="nav-link-btn" style="border-color:var(--accent);color:var(--accent)" title="Volver a Billetera">
      <span><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i></span><span class="nav-txt">Billetera</span>
    </a>
    <a href="index.php" class="nav-link-btn" style="border-color:var(--accent);color:var(--accent)" title="Ir al Lobby">
      <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span><span class="nav-txt">Lobby</span>
    </a>
  </div>
</nav>

<div class="page-top" style="max-width:960px">

  <div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <h1 class="page-title">Historial de Movimientos</h1>
      <p style="font-size:0.85rem;color:var(--muted)">Libro mayor completo de tu billetera virtual</p>
    </div>
    <a href="wallet.php" class="btn btn-green" style="font-size:0.85rem;padding:0.55rem 1.1rem">
      ← Volver a Billetera
    </a>
  </div>

  <!-- Filtros -->
  <div class="card" style="margin-bottom:1.5rem">
    <form method="GET" class="filters-form">
      <div class="filter-field">
        <label class="form-label">Tipo de movimiento</label>
        <select name="tipo" style="width:100%;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.6rem;font-size:0.88rem">
          <option value="">Todos los tipos</option>
          <?php foreach ($tiposDisponibles as $tipo): ?>
            <option value="<?= $tipo ?>" <?= $filtroTipo === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label class="form-label">Desde</label>
        <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($filtroFechaInicio) ?>" style="width:100%;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.6rem;font-size:0.88rem">
      </div>
      <div class="filter-field">
        <label class="form-label">Hasta</label>
        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($filtroFechaFin) ?>" style="width:100%;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.6rem;font-size:0.88rem">
      </div>
      <button type="submit" class="btn btn-blue" style="height:fit-content;padding:0.6rem 1.2rem;font-size:0.85rem;white-space:nowrap">Filtrar</button>
      <?php if ($filtroTipo || $filtroFechaInicio || $filtroFechaFin): ?>
        <a href="historial.php" class="btn btn-ghost" style="height:fit-content;padding:0.6rem 1.2rem;font-size:0.85rem;white-space:nowrap">Limpiar</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Resumen -->
  <div style="display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
    <div class="balance-card" style="flex:1;min-width:200px">
      <div class="section-label" style="color:var(--yellow)">Total Movimientos</div>
      <div class="balance-num"><?= number_format($totalRegistros) ?></div>
    </div>
    <div class="balance-card" style="flex:1;min-width:200px">
      <div class="section-label" style="color:var(--green)">Saldo Actual</div>
      <div class="balance-num"><i class="fa-solid fa-coins" aria-hidden="true"></i> <?= number_format($billetera['saldo_disponible'], 0, ',', '.') ?> fichas</div>
    </div>
  </div>

  <!-- Tabla de transacciones -->
  <div class="card">
    <div class="table-scroll">
      <table class="tx-table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Monto (Fichas)</th>
            <th>Saldo Resultante</th>
            <th>Referencia</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transacciones)): ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:2rem;color:var(--muted)">
                No hay movimientos registrados
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($transacciones as $tx): ?>
              <?php
                $tipoClass = strtolower($tx['tipo']);
                $esPositivo = $tx['monto'] >= 0;
              ?>
              <tr>
                <td><span class="tx-badge tx-<?= htmlspecialchars($tipoClass) ?>"><?= htmlspecialchars($tx['tipo']) ?></span></td>
                <td style="font-size:0.82rem"><?= htmlspecialchars($tx['descripcion']) ?></td>
                <td style="font-weight:600;color:<?= $esPositivo ? 'var(--green)' : '#ff6b6b' ?>">
                  <?= $esPositivo ? '+' : '' ?><?= number_format($tx['monto'], 0, ',', '.') ?> fichas
                </td>
                <td style="font-size:0.82rem"><?= number_format($tx['saldo_resultante'], 0, ',', '.') ?> fichas</td>
                <td style="font-family:monospace;font-size:0.78rem;color:var(--muted)"><?= htmlspecialchars($tx['referencia']) ?></td>
                <td style="font-size:0.75rem;color:var(--muted)"><?= htmlspecialchars($tx['fecha']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPaginas > 1): ?>
      <div class="pagination">
        <?php if ($pagina > 1): ?>
          <a href="?pagina=<?= $pagina - 1 ?>&tipo=<?= urlencode($filtroTipo) ?>&fecha_inicio=<?= urlencode($filtroFechaInicio) ?>&fecha_fin=<?= urlencode($filtroFechaFin) ?>" 
             class="btn btn-ghost" style="font-size:0.85rem;padding:0.5rem 1rem">← Anterior</a>
        <?php endif; ?>
        
        <span style="padding:0 1rem;color:var(--muted);font-size:0.85rem">
          Página <?= $pagina ?> de <?= $totalPaginas ?>
        </span>
        
        <?php if ($pagina < $totalPaginas): ?>
          <a href="?pagina=<?= $pagina + 1 ?>&tipo=<?= urlencode($filtroTipo) ?>&fecha_inicio=<?= urlencode($filtroFechaInicio) ?>&fecha_fin=<?= urlencode($filtroFechaFin) ?>" 
             class="btn btn-blue" style="font-size:0.85rem;padding:0.5rem 1rem">Siguiente →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
  window.__ES_ADMIN__ = <?= $esAdmin ? 'true' : 'false' ?>;
</script>
<script src="js/panel_v22.js"></script>
</body>
</html>