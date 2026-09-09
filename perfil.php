<?php
session_start();
require_once 'php/funciones.php';
inicializarSesionUsuario();

$usuario   = $_SESSION['usuario'];
$kyc       = $_SESSION['kyc'];
$billetera = $_SESSION['billetera'];
$nivel     = intval($kyc['nivel'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil y Verificación KYC – BINGO V2.1</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Navbar -->
<nav class="app-nav">
  <a href="index.php" class="nav-brand">
    <div class="logo-sm" style="font-size:1.5rem">BINGO</div>
    <span class="tagline" style="margin:0;font-size:0.65rem;color:var(--yellow)">V2.1 PAYPLAY</span>
  </a>
  <div class="nav-actions">
    <a href="wallet.php" class="chip-saldo">
      <span>🪙 $ <?= number_format($billetera['saldo_disponible'], 0, ',', '.') ?></span>
    </a>
    <a href="index.php" class="nav-link-btn" style="border-color:var(--accent);color:var(--accent)">
      🎮 Volver al Lobby
    </a>
  </div>
</nav>

<div class="page-top" style="max-width:700px">

  <!-- Header Perfil -->
  <div class="card" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap">
    <div class="avatar av-host" style="width:64px;height:64px;font-size:1.6rem">
      <?= strtoupper(substr($usuario['nickname'], 0, 2)) ?>
    </div>
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap">
        <h1 style="font-size:1.4rem;font-weight:600"><?= htmlspecialchars($usuario['nickname']) ?></h1>
        <span class="tx-badge badge-kyc-<?= $nivel ?>">
          KYC NIVEL <?= $nivel ?>: <?= $nivel === 2 ? 'FINANCIERO' : ($nivel === 1 ? 'IDENTIDAD' : 'BÁSICO') ?>
        </span>
      </div>
      <div style="font-size:0.85rem;color:var(--muted);margin-top:0.25rem">
        <?= htmlspecialchars($usuario['email']) ?>
      </div>
    </div>
    <a href="wallet.php" class="btn btn-green" style="font-size:0.82rem;padding:0.45rem 0.9rem">
      Billetera →
    </a>
  </div>

  <!-- Explicación de Niveles KYC -->
  <div style="margin-bottom:1.25rem">
    <div class="section-label">Estado de Cumplimiento (KYC)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));gap:0.75rem">
      
      <div class="card-sm" style="border-color:<?= $nivel >= 0 ? 'var(--border)' : 'transparent' ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem">
          <span style="font-weight:600;font-size:0.85rem">Nivel 0: Básico</span>
          <span style="color:var(--green)">✓</span>
        </div>
        <p style="font-size:0.75rem;color:var(--muted)">Acceso exclusivo a salas de juego gratuitas.</p>
      </div>

      <div class="card-sm" style="border-color:<?= $nivel >= 1 ? 'rgba(0,148,217,0.4)' : 'transparent' ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem">
          <span style="font-weight:600;font-size:0.85rem;color:#4dc2ff">Nivel 1: Identidad</span>
          <span style="color:<?= $nivel >= 1 ? 'var(--green)' : 'var(--muted)' ?>"><?= $nivel >= 1 ? '✓' : '○' ?></span>
        </div>
        <p style="font-size:0.75rem;color:var(--muted)">Recargas en línea y participación en salas Payplay.</p>
      </div>

      <div class="card-sm" style="border-color:<?= $nivel >= 2 ? 'rgba(86,215,38,0.4)' : 'transparent' ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem">
          <span style="font-weight:600;font-size:0.85rem;color:var(--green)">Nivel 2: Financiero</span>
          <span style="color:<?= $nivel >= 2 ? 'var(--green)' : 'var(--muted)' ?>"><?= $nivel >= 2 ? '✓' : '○' ?></span>
        </div>
        <p style="font-size:0.75rem;color:var(--muted)">Retiro de ganancias a cuentas bancarias CO.</p>
      </div>

    </div>
  </div>

  <!-- Formulario KYC Nivel 1: OTP Email -->
  <div class="card" style="margin-bottom:1.5rem">
    <div class="section-label" style="color:#4dc2ff">
      <span>📧</span> Verificación Nivel 1: Validación OTP
    </div>

    <?php if ($nivel >= 1): ?>
      <div style="display:flex;align-items:center;gap:0.75rem;background:rgba(0,148,217,0.1);padding:0.75rem 1rem;border-radius:10px;border:1px solid rgba(0,148,217,0.3)">
        <span style="font-size:1.3rem">✓</span>
        <div style="font-size:0.85rem">
          <strong>Identidad validada:</strong> Tu correo electrónico ha sido verificado con código OTP. Puedes jugar en salas Payplay y realizar recargas.
        </div>
      </div>
    <?php else: ?>
      <p style="font-size:0.82rem;color:var(--muted);margin-bottom:1rem">
        Ingresa tu correo para recibir un código de seguridad OTP de 6 dígitos.
      </p>
      <div style="display:flex;gap:0.6rem;margin-bottom:0.75rem">
        <input type="email" id="otp-email" value="<?= htmlspecialchars($usuario['email']) ?>" placeholder="correo@ejemplo.com" style="flex:1">
        <button class="btn btn-ghost" onclick="solicitarOtp()" style="white-space:nowrap;border-color:var(--accent);color:var(--accent)">
          Enviar OTP
        </button>
      </div>
      <div style="display:flex;gap:0.6rem">
        <input type="text" id="otp-input" placeholder="Código de 6 dígitos" maxlength="6" style="letter-spacing:0.2em;text-align:center;font-weight:bold">
        <button class="btn btn-blue" onclick="validarOtp()">Verificar →</button>
      </div>
      <div class="err-msg" id="msg-otp"></div>
    <?php endif; ?>
  </div>

  <!-- Formulario KYC Nivel 2: Documental -->
  <div class="card">
    <div class="section-label" style="color:var(--green)">
      <span>🪪</span> Verificación Nivel 2: Documento de Identidad y Banco
    </div>

    <?php if ($nivel >= 2): ?>
      <div style="display:flex;align-items:center;gap:0.75rem;background:rgba(86,215,38,0.1);padding:0.75rem 1rem;border-radius:10px;border:1px solid rgba(86,215,38,0.3)">
        <span style="font-size:1.3rem">✓</span>
        <div style="font-size:0.85rem">
          <strong>Verificación Financiera Aprobada:</strong> Documento <?= htmlspecialchars($usuario['documento'] ?? 'CC') ?> validado. Tu cuenta está habilitada para solicitar retiros de ganancias.
        </div>
      </div>
    <?php else: ?>
      <p style="font-size:0.82rem;color:var(--muted);margin-bottom:1rem">
        Sube una fotografía de tu documento de identidad (Cédula de Ciudadanía CC / CE) para habilitar retiros.
      </p>

      <div style="display:grid;grid-template-columns:1fr 2fr;gap:0.6rem;margin-bottom:0.75rem">
        <div>
          <label class="form-label">Tipo Documento</label>
          <select id="kyc-tipo-doc" style="width:100%;background:#1a3a14;border:1px solid rgba(255,255,255,0.18);border-radius:8px;color:#fff;padding:0.6rem;font-size:0.85rem">
            <option value="CC">C.C. (Cédula)</option>
            <option value="CE">C.E. (Extranjería)</option>
            <option value="NIT">NIT</option>
          </select>
        </div>
        <div>
          <label class="form-label">Número de Documento</label>
          <input type="text" id="kyc-num-doc" placeholder="Ej. 1020304050" maxlength="20">
        </div>
      </div>

      <div class="dropzone-kyc" onclick="document.getElementById('file-fake').click()">
        <div style="font-size:1.8rem;margin-bottom:0.3rem">📁</div>
        <div style="font-size:0.88rem;font-weight:500" id="label-archivo">Haz clic para seleccionar documento (PDF, JPG, PNG)</div>
        <div style="font-size:0.75rem;color:var(--muted);margin-top:0.2rem">Tamaño máx. 5MB. Copia legible por ambas caras.</div>
        <input type="file" id="file-fake" style="display:none" onchange="fileSelected(this)">
      </div>

      <button class="btn btn-green" id="btn-kyc-subir" onclick="subirKyc()">
        Enviar Documento para Aprobación →
      </button>
      <div class="err-msg" id="msg-kyc"></div>
    <?php endif; ?>
  </div>

