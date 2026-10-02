<?php
/**
 * wompi_simulado.php - Checkout Wompi Simulado (Proyecto Educativo)
 * 
 * Simula el flujo de pago de Wompi sin credenciales reales.
 * Permite recargar fichas usando métodos de pago colombianos sin tarjeta.
 */
session_start();
require_once __DIR__ . '/funciones.php';

inicializarSesionConBD();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$monto = intval($_GET['monto'] ?? 0);
if ($monto < 10000) {
    $monto = 10000;
}

$fichas = intval($monto / VALOR_FICHA);
$referencia = 'WOMPI-SIM-' . strtoupper(bin2hex(random_bytes(4)));
$usuario = $_SESSION['usuario'];

// Procesar el pago simulado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo = $_POST['metodo'] ?? '';
    $cuenta = $_POST['cuenta'] ?? '';
    
    if (empty($metodo) || empty($cuenta)) {
        $error = 'Debes seleccionar un método de pago e ingresar tu número de cuenta/teléfono.';
    } else {
        // Simular procesamiento exitoso
        require_once __DIR__ . '/../database/db.php';
        global $pdo;
        
        $usuarioId = (int)$_SESSION['usuario_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Actualizar saldo
            $stmt = $pdo->prepare('SELECT id, saldo_disponible FROM billeteras WHERE usuario_id = ? FOR UPDATE');
            $stmt->execute([$usuarioId]);
            $billetera = $stmt->fetch();
            
            if ($billetera) {
                $nuevoSaldo = floatval($billetera['saldo_disponible']) + $fichas;
                $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = ? WHERE id = ?');
                $stmt->execute([$nuevoSaldo, $billetera['id']]);
                
                // Registrar transacción
                $stmt = $pdo->prepare('INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $billetera['id'],
                    'RECARGA',
                    $fichas,
                    $nuevoSaldo,
                    "Recarga vía Wompi Simulado ({$metodo}) - {$fichas} fichas",
                    $referencia
                ]);
                
                // Actualizar sesión
                $_SESSION['billetera']['saldo_disponible'] = $nuevoSaldo;
                $_SESSION['billetera']['transacciones'][] = [
                    'id' => count($_SESSION['billetera']['transacciones']) + 1,
                    'tipo' => 'RECARGA',
                    'monto' => $fichas,
                    'saldo_resultante' => $nuevoSaldo,
                    'descripcion' => "Recarga vía Wompi Simulado ({$metodo})",
                    'referencia' => $referencia,
                    'fecha' => date('Y-m-d H:i:s')
                ];
            }
            
            $pdo->commit();
            
            // Redirigir con éxito
            header("Location: ../wallet.php?recarga=ok&ref={$referencia}&fichas={$fichas}");
            exit;
            
        } catch (\PDOException $e) {
            $pdo->rollBack();
            error_log('[WOMPI SIM] Error: ' . $e->getMessage());
            $error = 'Error al procesar el pago. Intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout Wompi Simulado – BANG ONLINE</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    .checkout-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem 1rem;
    }
    .checkout-card {
      width: 100%;
      max-width: 480px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 24px;
      padding: 2.25rem 2rem;
      backdrop-filter: blur(20px);
      box-shadow: 0 32px 64px rgba(0,0,0,0.5);
    }
    .checkout-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .checkout-logo {
      font-size: 2rem;
      font-weight: 800;
      color: #6c5ce7;
      margin-bottom: 0.5rem;
    }
    .checkout-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #fff;
    }
    .checkout-subtitle {
      font-size: 0.85rem;
      color: rgba(232,232,232,0.5);
      margin-top: 0.25rem;
    }
    .amount-box {
      background: rgba(108,92,231,0.1);
      border: 1px solid rgba(108,92,231,0.3);
      border-radius: 12px;
      padding: 1rem;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .amount-label {
      font-size: 0.75rem;
      color: rgba(232,232,232,0.5);
      text-transform: uppercase;
      letter-spacing: 0.1em;
    }
    .amount-value {
      font-size: 1.75rem;
      font-weight: 700;
      color: #a29bfe;
      margin-top: 0.25rem;
    }
    .amount-fichas {
      font-size: 0.9rem;
      color: rgba(232,232,232,0.6);
      margin-top: 0.25rem;
    }
    .method-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }
    .method-btn {
      background: rgba(0,0,0,0.3);
      border: 2px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      padding: 1rem;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s;
    }
    .method-btn:hover {
      border-color: rgba(108,92,231,0.5);
    }
    .method-btn.selected {
      border-color: #6c5ce7;
      background: rgba(108,92,231,0.15);
    }
    .method-icon {
      font-size: 1.75rem;
      margin-bottom: 0.35rem;
    }
    .method-name {
      font-size: 0.8rem;
      font-weight: 600;
      color: #fff;
    }
    .input-group {
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
    .input-group input {
      width: 100%;
      padding: 0.78rem 1rem;
      background: rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      color: #e8e8e8;
      font-size: 0.97rem;
      outline: none;
    }
    .input-group input:focus {
      border-color: #6c5ce7;
      box-shadow: 0 0 0 3px rgba(108,92,231,0.15);
    }
    .btn-pay {
      width: 100%;
      padding: 0.9rem;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, #6c5ce7 0%, #5a4bd1 100%);
      color: #fff;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      margin-top: 1rem;
    }
    .btn-pay:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(108,92,231,0.4);
    }
    .btn-pay:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .demo-badge {
      display: inline-block;
      background: rgba(255,193,7,0.15);
      border: 1px solid rgba(255,193,7,0.3);
      color: #ffc107;
      font-size: 0.7rem;
      padding: 0.25rem 0.6rem;
      border-radius: 20px;
      margin-bottom: 1rem;
    }
    .alert-error {
      background: rgba(249,52,22,0.12);
      border: 1px solid rgba(249,52,22,0.3);
      color: #ff8c7a;
      border-radius: 10px;
      padding: 0.8rem 1rem;
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }
    .spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      margin-right: 0.5rem;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
