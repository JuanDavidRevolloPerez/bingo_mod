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
<title>Jugando – BINGO</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-top">

  <!-- Header -->
  <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem">
    <div>
      <div class="logo-sm">BINGO</div>
      <div style="font-size:0.72rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted)">
        Sala: <?= htmlspecialchars($codigo) ?>
      </div>
    </div>
    <div id="hdr-info" style="font-size:0.82rem;color:var(--muted)"></div>
  </header>

  <div class="game-grid">
    <!-- Columna principal -->
    <div>
      <!-- Ficha actual -->
      <div class="card-sm" style="margin-bottom:1rem">
        <div class="section-label">Última ficha generada</div>
        <div style="display:flex;align-items:center;gap:1rem">
          <div class="ball-circle" id="bola-circulo" style="border-color:rgba(255,255,255,0.1)">
            <div class="ball-letter" id="bola-letra" style="color:var(--muted)">-</div>
            <div class="ball-num"    id="bola-num"   style="color:var(--muted)">--</div>
          </div>
          <div>
            <div style="font-size:1.55rem;font-weight:500" id="bola-display">Esperando fichas...</div>
            <div style="font-size:0.82rem;color:var(--muted);margin-top:0.2rem" id="bola-sub">La primera ficha aparecerá en breve</div>
          </div>
        </div>
        <div class="timer-bar"><div class="timer-fill" id="timer-fill" style="width:100%"></div></div>

        <div class="section-label" style="margin-top:0.85rem">
          Historial (<span id="n-historial">0</span>)
        </div>
        <div id="strip-historial" style="display:flex;flex-wrap:wrap;gap:5px"></div>
      </div>

      <!-- Cartón -->
      <div class="card-sm">
        <div class="section-label">Tu cartón</div>
        <div class="carton-grid" id="carton-grid"></div>
      </div>
    </div>


    <div>
      <div class="card-sm" style="margin-bottom:1rem">
        <div class="section-label">Jugadores</div>
        <div id="jugadores-mini" style="display:flex;flex-direction:column;gap:0.4rem"></div>
      </div>

      <div class="card-sm">
        <div class="section-label">¿Tienes bingo?</div>
        <p style="font-size:0.8rem;color:var(--muted);margin-bottom:0.75rem;line-height:1.5">
          Marca las casillas de tu cartón y presiona el botón cuando completes una línea, columna o diagonal.
        </p>
        <button class="btn-bingo" onclick="cantarBingo()">¡BINGO!</button>
        <div class="warn-msg" id="warn-msg"></div>
      </div>

      <!-- Control de velocidad (solo host) -->
      <div class="card-sm" id="ctrl-velocidad-host" style="display:none;margin-top:1rem">
        <div class="section-label">Velocidad de balotas</div>
        <div style="display:flex;align-items:center;gap:0.6rem;margin-top:0.4rem">
          <select id="sel-vel-juego" style="flex:1;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.35rem 0.6rem;font-size:0.85rem;cursor:pointer">
            <option value="5">Rápido (5 s)</option>
            <option value="10" selected>Normal (10 s)</option>
            <option value="15">Lento (15 s)</option>
          </select>
          <button onclick="cambiarVelocidad()" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:0.38rem 0.9rem;font-size:0.82rem;cursor:pointer;white-space:nowrap">Aplicar</button>
        </div>
        <div id="msg-velocidad" style="font-size:0.75rem;color:var(--green);margin-top:0.35rem;min-height:1em"></div>
      </div>

      <!-- Botón abandonar partida -->
      <button class="btn btn-ghost" id="btn-abandonar"
        onclick="confirmarAbandonar()"
        style="margin-top:0.75rem;border-color:var(--red);color:var(--red)">
        Abandonar partida
      </button>
    </div>
  </div>
</div>

<!-- Overlay cancelación — todos abandonaron -->
<div class="overlay" id="overlay-cancelado" style="display:none">
  <div class="winner-card">
    <div style="font-size:3.5rem;margin-bottom:0.75rem">🚪</div>
    <p style="font-size:0.85rem;color:var(--muted);margin-bottom:0.35rem">Partida cancelada</p>
    <div style="font-family:'Black Han Sans',sans-serif;font-size:1.6rem;color:var(--red);margin-bottom:0.5rem">
      Todos los jugadores se fueron
    </div>
    <p style="margin-top:0.5rem;color:var(--muted);font-size:0.82rem">La sala ha sido cerrada.</p>
    <a href="index.php" style="display:inline-block;margin-top:1.5rem;padding:0.75rem 2rem;
       background:var(--red);color:#fff;border-radius:12px;text-decoration:none;font-size:0.95rem">
      Volver al inicio
    </a>
  </div>
</div>

