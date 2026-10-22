<?php
/**
 * register.php - Módulo de Registro de Usuarios
 * BANG ONLINE V2.2 – Multijugador & Payplay
 */
session_start();

// Si ya está logueado, permitir igualmente ver el formulario de registro
// (se comentó la redirección automática a index.php)

$error   = $_SESSION['reg_error']   ?? null;
$success = $_SESSION['reg_success'] ?? null;
unset($_SESSION['reg_error'], $_SESSION['reg_success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear Cuenta – BANG ONLINE</title>
  <meta name="description" content="Regístrate gratis en BANG ONLINE y juega partidas multijugador con amigos. Modo gratuito y modo Payplay disponibles.">
  <link rel="stylesheet" href="style.css">
  <style>
    /* ── Auth Pages ─────────────────────────────── */
    .auth-wrapper {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem 1rem;
    }

    .auth-card {
      width: 100%;
      max-width: 440px;
      background: linear-gradient(180deg, rgba(255,255,255,0.07), rgba(255,255,255,0.03));
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 24px;
      padding: 2.25rem 2rem;
      backdrop-filter: blur(20px);
      box-shadow: 0 32px 64px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.08);
      animation: slideUp 0.45s cubic-bezier(0.4,0,0.2,1);
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    .auth-card:hover {
      border-color: rgba(255,255,255,0.18);
      box-shadow: 0 32px 64px rgba(0,0,0,0.5), 0 0 40px rgba(0,148,217,0.12), inset 0 1px 0 rgba(255,255,255,0.08);
    }

    /* Subtítulos de sección */
    .section-title {
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: rgba(232,232,232,0.35);
      margin: 1.25rem 0 0.85rem;
      padding-bottom: 0.4rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }

    .section-title:first-child { margin-top: 0; }

    @keyframes slideUp {
      from { transform: translateY(30px); opacity: 0; }
      to   { transform: none; opacity: 1; }
    }

    .auth-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .auth-title {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.55rem;
      font-weight: 500;
      color: #fff;
      margin-top: 0.75rem;
    }

    .auth-subtitle {
      font-size: 0.82rem;
      color: rgba(232,232,232,0.45);
      margin-top: 0.3rem;
    }

    /* ── Input group con ícono ─── */
    .input-group {
      position: relative;
      margin-bottom: 1rem;
    }

    .input-group label {
      display: block;
      font-size: 0.72rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: rgba(232,232,232,0.45);
      margin-bottom: 0.35rem;
      transition: color 0.2s;
    }

    .input-group:focus-within label { color: var(--accent); }

    .input-group:focus-within .input-icon { opacity: 1; }

    .input-group .input-wrap {
      position: relative;
    }

    .input-group .input-icon {
      position: absolute;
      left: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1rem;
      pointer-events: none;
      opacity: 0.5;
    }

    .input-group input {
      width: 100%;
      padding: 0.78rem 1rem 0.78rem 2.6rem;
      background: rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      color: #e8e8e8;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.97rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-group input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(0,148,217,0.15);
    }

    .input-group input::placeholder {
      color: rgba(232,232,232,0.2);
    }

    .input-group input.input-error {
      border-color: rgba(249,52,22,0.6);
      animation: shake 0.3s;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }

    .field-error {
      font-size: 0.75rem;
      color: #ff8c7a;
      background: rgba(249,52,22,0.08);
      border: 1px solid rgba(249,52,22,0.25);
      border-radius: 8px;
      padding: 0.35rem 0.6rem;
      margin-top: 0.35rem;
      display: none;
    }

    /* Toggle contraseña */
    .toggle-pass {
      position: absolute;
      right: 0.85rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: rgba(232,232,232,0.4);
      font-size: 1rem;
      padding: 0;
      transition: color 0.2s;
    }

    .toggle-pass:hover { color: rgba(232,232,232,0.8); }

    /* Select tipo documento */
    .input-group select {
      width: 100%;
      padding: 0.78rem 1rem 0.78rem 2.6rem;
      background: rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      color: #e8e8e8;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.97rem;
      outline: none;
      cursor: pointer;
      transition: border-color 0.2s;
      appearance: none;
    }

    .input-group select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(0,148,217,0.15);
    }

    .input-group select option {
      background: #1a3a14;
      color: #e8e8e8;
    }

    /* Password strength */
    .pass-strength {
      display: flex;
      gap: 4px;
      margin-top: 0.5rem;
    }

    .strength-bar {
      flex: 1;
      height: 3px;
      border-radius: 2px;
      background: rgba(255,255,255,0.1);
      transition: background 0.3s;
    }

    .strength-bar.active-weak   { background: var(--red); }
    .strength-bar.active-medium { background: var(--yellow); }
    .strength-bar.active-strong { background: var(--green); }

    .strength-label {
      font-size: 0.72rem;
      color: rgba(232,232,232,0.4);
      margin-top: 0.3rem;
    }

    /* Terms checkbox */
    .terms-row {
      display: flex;
      align-items: flex-start;
      gap: 0.7rem;
      margin: 1.25rem 0 1.5rem;
      cursor: pointer;
    }

    .terms-row input[type="checkbox"] {
      width: 18px;
      height: 18px;
      min-width: 18px;
      accent-color: var(--accent);
      cursor: pointer;
      margin-top: 1px;
    }

    .terms-row span {
      font-size: 0.8rem;
      color: rgba(232,232,232,0.5);
      line-height: 1.5;
    }

    .terms-row a {
      color: var(--accent);
      text-decoration: none;
    }

    /* Alert mensaje global */
    .alert {
      border-radius: 10px;
      padding: 0.8rem 1rem;
      font-size: 0.85rem;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }

    .alert-error {
      background: rgba(249,52,22,0.12);
      border: 1px solid rgba(249,52,22,0.3);
      color: #ff8c7a;
    }

    .alert-success {
      background: rgba(86,215,38,0.1);
      border: 1px solid rgba(86,215,38,0.3);
      color: var(--green);
    }

    /* Botón principal */
    .btn-auth {
      width: 100%;
      padding: 0.9rem;
      border: none;
      border-radius: 12px;
      transition: all 0.25s ease;
      background: linear-gradient(135deg, var(--accent) 0%, #0078b0 50%, var(--accent) 100%);
      background-size: 200% auto;
      color: #fff;
      font-family: 'DM Sans', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }

    .btn-auth::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
      opacity: 0;
      transition: opacity 0.2s;
    }

    .btn-auth:hover::after { opacity: 1; }
    .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,148,217,0.4); background-position: right center; }
    .btn-auth:active { transform: translateY(0); }
    .btn-auth:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

    .btn-google {
      width: 100%;
      padding: 0.9rem;
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 12px;
      background: rgba(0,0,0,0.3);
      color: #fff;
      font-family: 'DM Sans', sans-serif;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
      margin-bottom: 1rem;
    }
    
    .btn-google:hover {
      background: rgba(255,255,255,0.1);
      border-color: rgba(255,255,255,0.3);
    }
    
    .btn-google img {
      width: 20px;
      height: 20px;
    }

    /* Divider */
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 1.5rem 0;
    }

    .auth-divider::before,
    .auth-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(255,255,255,0.1);
    }

    .auth-divider span {
      font-size: 0.78rem;
      color: rgba(232,232,232,0.3);
      white-space: nowrap;
    }

    /* Link to login */
    .auth-footer {
      text-align: center;
      font-size: 0.85rem;
      color: rgba(232,232,232,0.45);
      margin-top: 1.5rem;
    }

    .auth-footer a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .auth-footer a:hover { color: #4dc2ff; }

    /* Spinner */
    .spinner {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: inline-block;
      vertical-align: middle;
      margin-right: 0.5rem;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Móvil ── */
    @media (max-width: 480px) {
      .auth-card {
        padding: 1.5rem 1.15rem;
        border-radius: 18px;
      }
      .auth-title { font-size: 1.3rem; }
      .auth-header { margin-bottom: 1.4rem; }
      .section-title { margin: 1rem 0 0.75rem; }
      .auth-divider { margin: 1.1rem 0; }
      .auth-divider span { font-size: 0.72rem; }
      .auth-footer { font-size: 0.82rem; }
      .btn-auth, .btn-google { padding: 0.85rem; font-size: 0.95rem; }
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Header -->
    <div class="auth-header">
      <div class="logo" style="font-size:2.2rem">BANG</div>
      <div class="auth-title">Crear tu cuenta</div>
      <div class="auth-subtitle">Únete y empieza a jugar en segundos</div>
    </div>

    <!-- Alertas del servidor -->
    <?php if ($error): ?>
    <div class="alert alert-error" id="server-alert">
      <span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success" id="server-alert">
      <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Botón de Google -->
    <a href="php/auth/google.php?action=login" class="btn-google">
      <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google">
      Registrarse con Google
    </a>
    
    <div class="auth-divider"><span>O usa tu correo</span></div>

    <!-- Formulario de Registro -->
    <form id="form-registro" novalidate>

      <div class="section-title">Datos de acceso</div>

      <!-- Nickname -->
      <div class="input-group">
        <label for="reg-nickname">Nickname / Alias</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span>
          <input
            type="text"
            id="reg-nickname"
            name="nickname"
            placeholder="ej. CrackBingo99"
            maxlength="30"
            autocomplete="username"
            required
          >
        </div>
        <div class="field-error" id="err-nickname">Mínimo 3 caracteres, solo letras, números y _</div>
      </div>

      <!-- Email -->
      <div class="input-group">
        <label for="reg-email">Correo electrónico</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
          <input
            type="email"
            id="reg-email"
            name="email"
            placeholder="tu@correo.com"
            autocomplete="email"
            required
          >
        </div>
        <div class="field-error" id="err-email">Ingresa un correo válido</div>
      </div>

      <!-- Contraseña -->
      <div class="input-group">
        <label for="reg-pass">Contraseña</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
          <input
            type="password"
            id="reg-pass"
            name="password"
            placeholder="Mínimo 8 caracteres"
            autocomplete="new-password"
            required
          >
          <button type="button" class="toggle-pass" id="btn-toggle-pass" title="Mostrar contraseña"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
        </div>
        <div class="pass-strength" id="pass-strength">
          <div class="strength-bar" id="sb1"></div>
          <div class="strength-bar" id="sb2"></div>
          <div class="strength-bar" id="sb3"></div>
          <div class="strength-bar" id="sb4"></div>
        </div>
        <div class="strength-label" id="strength-label"></div>
        <div class="field-error" id="err-pass">Mínimo 8 caracteres con letras y números</div>
      </div>

      <!-- Confirmar contraseña -->
      <div class="input-group">
        <label for="reg-pass2">Confirmar contraseña</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
          <input
            type="password"
            id="reg-pass2"
            name="password_confirm"
            placeholder="Repite la contraseña"
            autocomplete="new-password"
            required
          >
        </div>
        <div class="field-error" id="err-pass2">Las contraseñas no coinciden</div>
      </div>

      <div class="section-title">Datos personales</div>

      <!-- Tipo de documento -->
      <div class="input-group">
        <label for="reg-tipo-doc">Tipo de documento</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
          <select id="reg-tipo-doc" name="tipo_documento" required>
            <option value="">Selecciona...</option>
            <option value="CC">C.C. - Cédula de Ciudadanía</option>
            <option value="CE">C.E. - Cédula de Extranjería</option>
          </select>
        </div>
        <div class="field-error" id="err-tipo-doc">Selecciona un tipo de documento</div>
      </div>

      <!-- Número de documento -->
      <div class="input-group">
        <label for="reg-num-doc">Número de documento</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-hashtag" aria-hidden="true"></i></span>
          <input
            type="text"
            id="reg-num-doc"
            name="numero_documento"
            placeholder="Ej. 1020304050"
            maxlength="20"
            required
          >
        </div>
        <div class="field-error" id="err-num-doc">Ingresa tu número de documento</div>
      </div>

      <!-- Teléfono -->
      <div class="input-group">
        <label for="reg-telefono">Teléfono</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></span>
          <input
            type="tel"
            id="reg-telefono"
            name="telefono"
            placeholder="Ej. 3001234567"
            maxlength="15"
            required
          >
        </div>
        <div class="field-error" id="err-telefono">Ingresa un teléfono válido</div>
      </div>

      <!-- Fecha de nacimiento -->
      <div class="input-group">
        <label for="reg-fecha-nac">Fecha de nacimiento</label>
        <div class="input-wrap">
          <span class="input-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
          <input
            type="date"
            id="reg-fecha-nac"
            name="fecha_nacimiento"
            required
          >
        </div>
        <div class="field-error" id="err-fecha-nac">Debes ser mayor de edad (18+)</div>
      </div>

      <!-- Términos y condiciones -->
      <label class="terms-row" for="reg-terms">
        <input type="checkbox" id="reg-terms" name="terminos" required>
        <span>Acepto los <a href="#">Términos y Condiciones</a> y la <a href="#">Política de Privacidad</a> de BANG ONLINE.</span>
      </label>

      <!-- Submit -->
      <button type="submit" class="btn-auth" id="btn-registrar">
        Crear mi cuenta →
      </button>
    </form>

    <div class="auth-divider"><span>¿Ya tienes cuenta?</span></div>

    <div class="auth-footer">
      <a href="login.php">Iniciar sesión →</a>
    </div>

  </div><!-- /.auth-card -->
</div><!-- /.auth-wrapper -->

<script>
// ── Validaciones en tiempo real ───────────────────────────────────────────
const form     = document.getElementById('form-registro');
const nick     = document.getElementById('reg-nickname');
const email    = document.getElementById('reg-email');
const pass     = document.getElementById('reg-pass');
const pass2    = document.getElementById('reg-pass2');
const tipoDoc  = document.getElementById('reg-tipo-doc');
const numDoc   = document.getElementById('reg-num-doc');
const telefono = document.getElementById('reg-telefono');
const fechaNac = document.getElementById('reg-fecha-nac');
const terms    = document.getElementById('reg-terms');
const btnReg   = document.getElementById('btn-registrar');

// Toggle mostrar/ocultar contraseña
document.getElementById('btn-toggle-pass').addEventListener('click', function () {
  const isPass = pass.type === 'password';
  pass.type = isPass ? 'text' : 'password';
  this.innerHTML = isPass
    ? '<i class="fa-solid fa-eye-slash" aria-hidden="true"></i>'
    : '<i class="fa-solid fa-eye" aria-hidden="true"></i>';
});

// Fuerza de contraseña
pass.addEventListener('input', () => {
  const val   = pass.value;
  const score = calcStrength(val);
  renderStrength(score);
});

function calcStrength(val) {
  let score = 0;
  if (val.length >= 8)                          score++;
  if (/[A-Z]/.test(val))                        score++;
  if (/[0-9]/.test(val))                        score++;
  if (/[^A-Za-z0-9]/.test(val))                score++;
  return score;
}

function renderStrength(score) {
  const bars   = ['sb1','sb2','sb3','sb4'];
  const labels = ['','Débil','Aceptable','Fuerte','Muy fuerte'];
  const classes = { 1:'active-weak', 2:'active-medium', 3:'active-strong', 4:'active-strong' };

  bars.forEach((id, i) => {
    const el = document.getElementById(id);
    el.className = 'strength-bar';
    if (i < score) el.classList.add(classes[score] ?? 'active-weak');
  });
  document.getElementById('strength-label').textContent = labels[score] ?? '';
}

// Validación individual de campos
function showErr(id, show) {
  const el = document.getElementById('err-' + id);
  if (el) el.style.display = show ? 'block' : 'none';
}

function markInput(input, isErr) {
  input.classList.toggle('input-error', isErr);
}

nick.addEventListener('blur', () => {
  const ok = /^[a-zA-Z0-9_]{3,30}$/.test(nick.value.trim());
  showErr('nickname', !ok);
  markInput(nick, !ok);
});

email.addEventListener('blur', () => {
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
  showErr('email', !ok);
  markInput(email, !ok);
});

pass.addEventListener('blur', () => {
  const ok = pass.value.length >= 8 && /[a-zA-Z]/.test(pass.value) && /[0-9]/.test(pass.value);
  showErr('pass', !ok);
  markInput(pass, !ok);
});

pass2.addEventListener('blur', () => {
  const ok = pass2.value === pass.value && pass2.value.length > 0;
  showErr('pass2', !ok);
  markInput(pass2, !ok);
});

// ── Submit ────────────────────────────────────────────────────────────────
form.addEventListener('submit', async (e) => {
  e.preventDefault();

  // Limpiar alertas previas del servidor
  const prev = document.getElementById('server-alert');
  if (prev) prev.remove();

  // Validar todo
  let valid = true;

  if (!/^[a-zA-Z0-9_]{3,30}$/.test(nick.value.trim())) {
    showErr('nickname', true); markInput(nick, true); valid = false;
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    showErr('email', true); markInput(email, true); valid = false;
  }
  if (pass.value.length < 8 || !/[a-zA-Z]/.test(pass.value) || !/[0-9]/.test(pass.value)) {
    showErr('pass', true); markInput(pass, true); valid = false;
  }
  if (pass2.value !== pass.value) {
    showErr('pass2', true); markInput(pass2, true); valid = false;
  }
  if (!tipoDoc.value) {
    showErr('tipo-doc', true); markInput(tipoDoc, true); valid = false;
  }
  if (!numDoc.value.trim()) {
    showErr('num-doc', true); markInput(numDoc, true); valid = false;
  }
  if (!/^[0-9]{7,15}$/.test(telefono.value.trim())) {
    showErr('telefono', true); markInput(telefono, true); valid = false;
  }
  if (!fechaNac.value) {
    showErr('fecha-nac', true); markInput(fechaNac, true); valid = false;
  } else {
    const fecha = new Date(fechaNac.value);
    const hoy = new Date();
    let edad = hoy.getFullYear() - fecha.getFullYear();
    const mes = hoy.getMonth() - fecha.getMonth();
    if (mes < 0 || (mes === 0 && hoy.getDate() < fecha.getDate())) edad--;
    if (edad < 18) {
      showErr('fecha-nac', true); markInput(fechaNac, true); valid = false;
    }
  }
  if (!terms.checked) {
    showAlert('Debes aceptar los términos y condiciones', 'error'); valid = false;
  }

  if (!valid) return;

  // Loading state
  btnReg.disabled = true;
  btnReg.innerHTML = '<span class="spinner"></span>Creando cuenta...';

  try {
    const res = await fetch('php/auth/registro.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        nickname: nick.value.trim(),
        email:    email.value.trim().toLowerCase(),
        password: pass.value,
        tipo_documento: tipoDoc.value,
        numero_documento: numDoc.value.trim(),
        telefono: telefono.value.trim(),
        fecha_nacimiento: fechaNac.value
      })
    });

    const data = await res.json();

    if (data.ok) {
      showAlert('<i class="fa-solid fa-circle-check" aria-hidden="true"></i> ¡Cuenta creada! Redirigiendo al inicio...', 'success');
      setTimeout(() => { window.location.href = 'login.php'; }, 1800);
    } else {
      showAlert(data.error ?? 'Error al crear la cuenta', 'error');
      btnReg.disabled = false;
      btnReg.innerHTML = 'Crear mi cuenta →';
    }
  } catch (err) {
    showAlert('Error de conexión. Intenta de nuevo.', 'error');
    btnReg.disabled = false;
    btnReg.innerHTML = 'Crear mi cuenta →';
  }
});

function showAlert(msg, type) {
  // Eliminar previo
  const prev = document.getElementById('server-alert');
  if (prev) prev.remove();

  const div = document.createElement('div');
  div.id = 'server-alert';
  div.className = `alert alert-${type}`;
  div.innerHTML = (type === 'error' ? '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> ' : '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> ') + msg;

  form.insertAdjacentElement('beforebegin', div);
  div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
</body>
</html>
