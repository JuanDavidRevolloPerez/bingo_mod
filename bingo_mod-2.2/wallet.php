<?php
session_start();
require_once 'php/funciones.php';
inicializarSesionConBD();

// Billetera solo accesible con sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$billetera = $_SESSION['billetera'];
$usuario   = $_SESSION['usuario'];
$kyc       = $_SESSION['kyc'];

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Billetera – BANG V2.2</title>
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
    <a href="perfil.php" class="nav-link-btn" title="Mi perfil y verificación KYC">
      <span><i class="fa-solid fa-user" aria-hidden="true"></i></span><span class="nav-txt"><?= htmlspecialchars($usuario['nickname']) ?></span>
      <span class="tx-badge badge-kyc-<?= intval($kyc['nivel']) ?>">KYC <?= intval($kyc['nivel']) >= 2 ? '<i class="fa-solid fa-check" aria-hidden="true"></i>' : '' ?></span>
    </a>
    <a href="index.php" class="nav-link-btn" style="border-color:var(--accent);color:var(--accent)" title="Volver al Lobby">
      <span><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span><span class="nav-txt">Volver al Lobby</span>
    </a>
  </div>
</nav>

<div class="page-top" style="max-width:850px">

  <div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <h1 class="page-title">Mi Billetera Virtual</h1>
      <p style="font-size:0.85rem;color:var(--muted)">Gestiona tus fichas, recargas en línea y solicitudes de retiro.</p>
    </div>
    <div style="display:flex;gap:0.5rem">
      <button class="btn btn-green" style="padding:0.55rem 1.1rem;font-size:0.85rem" onclick="scrollSeccion('sec-recarga')"><i class="fa-solid fa-credit-card" aria-hidden="true"></i> Recargar</button>
      <button class="btn btn-blue" style="padding:0.55rem 1.1rem;font-size:0.85rem" onclick="scrollSeccion('sec-retiro')"><i class="fa-solid fa-building-columns" aria-hidden="true"></i> Retirar</button>
    </div>
  </div>

  <!-- Tarjeta de Balance -->
  <div class="balance-grid">
    <div class="balance-card highlight" style="grid-column:1 / -1">
      <div class="section-label" style="color:var(--yellow)">Saldo Disponible</div>
      <div class="balance-num" id="disp-saldo-disp" style="font-size:2.2rem">
        <i class="fa-solid fa-coins" aria-hidden="true"></i> <?= $billetera['saldo_disponible'] ?> fichas
      </div>
      <div style="font-size:0.8rem;color:var(--muted)">
        Equivalente a $<?= number_format($billetera['saldo_disponible'] * VALOR_FICHA, 0, ',', '.') ?> COP
        · Saldo retenido: <?= $billetera['saldo_retenido'] ?> fichas
      </div>
    </div>
  </div>

  <!-- Módulos Recarga y Retiro -->
  <div class="duo-grid">
    
    <!-- Módulo Recarga -->
    <div class="card" id="sec-recarga">
      <div class="section-label" style="color:var(--green)">
        <span><i class="fa-solid fa-credit-card" aria-hidden="true"></i></span> Recargar Fichas (Wompi)
      </div>
      <p style="font-size:0.82rem;color:var(--muted);margin-bottom:1rem">
        Acreditación automática e inmediata vía Wompi. Sin tarjeta de crédito.
      </p>

      <label class="form-label">Cantidad de fichas a recargar</label>
      <div class="fichas-box">
        <input type="number" id="rec-fichas" class="fichas-field" placeholder="Ej. 5" min="1" step="1" value="5"
               oninput="actualizarEquivalenteRecarga()">
        <span class="fichas-suffix">fichas</span>
      </div>
      <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
        Equivalente: <strong id="rec-equivalente" style="color:var(--yellow)">$ 50.000 COP</strong> · 1 Ficha = $<?= number_format(VALOR_FICHA, 0, ',', '.') ?> COP
      </p>

      <div style="display:flex;gap:0.4rem;margin-bottom:1rem;flex-wrap:wrap">
        <button type="button" class="buyin-chip" onclick="setFichasRecarga(1)">1 ficha</button>
        <button type="button" class="buyin-chip" onclick="setFichasRecarga(5)">5 fichas</button>
        <button type="button" class="buyin-chip" onclick="setFichasRecarga(10)">10 fichas</button>
        <button type="button" class="buyin-chip" onclick="setFichasRecarga(20)">20 fichas</button>
      </div>

      <button class="btn btn-green" id="btn-recargar" onclick="irAWompi()">
        Ir al Checkout de Pago →
      </button>
      <div class="err-msg" id="msg-recarga"></div>
    </div>

    <!-- Módulo Retiros -->
    <div class="card" id="sec-retiro">
      <div class="section-label" style="color:#ff6b6b">
        <span><i class="fa-solid fa-building-columns" aria-hidden="true"></i></span> Solicitar Retiro a Cuenta
      </div>

      <?php if (intval($kyc['nivel']) < 2): ?>
        <div style="background:rgba(249,52,22,0.1);border:1px solid rgba(249,52,22,0.3);border-radius:12px;padding:1rem;margin-bottom:1rem;text-align:center">
          <div style="font-size:1.8rem;margin-bottom:0.3rem"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>
          <div style="font-size:0.92rem;font-weight:600;color:#ff8585;margin-bottom:0.3rem">Retiros Bloqueados (Requiere Verificación)</div>
          <p style="font-size:0.8rem;color:var(--muted);line-height:1.4;margin-bottom:0.75rem">
            Por regulación financiera y prevención de fraude, debes subir tu documento de identidad.
          </p>
          <a href="perfil.php" class="btn btn-blue" style="font-size:0.82rem;padding:0.45rem 1rem">
            Verificar Identidad en Perfil →
          </a>
        </div>
      <?php else: ?>
        <p style="font-size:0.82rem;color:var(--muted);margin-bottom:1rem">
          Los retiros se abonan directamente a tu cuenta bancaria en Colombia.
        </p>

        <label class="form-label">Cantidad de fichas a retirar</label>
        <div class="fichas-box">
          <input type="number" id="ret-fichas" class="fichas-field" placeholder="Ej. 5" min="1" step="1" max="<?= intval($billetera['saldo_disponible']) ?>">
          <span class="fichas-suffix">fichas</span>
        </div>
        <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
          Disponible para retirar: <?= intval($billetera['saldo_disponible']) ?> fichas · 1 Ficha = $<?= number_format(VALOR_FICHA, 0, ',', '.') ?> COP
        </p>

        <label class="form-label">Banco de Destino</label>
        <select id="ret-banco" style="width:100%;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.6rem 0.8rem;font-size:0.88rem;margin-bottom:0.75rem">
          <option value="Bancolombia">Bancolombia</option>
          <option value="Nequi">Nequi</option>
          <option value="Daviplata">Daviplata</option>
          <option value="Davivienda">Banco Davivienda</option>
          <option value="BBVA">BBVA Colombia</option>
          <option value="Banco de Bogota">Banco de Bogotá</option>
        </select>

        <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:0.5rem;margin-bottom:0.75rem">
          <div>
            <label class="form-label">Tipo</label>
            <select id="ret-tipo" style="width:100%;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.6rem;font-size:0.85rem">
              <option value="Ahorros">Ahorros</option>
              <option value="Corriente">Corriente</option>
            </select>
          </div>
          <div>
            <label class="form-label">Número de Cuenta</label>
            <input type="text" id="ret-num" placeholder="Ej. 1234567890">
          </div>
        </div>

        <button class="btn btn-blue" id="btn-retirar" onclick="procesarRetiro()">
          Enviar Solicitud de Retiro →
        </button>
        <div class="err-msg" id="msg-retiro"></div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Historial de Transacciones (Libro Mayor) -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem">
      <div class="section-label" style="margin:0">
        <span><i class="fa-solid fa-scroll" aria-hidden="true"></i></span> Libro Mayor de Movimientos (Inmutable)
      </div>
      <span style="font-size:0.75rem;color:var(--muted)">Sistema Append-Only S-06</span>
    </div>

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
        <tbody id="tx-tbody">
          <?php 
          $ultimas5 = array_slice($billetera['transacciones'], 0, 5);
          $totalMovimientos = count($billetera['transacciones']);
          ?>
          <?php foreach ($ultimas5 as $tx): ?>
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
          <?php if ($totalMovimientos > 5): ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:1rem">
                <a href="historial.php" class="btn btn-ghost" style="width:auto;margin:0;font-size:0.85rem">
                  Ver historial completo (<?= $totalMovimientos ?> movimientos) →
                </a>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function setFichasRecarga(v) {
  document.getElementById('rec-fichas').value = v;
  actualizarEquivalenteRecarga();
}