<!-- ── Audios del juego ─────────────────────────────────────────── -->
<audio id="audio-balota" src="https://assets.mixkit.co/active_storage/sfx/1074/1074-preview.mp3"  preload="auto"></audio>
<audio id="audio-marcar" src="https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3"  preload="auto"></audio>
<audio id="audio-bingo"  src="https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3"  preload="auto"></audio>

<!-- Overlay ganador - oculto por defecto -->
<div class="overlay" id="overlay-ganador" style="display:none">
  <div class="winner-card">
    <div style="font-size:4rem;margin-bottom:0.75rem">🏆</div>
    <p style="font-size:0.85rem;color:var(--muted);margin-bottom:0.35rem">¡Ganador!</p>
    <div class="winner-name" id="nombre-ganador"></div>
    <p style="margin-top:1rem;color:var(--muted);font-size:0.82rem">La partida ha finalizado</p>
    <a href="ganador.php" style="display:inline-block;margin-top:1.5rem;padding:0.75rem 2rem;
       background:var(--accent);color:#fff;border-radius:12px;text-decoration:none;font-size:0.95rem">
      Ver pantalla de ganador
    </a>
  </div>
</div>

<script>
const miId   = '<?= $miId ?>';
const codigo = '<?= $codigo ?>';
const LETRAS = ['A','B','C','D','E'];
const COLORES = { A:'#0094d9', B:'#56d726', C:'#f5d400', D:'#f93416', E:'#a855f7' };
const BG_COL  = { A:'rgba(0,148,217,0.14)', B:'rgba(86,215,38,0.11)', C:'rgba(245,212,0,0.14)', D:'rgba(249,52,22,0.11)', E:'rgba(168,85,247,0.11)' };

let carton   = null;
let marcadas = [];
let ultHistorial = 0;
let hostId   = null;
let redirigiendo = false;

// ── Audio ───────────────────────────────────────────────────────
function playAudio(id) {
  try {
    const a = document.getElementById(id);
    if (!a || !a.src || a.src.includes('LINK_')) return; // src no configurado aún
    a.currentTime = 0;
    a.play().catch(() => {}); // ignorar bloqueo autoplay
  } catch(e) {}
}

