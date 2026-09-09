<?php
session_start();
if (!isset($_SESSION['jugador_id']) || !isset($_SESSION['codigo_sala'])) {
    header('Location: index.php'); exit;
}
$miId   = $_SESSION['jugador_id'];
$codigo = $_SESSION['codigo_sala'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sala de espera – BINGO V2.1</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-center">
  <div style="width:100%;max-width:520px">

    <div style="margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between">
      <div>
        <div class="logo-sm">BINGO</div>
        <p class="tagline">Sala de espera</p>
      </div>
      <a href="wallet.php" class="nav-link-btn" style="font-size:0.75rem">
        🪙 Mi Billetera
      </a>
    </div>

    <!-- Banner Pozo en Vivo para Modo Payplay -->
    <div class="prize-pool-banner" id="banner-pozo" style="display:none">
      <div>
        <div class="prize-title">💰 Pozo Acumulado (Prize Pool)</div>
        <div class="prize-amount" id="pozo-acumulado">$ 0</div>
        <div class="prize-meta" id="pozo-meta">Buy-in: $0 | Rake: 10%</div>
      </div>
      <div style="text-align:right">
        <span class="badge badge-yellow" style="font-size:0.75rem">Modo Payplay</span>
        <div style="font-size:0.75rem;color:var(--green);margin-top:0.35rem" id="pozo-neto-label">
          Premio Neto: $0
        </div>
      </div>
    </div>

    <div class="card">
      <!-- Header sala -->
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem">
        <div>
          <div class="section-label">
            <span>Sala</span>
            <span id="badge-tipo-sala" class="badge badge-green" style="margin-left:0.4rem;display:none">Gratuita</span>
          </div>
          <div style="font-size:1.2rem;font-weight:500" id="nombre-sala">Cargando...</div>
        </div>
        <div style="text-align:right">
          <div class="section-label" style="justify-content:flex-end">Código</div>
          <div class="code-badge" onclick="copiarCodigo()" title="Clic para copiar">
            <?= htmlspecialchars($codigo) ?>
            <span id="tip-copiado" style="position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);
              font-size:0.68rem;white-space:nowrap;color:var(--green);opacity:0;transition:opacity 0.3s;
              font-family:'DM Sans',sans-serif;letter-spacing:normal">¡Copiado!</span>
          </div>
        </div>
      </div>

      <!-- Jugadores -->
      <div class="section-label">
        <span class="status-dot"></span>
        Jugadores (<span id="n-jugadores">0</span>)
      </div>
      <div id="lista-jugadores" style="display:flex;flex-direction:column;gap:0.45rem;min-height:52px">
        <div style="font-size:0.88rem;color:var(--muted);padding:0.5rem 0">Cargando...</div>
      </div>
    </div>

    <!-- Controles host -->
    <div id="ctrl-host" style="display:none;margin-top:1rem">
      <!-- Velocidad -->
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.6rem;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:0.65rem 1rem">
        <span style="font-size:0.82rem;color:var(--muted);white-space:nowrap">Velocidad de balotas:</span>
        <select id="sel-velocidad" style="flex:1;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.35rem 0.6rem;font-size:0.88rem;cursor:pointer">
          <option value="5"  style="background:#1a3a14;color:#fff">Rápido (5 s)</option>
          <option value="10" style="background:#1a3a14;color:#fff" selected>Normal (10 s)</option>
          <option value="15" style="background:#1a3a14;color:#fff">Lento (15 s)</option>
        </select>
      </div>
      <!-- Nomenclatura -->
      <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:0.65rem 1rem;margin-bottom:0.75rem">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.4rem">
          <span style="font-size:0.82rem;color:var(--muted);white-space:nowrap">Nomenclatura:</span>
          <select id="sel-modo" style="flex:1;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.35rem 0.6rem;font-size:0.88rem;cursor:pointer">
            <option value="normal"   style="background:#1a3a14;color:#fff">Normal (A00-A99 × 5 letras)</option>
            <option value="reducido" style="background:#1a3a14;color:#fff">Reducida (A00-19, B20-39…)</option>
          </select>
        </div>
      </div>
      <button class="btn btn-blue" id="btn-iniciar" onclick="iniciarPartida()" disabled>Iniciar partida</button>
      <p style="text-align:center;font-size:0.75rem;color:var(--muted);margin-top:0.4rem">
        Necesitas al menos 1 jugador
      </p>
    </div>

    <!-- Controles jugador -->
    <div id="ctrl-jugador" style="display:none;margin-top:1rem">
      <div class="card-sm" style="text-align:center">
        <p style="font-size:0.88rem;color:var(--muted)">Esperando que el host inicie<span class="dots"></span></p>
      </div>
    </div>

    <button class="btn btn-ghost" style="margin-top:0.75rem" onclick="salirSala()">Salir de la sala</button>
  </div>
</div>
<script>
const miId   = '<?= $miId ?>';
const codigo = '<?= $codigo ?>';
let redirigiendo = false;

async function poll() {
  if (redirigiendo) return;
  try {
    const r = await fetch(`php/verificar_estado.php?accion=estado&codigo=${codigo}`);
    const d = await r.json();
    if (!d.ok) { redirigiendo = true; location.href = 'index.php'; return; }
    const sala = d.sala;
    if (sala.estado === 'jugando') {
      redirigiendo = true;
      try { const a=document.getElementById('audio-inicio'); a.currentTime=0; await a.play(); } catch(e) {}
      setTimeout(() => location.href = 'juego.php', 1200);
      return;
    }

    document.getElementById('nombre-sala').textContent = sala.nombre;
    document.getElementById('n-jugadores').textContent = sala.jugadores.length;
    renderJugadores(sala.jugadores, sala.host_id, sala.tipo_sala);

    // Render Pozo Payplay
    const bannerPozo = document.getElementById('banner-pozo');
    const badgeTipo  = document.getElementById('badge-tipo-sala');
    if (sala.tipo_sala === 'payplay') {
      bannerPozo.style.display = 'flex';
      badgeTipo.style.display = 'none';
      const buyIn = Number(sala.buy_in) || 0;
      const totalPozo = (sala.jugadores.length * buyIn);
      const netoPozo  = Math.round(totalPozo * (1 - (sala.comision_pct || 0.10)));
      document.getElementById('pozo-acumulado').textContent = '$ ' + totalPozo.toLocaleString();
      document.getElementById('pozo-meta').textContent = `Buy-in: $${buyIn.toLocaleString()} | Rake: 10%`;
      document.getElementById('pozo-neto-label').textContent = `Premio Neto: $${netoPozo.toLocaleString()}`;
    } else {
      bannerPozo.style.display = 'none';
      badgeTipo.style.display = 'inline-block';
      badgeTipo.textContent = 'Gratuita';
    }

    const soyHost = sala.host_id === miId;
    document.getElementById('ctrl-host').style.display    = soyHost ? '' : 'none';
    document.getElementById('ctrl-jugador').style.display = soyHost ? 'none' : '';
    if (soyHost) {
      document.getElementById('btn-iniciar').disabled = sala.jugadores.length < 1;
    }
  } catch(e) {}
}

function renderJugadores(jugadores, hostId, tipoSala) {
  const el = document.getElementById('lista-jugadores');
  if (!jugadores.length) { el.innerHTML = '<div style="font-size:0.88rem;color:var(--muted);padding:0.5rem 0">Sin jugadores aún...</div>'; return; }
  el.innerHTML = jugadores.map(j => {
    const esHost = j.id === hostId, esYo = j.id === miId;
    const ini = j.nombre.slice(0,2).toUpperCase();
    return `<div class="player-row">
      <div class="avatar ${esHost?'av-host':'av-player'}">${esc(ini)}</div>
      <span style="flex:1;font-size:0.9rem">${esc(j.nombre)}</span>
      ${tipoSala === 'payplay' ? '<span class="tx-badge tx-buyin" style="font-size:0.68rem">Escrow OK</span>' : ''}
      ${esHost ? '<span class="badge badge-yellow">Host</span>' : ''}
      ${esYo   ? '<span class="badge badge-green">Tú</span>'   : ''}
    </div>`;
  }).join('');
}

async function iniciarPartida() {
  const btn = document.getElementById('btn-iniciar');
  const velocidad = parseInt(document.getElementById('sel-velocidad').value) || 10;
  const modo      = document.getElementById('sel-modo').value || 'normal';
  btn.disabled = true; btn.textContent = 'Iniciando...';
  const r = await fetch('php/iniciar_partida.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ codigo, velocidad, modo }) });
  const d = await r.json();
  if (d.ok) {
    redirigiendo = true;
    try { const a=document.getElementById('audio-inicio'); a.currentTime=0; await a.play(); } catch(e) {}
    setTimeout(() => location.href = 'juego.php', 1200);
  } else { btn.disabled = false; btn.textContent = 'Iniciar partida'; alert(d.error); }
}

async function salirSala() {
  await fetch('php/verificar_estado.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ accion:'salir', codigo }) });
  location.href = 'index.php';
}

function copiarCodigo() {
  navigator.clipboard.writeText(codigo).then(() => {
    const tip = document.getElementById('tip-copiado');
    tip.style.opacity = '1';
    setTimeout(() => tip.style.opacity = '0', 2000);
  });
}

function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

poll();
setInterval(poll, 2500);
</script>
<audio id="audio-inicio" src="https://assets.mixkit.co/active_storage/sfx/2704/2704-preview.mp3" preload="auto"></audio>
</body>
</html>