function actualizarEquivalenteRecarga() {
  const f = parseInt(document.getElementById('rec-fichas').value) || 0;
  const equiv = document.getElementById('rec-equivalente');
  if (equiv) equiv.textContent = '$ ' + (f * <?= VALOR_FICHA ?>).toLocaleString('es-CO') + ' COP';
}

function scrollSeccion(id) {
  const el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: 'smooth' });
}

function irAWompi() {
  const fichas = parseInt(document.getElementById('rec-fichas').value) || 0;
  const msg = document.getElementById('msg-recarga');
  if (fichas < 1) {
    msg.textContent = 'La cantidad mínima de recarga es 1 ficha ($<?= number_format(VALOR_FICHA, 0, ',', '.') ?> COP)';
    msg.style.display = 'block';
    msg.style.color = '#ff6b6b';
    return;
  }
  window.location.href = 'php/wompi_simulado.php?fichas=' + fichas;
}

async function procesarRetiro() {
  const fichas = parseInt(document.getElementById('ret-fichas').value) || 0;
  const banco = document.getElementById('ret-banco').value;
  const tipo  = document.getElementById('ret-tipo').value;
  const num   = document.getElementById('ret-num').value.trim();
  const msg   = document.getElementById('msg-retiro');
  msg.style.display = 'none';

  if (fichas < 1 || !num) {
    msg.textContent = 'Completa la cantidad de fichas y el número de cuenta.';
    msg.style.display = 'block';
    msg.style.color = '#ff6b6b';
    return;
  }

  const btn = document.getElementById('btn-retirar');
  btn.disabled = true;
  btn.textContent = 'Enviando solicitud...';

  try {
    const r = await fetch('php/billetera_acciones.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'solicitar_retiro', fichas, banco, tipo_cuenta: tipo, num_cuenta: num })
    });
    const d = await r.json();
    if (d.ok) {
      msg.textContent = `✓ ${d.mensaje}`;
      msg.style.display = 'block';
      msg.style.color = 'var(--green)';
      setTimeout(() => location.reload(), 1500);
    } else {
      msg.textContent = d.error;
      msg.style.display = 'block';
      msg.style.color = '#ff6b6b';
      btn.disabled = false;
      btn.textContent = 'Enviar Solicitud de Retiro →';
    }
  } catch(e) {
    btn.disabled = false;
    btn.textContent = 'Enviar Solicitud de Retiro →';
  }
}

// Inicializar el equivalente COP al cargar la página
actualizarEquivalenteRecarga();
</script>
<script>
  window.__ES_ADMIN__ = <?= $esAdmin ? 'true' : 'false' ?>;
</script>
<script src="js/panel_v22.js"></script>
</body>
</html>