// ── Cambiar velocidad (host en partida) ─────────────────────────
async function cambiarVelocidad() {
  const vel = parseInt(document.getElementById('sel-vel-juego').value);
  const msg = document.getElementById('msg-velocidad');
  const r = await fetch('php/cambiar_velocidad.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ codigo, velocidad: vel }) });
  const d = await r.json();
  msg.style.color = d.ok ? 'var(--green)' : '#f93416';
  msg.textContent = d.ok ? `✓ Velocidad actualizada a ${vel}s` : (d.error || 'Error');
  setTimeout(() => msg.textContent = '', 3000);
}

// ── Inicialización ──────────────────────────────────────────────
async function init() {
  const r = await fetch(`php/verificar_estado.php?accion=carton&codigo=${codigo}`);
  const d = await r.json();
  if (!d.ok) { location.href = 'index.php'; return; }
  carton   = d.grid;
  marcadas = d.marcadas || [];
  renderCarton();
  poll();
  setInterval(poll, 3000);
}

// ── Cartón ──────────────────────────────────────────────────────
function renderCarton() {
  const g = document.getElementById('carton-grid');
  g.innerHTML = '';
  // Cabeceras
  LETRAS.forEach(l => {
    const h = document.createElement('div');
    h.className = `col-header h${l}`; h.textContent = l; g.appendChild(h);
  });
  // Celdas: carton[col][fila]
  for (let fila = 0; fila < 5; fila++) {
    for (let col = 0; col < 5; col++) {
      const val  = carton[col][fila];
      const cell = document.createElement('div');
      cell.className = 'cell'; cell.id = `c-${col}-${fila}`; cell.textContent = val;
      if (marcadas.includes(val)) cell.classList.add('marcada');
      cell.onclick = () => marcarCelda(val, cell);
      g.appendChild(cell);
    }
  }
}

// ── Marcar casilla ──────────────────────────────────────────────
async function marcarCelda(val, cell) {
  if (cell.classList.contains('marcada')) return;
  const r = await fetch('php/verificar_estado.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ accion:'marcar', codigo, valor: val }) });
  const d = await r.json();
  if (d.ok) { cell.classList.add('marcada'); marcadas.push(val); limpiarWarn(); playAudio('audio-marcar'); }
  else {
    mostrarWarn(d.error || 'Esa ficha aún no ha salido');
    cell.classList.add('shake');
    setTimeout(() => cell.classList.remove('shake'), 380);
  }
}

// ── Cantar Bingo ────────────────────────────────────────────────
async function cantarBingo() {
  const r = await fetch('php/cantar_bingo.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ codigo }) });
  const d = await r.json();
  if (d.ok && d.ganador) { playAudio('audio-bingo'); mostrarGanador(d.nombre_ganador); }
  else mostrarWarn(d.error || 'Bingo inválido – revisa tu cartón');
}

// ── Polling de estado ───────────────────────────────────────────
async function poll() {
  if (redirigiendo) return;
  try {
    const r = await fetch(`php/verificar_estado.php?accion=estado&codigo=${codigo}`);
    const d = await r.json();
    if (!d.ok) { redirigiendo = true; location.href = 'index.php'; return; }
    const sala = d.sala;

    if (sala.estado === 'cancelado') { mostrarCancelado(); return; }
    if (sala.estado === 'finalizado') { mostrarGanador(sala.ganador_nombre); return; }

    // Jugadores sidebar
    document.getElementById('jugadores-mini').innerHTML = sala.jugadores.map(j =>
      `<div style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem">
        <div class="status-dot"></div>
        <span>${esc(j.nombre)}${j.id===miId?' <span style="color:var(--muted)">(tú)</span>':''}</span>
      </div>`
    ).join('');

    const modoLabel = (sala.modo === 'reducido') ? ' · Modo reducido' : '';
    document.getElementById('hdr-info').textContent = `${sala.historial.length} fichas generadas${modoLabel}`;

    // Ficha nueva
    if (sala.historial.length !== ultHistorial) {
      ultHistorial = sala.historial.length;
      actualizarFicha(sala.historial);
      playAudio('audio-balota');
    }

    // Timer
    if (sala.proxima_ficha_at) {
      const restante = Math.max(0, sala.proxima_ficha_at - (Date.now()/1000));
      const total    = sala.intervalo_fichas || 12;
      const pct      = Math.round((restante / total) * 100);
      const fill     = document.getElementById('timer-fill');
      fill.style.width = pct + '%';
      fill.style.background = pct < 25 ? '#f93416' : pct < 50 ? 'var(--yellow)' : 'var(--accent)';
    }
  } catch(e) {}
}

// ── Actualizar ficha actual + historial ─────────────────────────
function actualizarFicha(historial) {
  if (!historial.length) return;
  const ultima = historial[historial.length - 1];
  const letra  = ultima[0], num = ultima.slice(1);
  const color  = COLORES[letra], bg = BG_COL[letra];

  const circ = document.getElementById('bola-circulo');
  circ.style.borderColor = color; circ.style.background = bg;
  circ.classList.remove('ball-circle'); void circ.offsetWidth; circ.classList.add('ball-circle');

  document.getElementById('bola-letra').style.color = color; document.getElementById('bola-letra').textContent = letra;
  document.getElementById('bola-num').style.color   = color; document.getElementById('bola-num').textContent   = num;
  document.getElementById('bola-display').textContent = ultima;
  document.getElementById('bola-sub').textContent     = `Ficha #${historial.length} · Busca ${ultima} en tu cartón`;
  document.getElementById('n-historial').textContent  = historial.length;

  // Strip (últimas 30, más reciente primero)
  document.getElementById('strip-historial').innerHTML = [...historial].reverse().slice(0,30).map(b => {
    const l = b[0];
    return `<div class="mini-ball" style="border-color:${COLORES[l]};background:${BG_COL[l]};color:${COLORES[l]}">${b.slice(1)}</div>`;
  }).join('');
}

// ── Ganador ─────────────────────────────────────────────────────
// ── Abandonar partida ───────────────────────────────────────────
function confirmarAbandonar() {
  const btn = document.getElementById('btn-abandonar');
  if (btn.dataset.confirmando === '1') {
    abandonarPartida();
  } else {
    btn.dataset.confirmando = '1';
    btn.textContent = '¿Seguro? Toca de nuevo para confirmar';
    setTimeout(() => {
      btn.dataset.confirmando = '0';
      btn.textContent = 'Abandonar partida';
    }, 4000);
  }
}

async function abandonarPartida() {
  const btn = document.getElementById('btn-abandonar');
  btn.disabled = true;
  btn.textContent = 'Saliendo...';
  try {
    await fetch('php/verificar_estado.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'salir', codigo })
    });
  } catch(e) {}
  redirigiendo = true;
  location.href = 'index.php';
}

// ── Cancelación (todos abandonaron) ─────────────────────────────
function mostrarCancelado() {
  if (redirigiendo) return;
  redirigiendo = true;
  document.getElementById('overlay-cancelado').style.display = 'flex';
}

function mostrarGanador(nombre) {
  if (redirigiendo) return;
  redirigiendo = true;
  sessionStorage.setItem('bingo_ganador', nombre);
  sessionStorage.setItem('bingo_codigo', codigo);
  document.getElementById('nombre-ganador').textContent = nombre;
  document.getElementById('overlay-ganador').style.display = 'flex';
}

function mostrarWarn(msg) { const e=document.getElementById('warn-msg'); e.textContent=msg; setTimeout(()=>e.textContent='',3500); }
function limpiarWarn()    { document.getElementById('warn-msg').textContent=''; }
function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

init();
</script>
</body>
</html>