<div class="checkout-wrapper">
  <div class="checkout-card">
    <div class="checkout-header">
      <div class="demo-badge">MODO SIMULACIÓN - PROYECTO EDUCATIVO</div>
      <div class="checkout-logo">Wompi</div>
      <div class="checkout-title">Checkout de Pago</div>
      <div class="checkout-subtitle">Recarga tus fichas de BANG ONLINE</div>
    </div>

    <div class="amount-box">
      <div class="amount-label">Monto a pagar</div>
      <div class="amount-value">$ <?= number_format($monto, 0, ',', '.') ?> COP</div>
      <div class="amount-fichas">+ <?= $fichas ?> fichas a acreditar</div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-pago">
      <div class="method-grid">
        <div class="method-btn" onclick="selectMethod('PSE', this)">
          <div class="method-icon">🏦</div>
          <div class="method-name">PSE</div>
        </div>
        <div class="method-btn" onclick="selectMethod('Nequi', this)">
          <div class="method-icon">📱</div>
          <div class="method-name">Nequi</div>
        </div>
        <div class="method-btn" onclick="selectMethod('Daviplata', this)">
          <div class="method-icon">📲</div>
          <div class="method-name">Daviplata</div>
        </div>
        <div class="method-btn" onclick="selectMethod('Efecty', this)">
          <div class="method-icon">💵</div>
          <div class="method-name">Efecty</div>
        </div>
      </div>

      <div class="input-group">
        <label id="label-cuenta">Número de cuenta / Teléfono</label>
        <input type="text" id="cuenta" name="cuenta" placeholder="Ingresa tu número" required>
      </div>

      <input type="hidden" name="metodo" id="metodo" value="">

      <button type="submit" class="btn-pay" id="btn-pagar">
        Pagar $ <?= number_format($monto, 0, ',', '.') ?> COP →
      </button>
    </form>

    <div style="text-align:center;margin-top:1rem">
      <a href="../wallet.php" style="color:rgba(232,232,232,0.5);font-size:0.85rem;text-decoration:none">
        ← Volver a la billetera
      </a>
    </div>
  </div>
</div>

<script>
let metodoSeleccionado = '';

function selectMethod(metodo, el) {
  document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  metodoSeleccionado = metodo;
  document.getElementById('metodo').value = metodo;
  
  const label = document.getElementById('label-cuenta');
  const input = document.getElementById('cuenta');
  
  if (metodo === 'PSE') {
    label.textContent = 'Número de cuenta bancaria';
    input.placeholder = 'Ej. 1234567890';
  } else if (metodo === 'Nequi' || metodo === 'Daviplata') {
    label.textContent = 'Número de teléfono';
    input.placeholder = 'Ej. 3001234567';
  } else if (metodo === 'Efecty') {
    label.textContent = 'Número de cédula';
    input.placeholder = 'Ej. 1020304050';
  }
}

document.getElementById('form-pago').addEventListener('submit', function(e) {
  if (!metodoSeleccionado) {
    e.preventDefault();
    alert('Selecciona un método de pago');
    return;
  }
  const btn = document.getElementById('btn-pagar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>Procesando pago...';
});
</script>
</body>
</html>
