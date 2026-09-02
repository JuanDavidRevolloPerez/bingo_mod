<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BINGO ONLINE</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-center">
  <div style="width:100%;max-width:460px">

    <div style="text-align:center;margin-bottom:2rem">
      <div class="logo">BINGO</div>
      <p class="tagline">Online Multiplayer</p>
    </div>

    <div class="card">
      <div class="tabs">
        <button class="tab active" onclick="tab('crear')">Crear sala</button>
        <button class="tab"        onclick="tab('unirse')">Unirse</button>
      </div>

      <!-- Panel Crear -->
      <div id="p-crear">
        <label class="form-label">Tu nombre</label>
        <input type="text" id="c-nombre" placeholder="ej. María" maxlength="20">
        <label class="form-label">Nombre de la sala</label>
        <input type="text" id="c-sala"   placeholder="ej. Bingo Familiar" maxlength="30">
        <button class="btn btn-blue" onclick="crearSala()">Crear sala →</button>
        <div class="err-msg" id="err-crear"></div>
      </div>

      <!-- Panel Unirse -->
      <div id="p-unirse" style="display:none">
        <label class="form-label">Tu nombre</label>
        <input type="text" id="u-nombre" placeholder="ej. Carlos" maxlength="20">
        <label class="form-label">Código de sala</label>
        <input type="text" id="u-codigo" placeholder="A7X3" maxlength="4"
               style="text-transform:uppercase;letter-spacing:0.22em;font-size:1.4rem;text-align:center">
        <button class="btn btn-green" onclick="unirseSala()">Unirse →</button>
        <div class="err-msg" id="err-unirse"></div>
      </div>

      <div class="divider"></div>
      <p class="hint">Comparte el código de 4 letras con tus amigos para jugar juntos</p>
    </div>
  </div>
</div>
<script>
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
  const r = await fetch('php/crear_sala.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ nombre, nombre_sala: sala }) });
  const d = await r.json();
  if (d.ok) location.href = 'espera.php';
  else mostrarError(err, d.error);
}

async function unirseSala() {
  const nombre = document.getElementById('u-nombre').value.trim();
  const codigo = document.getElementById('u-codigo').value.trim().toUpperCase();
  const err    = document.getElementById('err-unirse');
  err.style.display = 'none';
  if (!nombre || !codigo) { mostrarError(err,'Completa todos los campos'); return; }
  const r = await fetch('php/unirse_sala.php', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ nombre, codigo }) });
  const d = await r.json();
  if (d.ok) location.href = 'espera.php';
  else mostrarError(err, d.error);
}

function mostrarError(el, msg) { el.textContent = msg; el.style.display = 'block'; }

document.getElementById('u-codigo').addEventListener('input', function(){ this.value = this.value.toUpperCase(); });
</script>
</body>
</html>