</div>

<script>
function fileSelected(input) {
  if (input.files && input.files[0]) {
    document.getElementById('label-archivo').textContent = '✓ Archivo: ' + input.files[0].name;
    document.getElementById('label-archivo').style.color = 'var(--green)';
  }
}

async function solicitarOtp() {
  const email = document.getElementById('otp-email').value.trim();
  const msg   = document.getElementById('msg-otp');
  msg.style.display = 'none';

  if (!email) {
    msg.textContent = 'Ingresa un correo electrónico válido.';
    msg.style.display = 'block';
    return;
  }

  const r = await fetch('php/billetera_acciones.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accion: 'enviar_otp', email })
  });
  const d = await r.json();
  if (d.ok) {
    msg.textContent = `✓ ${d.mensaje} (Código Demo de prueba: ${d.otp_demo})`;
    msg.style.display = 'block';
    msg.style.color = 'var(--green)';
    document.getElementById('otp-input').value = d.otp_demo;
  }
}

async function validarOtp() {
  const otp = document.getElementById('otp-input').value.trim();
  const msg = document.getElementById('msg-otp');
  msg.style.display = 'none';

  if (!otp) {
    msg.textContent = 'Por favor ingresa el código OTP.';
    msg.style.display = 'block';
    return;
  }

  const r = await fetch('php/billetera_acciones.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accion: 'validar_otp', otp })
  });
  const d = await r.json();
  if (d.ok) {
    msg.textContent = `✓ ${d.mensaje}`;
    msg.style.display = 'block';
    msg.style.color = 'var(--green)';
    setTimeout(() => location.reload(), 1200);
  } else {
    msg.textContent = d.error;
    msg.style.display = 'block';
    msg.style.color = '#ff6b6b';
  }
}

async function subirKyc() {
  const tipo = document.getElementById('kyc-tipo-doc').value;
  const num  = document.getElementById('kyc-num-doc').value.trim();
  const msg  = document.getElementById('msg-kyc');
  msg.style.display = 'none';

  if (!num) {
    msg.textContent = 'Por favor ingresa tu número de documento.';
    msg.style.display = 'block';
    msg.style.color = '#ff6b6b';
    return;
  }

  const btn = document.getElementById('btn-kyc-subir');
  btn.disabled = true;
  btn.textContent = 'Validando documento...';

  const r = await fetch('php/billetera_acciones.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accion: 'subir_kyc', tipo_doc: tipo, num_doc: num })
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
    btn.textContent = 'Enviar Documento para Aprobación →';
  }
}
</script>
</body>
</html>
