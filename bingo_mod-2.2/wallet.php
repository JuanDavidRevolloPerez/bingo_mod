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
</head>
<body>

<!-- Navbar -->
<nav class="app-nav">
  <a href="index.php" class="nav-brand">
    <div class="logo-sm" style="font-size:1.5rem">BANG</div>
    <span class="tagline" style="margin:0;font-size:0.65rem;color:var(--yellow)">V2.2 PAYPLAY</span>
  </a>
  <div class="nav-actions">
    <a href="perfil.php" class="nav-link-btn">
      <span>👤 <?= htmlspecialchars($usuario['nickname']) ?></span>
      <span class="tx-badge badge-kyc-<?= intval($kyc['nivel']) ?>">KYC <?= intval($kyc['nivel']) >= 2 ? '✓' : '' ?></span>
    </a>
    <a href="index.php" class="nav-link-btn" style="border-color:var(--accent);color:var(--accent)">
      🎮 Volver al Lobby
    </a>
  </div>
</nav>

<div class="page-top" style="max-width:850px">

  <div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <h1 style="font-family:'Black Han Sans',sans-serif;font-size:2rem;letter-spacing:0.02em">Mi Billetera Virtual</h1>
      <p style="font-size:0.85rem;color:var(--muted)">Gestiona tus fichas, recargas en línea y solicitudes de retiro.</p>
    </div>
    <div style="display:flex;gap:0.5rem">
      <button class="btn btn-green" style="padding:0.55rem 1.1rem;font-size:0.85rem" onclick="scrollSeccion('sec-recarga')">💳 Recargar</button>
      <button class="btn btn-blue" style="padding:0.55rem 1.1rem;font-size:0.85rem" onclick="scrollSeccion('sec-retiro')">🏦 Retirar</button>
    </div>
  </div>

  <!-- Tarjetas de Balance -->
  <div class="balance-grid">
    <div class="balance-card highlight">
      <div class="section-label" style="color:var(--yellow)">Saldo Disponible</div>
      <div class="balance-num" id="disp-saldo-disp">
        🪙 <?= $billetera['saldo_disponible'] ?> fichas
      </div>
      <div style="font-size:0.8rem;color:var(--muted)">
        Equivalente a $<?= number_format($billetera['saldo_disponible'] * VALOR_FICHA, 0, ',', '.') ?> COP
      </div>
    </div>

    <div class="balance-card escrow">
      <div class="section-label" style="color:#4dc2ff">Saldo en Retención (Escrow)</div>
      <div class="balance-num" id="disp-saldo-ret" style="color:#80d4ff">
        🪙 <?= $billetera['saldo_retenido'] ?> fichas
      </div>
      <div style="font-size:0.8rem;color:var(--muted)">
        Fondos temporales en salas de espera activas
      </div>
    </div>
  </div>

  <!-- Módulos Recarga y Retiro -->
  <div class="game-grid" style="grid-template-columns:1fr 1fr;margin-bottom:1.5rem">
    
    <!-- Módulo Recarga -->
    <div class="card" id="sec-recarga">
      <div class="section-label" style="color:var(--green)">
        <span>💳</span> Recargar Fichas (Wompi)
      </div>
      <p style="font-size:0.82rem;color:var(--muted);margin-bottom:1rem">
        Acreditación automática e inmediata vía Wompi. Sin tarjeta de crédito.
      </p>

      <label class="form-label">Monto a recargar (COP)</label>
      <input type="number" id="rec-monto" placeholder="Ej. 50000" min="10000" step="10000" value="50000"
             style="font-size:1.1rem;font-weight:600">
      <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
        1 Ficha = $<?= number_format(VALOR_FICHA, 0, ',', '.') ?> COP
      </p>

      <div style="display:flex;gap:0.4rem;margin-bottom:1rem;flex-wrap:wrap">
        <button type="button" class="buyin-chip" onclick="setMontoRecarga(10000)">1 ficha</button>
        <button type="button" class="buyin-chip" onclick="setMontoRecarga(50000)">5 fichas</button>
        <button type="button" class="buyin-chip" onclick="setMontoRecarga(100000)">10 fichas</button>
        <button type="button" class="buyin-chip" onclick="setMontoRecarga(200000)">20 fichas</button>
      </div>

      <button class="btn btn-green" id="btn-recargar" onclick="irAWompi()">
        Ir al Checkout de Pago →
      </button>
      <div class="err-msg" id="msg-recarga"></div>
    </div>

    <!-- Módulo Retiros -->
    <div class="card" id="sec-retiro">
      <div class="section-label" style="color:#ff6b6b">
        <span>🏦</span> Solicitar Retiro a Cuenta
      </div>

      <?php if (intval($kyc['nivel']) < 2): ?>
        <div style="background:rgba(249,52,22,0.1);border:1px solid rgba(249,52,22,0.3);border-radius:12px;padding:1rem;margin-bottom:1rem;text-align:center">
          <div style="font-size:1.8rem;margin-bottom:0.3rem">🔒</div>
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
        <input type="number" id="ret-monto" placeholder="Ej. 5" min="1" step="1" max="<?= intval($billetera['saldo_disponible']) ?>">
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
        <span>📜</span> Libro Mayor de Movimientos (Inmutable)
      </div>
      <span style="font-size:0.75rem;color:var(--muted)">Sistema Append-Only S-06</span>
    </div>

    <div style="overflow-x:auto">
      <table class="tx-table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Monto</th>
            <th>Saldo Resultante</th>
            <th>Referencia</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody id="tx-tbody">
          <?php foreach ($billetera['transacciones'] as $tx): ?>
            <?php
              $tipoClass = strtolower($tx['tipo']);
              $esPositivo = $tx['monto'] >= 0;
            ?>
            <tr>
              <td><span class="tx-badge tx-<?= htmlspecialchars($tipoClass) ?>"><?= htmlspecialchars($tx['tipo']) ?></span></td>
              <td style="font-size:0.82rem"><?= htmlspecialchars($tx['descripcion']) ?></td>
              <td style="font-weight:600;color:<?= $esPositivo ? 'var(--green)' : '#ff6b6b' ?>">
                <?= $esPositivo ? '+' : '' ?>$ <?= number_format($tx['monto'], 0, ',', '.') ?>
              </td>
              <td style="font-size:0.82rem">$ <?= number_format($tx['saldo_resultante'], 0, ',', '.') ?></td>
              <td style="font-family:monospace;font-size:0.78rem;color:var(--muted)"><?= htmlspecialchars($tx['referencia']) ?></td>
              <td style="font-size:0.75rem;color:var(--muted)"><?= htmlspecialchars($tx['fecha']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function setMontoRecarga(v) {
  document.getElementById('rec-monto').value = v;
}

function scrollSeccion(id) {
  const el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: 'smooth' });
}

function irAWompi() {
  const monto = parseFloat(document.getElementById('rec-monto').value) || 50000;
  if (monto < 10000) {
    const msg = document.getElementById('msg-recarga');
    msg.textContent = 'El monto mínimo de recarga es de $10.000 COP (1 ficha)';
    msg.style.display = 'block';
    msg.style.color = '#ff6b6b';
    return;
  }
  window.location.href = 'php/wompi_simulado.php?monto=' + monto;
}

async function procesarRetiro() {
  const monto = parseFloat(document.getElementById('ret-monto').value) || 0;
  const banco = document.getElementById('ret-banco').value;
  const tipo  = document.getElementById('ret-tipo').value;
  const num   = document.getElementById('ret-num').value.trim();
  const msg   = document.getElementById('msg-retiro');
  msg.style.display = 'none';

  if (monto <= 0 || !num) {
    msg.textContent = 'Completa el monto y el número de cuenta.';
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
      body: JSON.stringify({ accion: 'solicitar_retiro', monto, banco, tipo_cuenta: tipo, num_cuenta: num })
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
</script>
<script>
  window.__ES_ADMIN__ = <?= $esAdmin ? 'true' : 'false' ?>;
</script>
<script src="js/panel_v22.js"></script>
</body>
</html>
