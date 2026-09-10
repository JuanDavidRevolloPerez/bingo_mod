<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>¡Ganador! – BINGO</title>
<link rel="stylesheet" href="style.css">
<style>
.confetti { position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:10; overflow:hidden; }
.c-piece  { position:absolute; top:-10px; width:10px; height:10px; opacity:0; animation: fall linear forwards; }
@keyframes fall {
  0%   { transform: translateY(0) rotate(0deg); opacity:1; }
  100% { transform: translateY(110vh) rotate(720deg); opacity:0; }
}
</style>
</head>
<body>
<div class="confetti" id="confetti"></div>

<div class="page-center" style="position:relative;z-index:20">
  <div style="width:100%;max-width:420px;text-align:center">

    <div class="card" style="padding:3rem 2rem">
      <div style="font-size:5rem;margin-bottom:1rem;animation:popIn 0.6s ease" id="trophy-emoji">🏆</div>

      <p style="font-size:0.8rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--muted);margin-bottom:0.5rem">
        ¡Ganador!
      </p>
      <div class="winner-name" id="nombre-ganador" style="margin-bottom:1rem">...</div>

      <div class="divider"></div>
      <p style="font-size:0.88rem;color:var(--muted);margin-bottom:1.5rem">La partida ha finalizado. ¡Gracias por jugar!</p>

      <a href="index.php" class="btn btn-blue" style="text-decoration:none;display:block"
         onclick="sessionStorage.clear()">Volver al inicio</a>
    </div>

    <div style="margin-top:1rem">
      <div class="logo-sm">BINGO</div>
      <p class="tagline">Online Multiplayer</p>
    </div>
  </div>
</div>

<script>
// Leer ganador de sessionStorage (set en juego.php)
const nombre = sessionStorage.getItem('bingo_ganador') || '¡Ganador!';
document.getElementById('nombre-ganador').textContent = nombre;
// Reproducir sonido de ganador
window.addEventListener('load', () => {
  try { const a=document.getElementById('audio-ganador'); a.currentTime=0; a.play().catch(()=>{}); } catch(e) {}
});

// Confetti
const colors = ['#0094d9','#56d726','#f5d400','#f93416','#308028','#fff'];
const cont = document.getElementById('confetti');
for (let i = 0; i < 80; i++) {
  const p = document.createElement('div');
  p.className = 'c-piece';
  p.style.left      = Math.random()*100 + '%';
  p.style.background = colors[Math.floor(Math.random()*colors.length)];
  p.style.width     = (6 + Math.random()*8) + 'px';
  p.style.height    = (6 + Math.random()*8) + 'px';
  p.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
  const dur = 1.5 + Math.random()*2.5;
  p.style.animationDuration = dur + 's';
  p.style.animationDelay    = (Math.random()*1.5) + 's';
  cont.appendChild(p);
}

// Si llegan directamente sin sessionStorage, consultar estado
if (!sessionStorage.getItem('bingo_ganador')) {
  const codigo = '<?= $_SESSION['codigo_sala'] ?? '' ?>';
  if (codigo) {
    fetch(`php/verificar_estado.php?accion=estado&codigo=${codigo}`)
      .then(r=>r.json()).then(d=>{
        if (d.ok && d.sala.ganador_nombre)
          document.getElementById('nombre-ganador').textContent = d.sala.ganador_nombre;
      });
  }
}
</script>
<audio id="audio-ganador" src="https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3" preload="auto"></audio>
</body>
</html>
