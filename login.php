<?php
/**
 * login.php - Módulo de Inicio de Sesión
 * BANG ONLINE V2.2 – Multijugador & Payplay
 */
session_start();

// Si ya está logueado, redirigir al inicio
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php'); exit;
}

$error   = $_SESSION['login_error']   ?? null;
$success = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_error'], $_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión – BANG ONLINE</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* ── Auth Pages (Mismos estilos que register.php) ─────────────────────────────── */
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
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 24px;
      padding: 2.25rem 2rem;
      backdrop-filter: blur(20px);
      box-shadow: 0 32px 64px rgba(0,0,0,0.5);
      animation: slideUp 0.45s cubic-bezier(0.4,0,0.2,1);
    }

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

    /* ── Input group ─── */
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
    }

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
    }

    .field-error {
      font-size: 0.75rem;
      color: #ff6b6b;
      margin-top: 0.3rem;
      display: none;
    }

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

    /* Botones */
    .btn-auth {
      width: 100%;
      padding: 0.9rem;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--accent) 0%, #0078b0 100%);
      color: #fff;
      font-family: 'DM Sans', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
      overflow: hidden;
      margin-top: 1rem;
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
    .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,148,217,0.4); }
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
  </style>
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-header">
      <div class="logo" style="font-size:2.2rem">BANG</div>
      <div class="auth-title">Bienvenido de nuevo</div>
      <div class="auth-subtitle">Ingresa a tu cuenta para jugar</div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error" id="server-alert">
      <span>⚠️</span> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success" id="server-alert">
      <span>✅</span> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Botón de Google -->
    <a href="php/auth/google.php?action=login" class="btn-google">
      <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google">
      Continuar con Google
    </a>
    
    <div class="auth-divider"><span>O ingresa con tu correo</span></div>

    <form id="form-login" novalidate>
      <div class="input-group">
        <label for="log-email">Correo electrónico</label>
        <div class="input-wrap">
          <span class="input-icon">📧</span>
          <input type="email" id="log-email" name="email" placeholder="tu@correo.com" autocomplete="email" required>
        </div>
        <div class="field-error" id="err-email">Ingresa un correo válido</div>
      </div>

      <div class="input-group">
        <label for="log-pass">Contraseña</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" id="log-pass" name="password" placeholder="Tu contraseña" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" id="btn-toggle-pass" title="Mostrar contraseña">👁️</button>
        </div>
        <div class="field-error" id="err-pass">La contraseña es requerida</div>
      </div>

      <button type="submit" class="btn-auth" id="btn-login">
        Iniciar Sesión →
      </button>
    </form>

    <div class="auth-footer">
      ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a><br><br>
      <a href="#">¿Olvidaste tu contraseña?</a>
    </div>

  </div>
</div>

<script>
const form     = document.getElementById('form-login');
const email    = document.getElementById('log-email');
const pass     = document.getElementById('log-pass');
const btnLogin = document.getElementById('btn-login');

document.getElementById('btn-toggle-pass').addEventListener('click', function () {
  const isPass = pass.type === 'password';
  pass.type = isPass ? 'text' : 'password';
  this.textContent = isPass ? '🙈' : '👁️';
});

function showErr(id, show) {
  const el = document.getElementById('err-' + id);
  if (el) el.style.display = show ? 'block' : 'none';
}

function markInput(input, isErr) {
  input.classList.toggle('input-error', isErr);
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  const prev = document.getElementById('server-alert');
  if (prev) prev.remove();

  let valid = true;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    showErr('email', true); markInput(email, true); valid = false;
  } else {
    showErr('email', false); markInput(email, false);
  }
  
  if (pass.value.trim().length === 0) {
    showErr('pass', true); markInput(pass, true); valid = false;
  } else {
    showErr('pass', false); markInput(pass, false);
  }

  if (!valid) return;

  btnLogin.disabled = true;
  btnLogin.innerHTML = '<span class="spinner"></span>Iniciando...';

  try {
    const res = await fetch('php/auth/login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        email:    email.value.trim().toLowerCase(),
        password: pass.value
      })
    });

    const data = await res.json();

    if (data.ok) {
      window.location.href = 'index.php'; // Redirige al inicio tras login exitoso
    } else {
      showAlert(data.error ?? 'Credenciales incorrectas', 'error');
      btnLogin.disabled = false;
      btnLogin.innerHTML = 'Iniciar Sesión →';
    }
  } catch (err) {
    showAlert('Error de conexión. Intenta de nuevo.', 'error');
    btnLogin.disabled = false;
    btnLogin.innerHTML = 'Iniciar Sesión →';
  }
});

function showAlert(msg, type) {
  const prev = document.getElementById('server-alert');
  if (prev) prev.remove();

  const div = document.createElement('div');
  div.id = 'server-alert';
  div.className = `alert alert-${type}`;
  div.innerHTML = (type === 'error' ? '⚠️ ' : '✅ ') + msg;

  form.insertAdjacentElement('beforebegin', div);
  div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
</body>
</html>
