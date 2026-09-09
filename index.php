<?php
session_start();
require_once 'php/funciones.php';
inicializarSesionUsuario();

$billetera = $_SESSION['billetera'];
$usuario   = $_SESSION['usuario'];
$kyc       = $_SESSION['kyc'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BINGO ONLINE V2.1 – Multijugador & Payplay</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Navbar Superior V2.1 -->
<nav class="app-nav">
  <div class="nav-brand">
    <div class="logo-sm" style="font-size:1.45rem">BINGO</div>
    <span class="tagline" style="margin:0;font-size:0.65rem;color:var(--yellow)">V2.1 PAYPLAY</span>
  </div>
  <div class="nav-actions">
    <a href="wallet.php" class="chip-saldo" title="Ver mi billetera y recargas">
      <span>🪙 $ <?= number_format($billetera['saldo_disponible'], 0, ',', '.') ?></span>
    </a>
    <a href="perfil.php" class="nav-link-btn" title="Ver perfil y verificación KYC">
      <span>👤 <?= htmlspecialchars($usuario['nickname'] ?? 'Jugador') ?></span>
      <span class="tx-badge badge-kyc-<?= intval($kyc['nivel'] ?? 1) ?>" style="padding:0.1rem 0.4rem;font-size:0.68rem">
        KYC <?= intval($kyc['nivel'] ?? 1) ?>
      </span>
    </a>
  </div>
</nav>

<div class="page-center" style="min-height:calc(100vh - 70px);padding-top:1rem">
  <div style="width:100%;max-width:480px">

    <div style="text-align:center;margin-bottom:1.5rem">
      <div class="logo">BINGO</div>
      <p class="tagline">Multijugador Transaccional en Tiempo Real</p>
    </div>

    <!-- Toggle Selector de Modo (Gratuito vs Payplay) -->
    <div class="mode-toggle-group">
      <button type="button" class="mode-btn active" id="btn-modo-free" onclick="cambiarModoPlataforma('free')">
        <span>🎮</span> Modo Gratuito
      </button>
      <button type="button" class="mode-btn" id="btn-modo-payplay" onclick="cambiarModoPlataforma('payplay')">
        <span>💰</span> Modo Payplay (Fichas)
      </button>
    </div>

    <div class="card">
      <div class="tabs">
        <button class="tab active" onclick="tab('crear')">Crear sala</button>
        <button class="tab"        onclick="tab('unirse')">Unirse a sala</button>
      </div>

      <!-- Panel Crear -->
      <div id="p-crear">
        <label class="form-label">Tu nombre / Alias</label>
        <input type="text" id="c-nombre" placeholder="ej. María" maxlength="20" value="<?= htmlspecialchars($usuario['nickname'] ?? '') ?>">

        <label class="form-label">Nombre de la sala</label>
        <input type="text" id="c-sala"   placeholder="ej. Gran Torneo Bingo" maxlength="30">

        <!-- Opciones Exclusivas Modo Payplay -->
        <div id="box-payplay-opciones" style="display:none;margin-top:1rem;background:rgba(204,245,0,0.04);border:1px solid rgba(204,245,0,0.2);border-radius:12px;padding:0.85rem">
          <div class="buyin-label">
            <span style="font-weight:600;color:var(--yellow)">Selecciona el Buy-in (Costo de entrada)</span>
            <span id="label-saldo-check" style="font-size:0.75rem;color:var(--muted)">Saldo: $<?= number_format($billetera['saldo_disponible'], 0, ',', '.') ?></span>
          </div>
          <div class="buyin-grid">
            <div class="buyin-chip selected" onclick="selBuyIn(this, 1000)">$1.000</div>
            <div class="buyin-chip" onclick="selBuyIn(this, 5000)">$5.000</div>
            <div class="buyin-chip" onclick="selBuyIn(this, 10000)">$10.000</div>
            <div class="buyin-chip" onclick="selBuyIn(this, 50000)">$50.000</div>
          </div>
          <div style="font-size:0.75rem;color:var(--muted);display:flex;justify-content:space-between">
            <span>🛡️ Retención Escrow protegida</span>
            <span style="color:var(--yellow)">Rake: 10% para la plataforma</span>
          </div>
        </div>

        <button class="btn btn-blue" id="btn-crear-submit" style="margin-top:1rem" onclick="crearSala()">
          Crear sala →
        </button>
        <div class="err-msg" id="err-crear"></div>
      </div>

      <!-- Panel Unirse -->
      <div id="p-unirse" style="display:none">
        <label class="form-label">Tu nombre / Alias</label>
        <input type="text" id="u-nombre" placeholder="ej. Carlos" maxlength="20" value="<?= htmlspecialchars($usuario['nickname'] ?? '') ?>">

        <label class="form-label">Código de sala</label>
        <input type="text" id="u-codigo" placeholder="A7X3" maxlength="4"
               style="text-transform:uppercase;letter-spacing:0.22em;font-size:1.4rem;text-align:center">
        
        <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem;text-align:center">
          Si la sala es Payplay, el Buy-in se retendrá automáticamente de tu billetera al ingresar.
        </p>

        <button class="btn btn-green" onclick="unirseSala()">Unirse a la sala →</button>
        <div class="err-msg" id="err-unirse"></div>
      </div>

      <div class="divider"></div>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
        <p class="hint" style="margin:0">Comparte el código de 4 letras para jugar con amigos</p>
        <a href="wallet.php" style="font-size:0.78rem;color:var(--yellow);text-decoration:none">Recargar fichas ↗</a>
      </div>
    </div>
  </div>
</div>

<script>
let modoActual = 'free';
let buyInSeleccionado = 1000;
const saldoDisponible = <?= floatval($billetera['saldo_disponible']) ?>;

function cambiarModoPlataforma(m) {
  modoActual = m;
  const btnFree = document.getElementById('btn-modo-free');
  const btnPay  = document.getElementById('btn-modo-payplay');
  const boxPay  = document.getElementById('box-payplay-opciones');
  const btnSubmit = document.getElementById('btn-crear-submit');

  if (m === 'payplay') {
    btnPay.classList.add('active', 'payplay-active');
    btnFree.classList.remove('active');
    boxPay.style.display = 'block';
    btnSubmit.style.background = 'linear-gradient(135deg, var(--yellow), #9ec200)';
    btnSubmit.style.color = '#11280d';
    btnSubmit.textContent = `Crear sala Payplay ($${buyInSeleccionado.toLocaleString()}) →`;
  } else {
    btnFree.classList.add('active');
    btnPay.classList.remove('active', 'payplay-active');
    boxPay.style.display = 'none';
    btnSubmit.style.background = '';
    btnSubmit.style.color = '';
    btnSubmit.textContent = 'Crear sala Gratuita →';
  }
}

function selBuyIn(el, v) {
  document.querySelectorAll('.buyin-chip').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  buyInSeleccionado = v;
  const btnSubmit = document.getElementById('btn-crear-submit');
  btnSubmit.textContent = `Crear sala Payplay ($${buyInSeleccionado.toLocaleString()}) →`;
}

function tab(t) {
  document.querySelectorAll('.tab').forEach((el,i) => el.classList.toggle('active', ['crear','unirse'][i]===t));
  document.getElementById('p-crear').style.display  = t==='crear'  ? '' : 'none';
  document.getElementById('p-unirse').style.display = t==='unirse' ? '' : 'none';
}

async function crearSala() {
  const nombre = document.getElementById('c-nombre').value.trim();
  const sala   = document.getElementById('c-sala').value.trim();
  const err    = document.getElementById('err-crear');
  err.style.display = 'none';

  if (!nombre || !sala) { mostrarError(err,'Completa todos los campos'); return; }

  if (modoActual === 'payplay' && saldoDisponible < buyInSeleccionado) {
    mostrarError(err, `Saldo insuficiente ($${saldoDisponible.toLocaleString()}). Recarga tu billetera para este Buy-in.`);
    return;
  }

  const btn = document.getElementById('btn-crear-submit');
  btn.disabled = true;
  btn.textContent = 'Creando sala...';

  try {
    const r = await fetch('php/crear_sala.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        nombre,
        nombre_sala: sala,
        tipo_sala: modoActual,
        buy_in: modoActual === 'payplay' ? buyInSeleccionado : 0
      })
    });
    const d = await r.json();
    if (d.ok) {
      location.href = 'espera.php';
    } else {
      mostrarError(err, d.error);
      btn.disabled = false;
      btn.textContent = modoActual === 'payplay' ? `Crear sala Payplay ($${buyInSeleccionado.toLocaleString()}) →` : 'Crear sala →';
    }
  } catch(e) {
    btn.disabled = false;
  }
}

async function unirseSala() {
  const nombre = document.getElementById('u-nombre').value.trim();
  const codigo = document.getElementById('u-codigo').value.trim().toUpperCase();
  const err    = document.getElementById('err-unirse');
  err.style.display = 'none';

  if (!nombre || !codigo) { mostrarError(err,'Completa todos los campos'); return; }

  const r = await fetch('php/unirse_sala.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ nombre, codigo })
  });
  const d = await r.json();
  if (d.ok) {
    location.href = 'espera.php';
  } else {
    mostrarError(err, d.error);
  }
}

function mostrarError(el, msg) { el.textContent = msg; el.style.display = 'block'; }

document.getElementById('u-codigo').addEventListener('input', function(){ this.value = this.value.toUpperCase(); });
</script>
<script src="js/panel_v22.js"></script>
</body>
</html>
