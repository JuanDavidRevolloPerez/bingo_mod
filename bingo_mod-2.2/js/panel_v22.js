/**
 * panel_v22.js - Panel de Control Interactivo, Simulador de Pasarelas y Gestor Multi-Perfil V2.2
 */

(function () {
  'use strict';

  // 1. Inyectar Estructura HTML del FAB, Drawer, Checkout Modal y Toasts
  function inyectarInterfazV22() {
    if (document.getElementById('v22-fab')) return;

    const div = document.createElement('div');
    div.id = 'v22-container';
    div.innerHTML = `
      <!-- Botón Flotante (FAB) -->
      <button id="v22-fab" onclick="togglePanelV22()" title="Abrir Panel Interactivo V2.2">
        <span style="font-size:1.25rem">🛠️</span>
        <span class="v22-fab-text">Panel V2.2</span>
      </button>

      <!-- Overlay & Drawer Lateral -->
      <div id="v22-drawer-overlay" onclick="togglePanelV22()"></div>
      <div id="v22-drawer">
        <div class="v22-drawer-header">
          <div>
            <div style="font-size:1.1rem;font-weight:600;display:flex;align-items:center;gap:0.4rem">
              <span>🛠️</span> Panel de Control V2.2
            </div>
            <div style="font-size:0.75rem;color:var(--muted)">Herramientas interactivas de prueba y simulación</div>
          </div>
          <button class="v22-btn-close" onclick="togglePanelV22()">✕</button>
        </div>

        <!-- Pestañas del Panel -->
        <div class="v22-tabs">
          <button class="v22-tab active" onclick="tabV22('perfiles')">👤 Perfiles</button>
          <button class="v22-tab" onclick="tabV22('pasarela')">💳 Pasarela</button>
          <button class="v22-tab" onclick="tabV22('pozo')">💰 Bots & Pozo</button>
          <button class="v22-tab" onclick="tabV22('calculadora')">🧮 Split Pot</button>
        </div>

        <div class="v22-drawer-body">
          <!-- Tab 1: Perfiles -->
          <div id="v22-tab-perfiles">
            <div class="v22-section-title">Cambio Rápido de Identidad KYC</div>
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
              Prueba cómo reaccionan las salas Payplay y la billetera según el nivel del usuario.
            </p>

            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1rem">
              <button class="v22-btn-option" onclick="cambiarPerfilRapido('nivel_0')">
                <span class="badge-kyc-0 tx-badge">Nivel 0</span>
                <div style="text-align:left;flex:1">
                  <div style="font-weight:600;font-size:0.85rem">Carlos (Básico)</div>
                  <div style="font-size:0.72rem;color:var(--muted)">Saldo: $0 | Solo salas gratuitas</div>
                </div>
              </button>

              <button class="v22-btn-option" onclick="cambiarPerfilRapido('nivel_1')">
                <span class="badge-kyc-1 tx-badge">Nivel 1</span>
                <div style="text-align:left;flex:1">
                  <div style="font-weight:600;font-size:0.85rem">María (Jugadora)</div>
                  <div style="font-size:0.72rem;color:var(--muted)">Saldo: $25.000 | OTP Verificado</div>
                </div>
              </button>

              <button class="v22-btn-option" onclick="cambiarPerfilRapido('nivel_2')">
                <span class="badge-kyc-2 tx-badge">Nivel 2</span>
                <div style="text-align:left;flex:1">
                  <div style="font-weight:600;font-size:0.85rem">Andrés (VIP)</div>
                  <div style="font-size:0.72rem;color:var(--muted)">Saldo: $150.000 | Retiros Habilitados</div>
                </div>
              </button>
            </div>

            <div class="v22-section-title">Perfil Personalizado</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;margin-bottom:0.5rem">
              <input type="text" id="v22-cust-nom" placeholder="Tu alias" style="padding:0.4rem;font-size:0.8rem">
              <input type="number" id="v22-cust-saldo" placeholder="Saldo COP" value="50000" style="padding:0.4rem;font-size:0.8rem">
            </div>
            <div style="display:flex;gap:0.4rem;margin-bottom:0.75rem">
              <select id="v22-cust-nivel" style="flex:1;background:#1a3a14;color:#fff;border:1px solid var(--border);border-radius:6px;padding:0.4rem;font-size:0.8rem">
                <option value="0">KYC 0 (Básico)</option>
                <option value="1" selected>KYC 1 (Identidad)</option>
                <option value="2">KYC 2 (Financiero)</option>
              </select>
              <button class="btn btn-green" style="padding:0.4rem 0.8rem;font-size:0.8rem" onclick="aplicarPerfilCustom()">
                Aplicar
              </button>
            </div>
          </div>

          <!-- Tab 2: Pasarela Wompi / ePayco -->
          <div id="v22-tab-pasarela" style="display:none">
            <div class="v22-section-title">Simulador de Checkout Wompi / ePayco</div>
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
              Prueba la experiencia de usuario del widget oficial de pagos en Colombia.
            </p>

            <button class="btn btn-green" style="width:100%;margin-bottom:1rem" onclick="abrirCheckoutModal(20000)">
              🛍️ Abrir Widget Checkout ($20.000 COP)
            </button>

            <div class="v22-section-title">Simulador de Webhooks SHA-256 (RF-15)</div>
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.5rem">
              Envía un evento simulado firmado con hash criptográfico para verificar idempotencia.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;margin-bottom:0.5rem">
              <input type="number" id="v22-wh-monto" value="50000" step="5000" placeholder="Monto" style="padding:0.4rem;font-size:0.8rem">
              <select id="v22-wh-evento" style="background:#1a3a14;color:#fff;border:1px solid var(--border);border-radius:6px;padding:0.4rem;font-size:0.8rem">
                <option value="APPROVED">APPROVED (Aprobado)</option>
                <option value="DECLINED">DECLINED (Rechazado)</option>
              </select>
            </div>
            <button class="btn btn-blue" style="width:100%;padding:0.5rem;font-size:0.82rem" onclick="dispararWebhookSimulado()">
              ⚡ Disparar Webhook
            </button>
            <div id="v22-wh-res" style="font-size:0.75rem;margin-top:0.5rem;display:none;padding:0.5rem;background:rgba(0,0,0,0.3);border-radius:8px;word-break:break-all"></div>
          </div>

          <!-- Tab 3: Bots & Pozo -->
          <div id="v22-tab-pozo" style="display:none">
            <div class="v22-section-title">Inyección de Jugadores Simulados (Bots)</div>
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
              Añade participantes a una sala de espera Payplay para ver el incremento automático del Pozo y cálculo de Rake.
            </p>

            <label class="form-label" style="font-size:0.75rem">Código de Sala Activa</label>
            <input type="text" id="v22-bot-codigo" placeholder="Ej. A7X3" maxlength="4" style="text-transform:uppercase;font-weight:bold;margin-bottom:0.75rem">

            <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:0.4rem;margin-bottom:0.75rem">
              <button class="v22-btn-option" style="justify-content:center;padding:0.5rem" onclick="inyectarBots(1)">+1 Bot</button>
              <button class="v22-btn-option" style="justify-content:center;padding:0.5rem" onclick="inyectarBots(3)">+3 Bots</button>
              <button class="v22-btn-option" style="justify-content:center;padding:0.5rem" onclick="inyectarBots(5)">+5 Bots</button>
            </div>
            <div id="v22-bot-msg" style="font-size:0.75rem;color:var(--green);display:none"></div>
          </div>

          <!-- Tab 4: Calculadora & Split Pot -->
          <div id="v22-tab-calculadora" style="display:none">
            <div class="v22-section-title">Calculadora de Pozo y Split Pot (RF-21)</div>
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.75rem">
              Comprueba cómo se calcula la comisión (Rake 10%) y el reparto equitativo en caso de empate.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem">
              <div>
                <label class="form-label" style="font-size:0.72rem">Buy-in (COP)</label>
                <input type="number" id="calc-buyin" value="10000" oninput="recalcularSplitPot()" style="padding:0.4rem;font-size:0.85rem">
              </div>
              <div>
                <label class="form-label" style="font-size:0.72rem">Jugadores</label>
                <input type="number" id="calc-jugadores" value="6" oninput="recalcularSplitPot()" style="padding:0.4rem;font-size:0.85rem">
              </div>
            </div>

            <div style="margin-bottom:0.75rem">
              <label class="form-label" style="font-size:0.72rem">Ganadores simultáneos (Empate en misma balota)</label>
              <select id="calc-ganadores" onchange="recalcularSplitPot()" style="width:100%;background:#1a3a14;color:#fff;border:1px solid var(--border);border-radius:6px;padding:0.4rem;font-size:0.85rem">
                <option value="1">1 Ganador Único</option>
                <option value="2">2 Ganadores (Empate 50% / 50%)</option>
                <option value="3">3 Ganadores (Empate 33.3% c/u)</option>
              </select>
            </div>

            <div style="background:rgba(204,245,0,0.06);border:1px solid rgba(204,245,0,0.25);border-radius:10px;padding:0.85rem">
              <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.3rem">
                <span style="color:var(--muted)">Pozo Total Bruto:</span>
                <strong id="calc-res-bruto">$ 60.000</strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.3rem">
                <span style="color:var(--yellow)">Comisión Plataforma (10%):</span>
                <strong id="calc-res-rake" style="color:var(--yellow)">- $ 6.000</strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.3rem;border-top:1px solid var(--border);padding-top:0.3rem">
                <span>Pozo Neto a Repartir:</span>
                <strong id="calc-res-neto" style="color:var(--green)">$ 54.000</strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.92rem;font-weight:bold;margin-top:0.4rem;background:rgba(255,255,255,0.05);padding:0.4rem;border-radius:6px">
                <span style="color:var(--accent)">Premio por Ganador:</span>
                <span id="calc-res-por-ganador" style="color:#fff">$ 54.000</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de Checkout Interactivo (Wompi/ePayco) -->
      <div id="v22-checkout-overlay" style="display:none" onclick="cerrarCheckoutModal()">
        <div id="v22-checkout-modal" onclick="event.stopPropagation()">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <div style="display:flex;align-items:center;gap:0.5rem">
              <div style="font-size:1.3rem">🔒</div>
              <div>
                <div style="font-weight:600;font-size:0.95rem">Checkout Seguro Colombia</div>
                <div style="font-size:0.7rem;color:var(--muted)">Integración Wompi / PSE / Nequi</div>
              </div>
            </div>
            <button class="v22-btn-close" onclick="cerrarCheckoutModal()">✕</button>
          </div>

          <div style="background:rgba(0,0,0,0.25);border:1px solid var(--border);border-radius:12px;padding:0.85rem;margin-bottom:1rem">
            <div style="font-size:0.75rem;color:var(--muted)">Total a pagar</div>
            <div style="font-size:1.8rem;font-family:'Black Han Sans',sans-serif;color:var(--yellow)" id="v22-modal-monto">
              $ 20.000 COP
            </div>
            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.2rem">
              Ref: <span id="v22-modal-ref" style="font-family:monospace">WOMPI-DEMO-01</span>
            </div>
          </div>

          <div class="pay-methods-grid" style="margin-bottom:1rem">
            <div class="pay-card active" onclick="selMetodoModal(this, 'PSE')">
              <span style="font-size:1.1rem">🏦</span>
              <span style="font-size:0.72rem;font-weight:600">PSE</span>
            </div>
            <div class="pay-card" onclick="selMetodoModal(this, 'Nequi')">
              <span style="font-size:1.1rem">📱</span>
              <span style="font-size:0.72rem;font-weight:600">Nequi</span>
            </div>
            <div class="pay-card" onclick="selMetodoModal(this, 'Daviplata')">
              <span style="font-size:1.1rem">📲</span>
              <span style="font-size:0.72rem;font-weight:600">Daviplata</span>
            </div>
            <div class="pay-card" onclick="selMetodoModal(this, 'Tarjeta')">
              <span style="font-size:1.1rem">💳</span>
              <span style="font-size:0.72rem;font-weight:600">Tarjeta</span>
            </div>
          </div>

          <button class="btn btn-green" id="v22-btn-pagar-modal" onclick="confirmarPagoModal()">
            Confirmar y Acreditar Fichas →
          </button>
        </div>
      </div>

      <!-- Contenedor de Notificaciones Toast -->
      <div id="v22-toast-container"></div>
    `;

    document.body.appendChild(div);

    // Auto-detectar código de sala si existe en la vista
    const codigoInput = document.getElementById('v22-bot-codigo');
    if (codigoInput && typeof window.codigo !== 'undefined') {
      codigoInput.value = window.codigo;
    }
  }

  // 2. Funciones de Navegación del Panel
  window.togglePanelV22 = function () {
    const drawer = document.getElementById('v22-drawer');
    const overlay = document.getElementById('v22-drawer-overlay');
    const abierto = drawer.classList.contains('open');
    if (abierto) {
      drawer.classList.remove('open');
      overlay.classList.remove('open');
    } else {
      drawer.classList.add('open');
      overlay.classList.add('open');
    }
  };

  window.tabV22 = function (t) {
    document.querySelectorAll('.v22-tab').forEach((el, i) => {
      el.classList.toggle('active', ['perfiles', 'pasarela', 'pozo', 'calculadora'][i] === t);
    });
    document.getElementById('v22-tab-perfiles').style.display = t === 'perfiles' ? 'block' : 'none';
    document.getElementById('v22-tab-pasarela').style.display = t === 'pasarela' ? 'block' : 'none';
    document.getElementById('v22-tab-pozo').style.display = t === 'pozo' ? 'block' : 'none';
    document.getElementById('v22-tab-calculadora').style.display = t === 'calculadora' ? 'block' : 'none';
    if (t === 'calculadora') recalcularSplitPot();
  };

  // 3. Acciones de Perfil
  window.cambiarPerfilRapido = async function (tipo) {
    try {
      const r = await fetch('php/billetera_acciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'cambiar_perfil_demo', perfil: tipo })
      });
      const d = await r.json();
      if (d.ok) {
        mostrarToast(`✓ ${d.mensaje}`, 'success');
        setTimeout(() => location.reload(), 800);
      }
    } catch (e) {}
  };

  window.aplicarPerfilCustom = async function () {
    const nickname = document.getElementById('v22-cust-nom').value.trim();
    const saldo = parseFloat(document.getElementById('v22-cust-saldo').value) || 0;
    const nivel = parseInt(document.getElementById('v22-cust-nivel').value) || 0;

    if (!nickname) {
      mostrarToast('Ingresa un alias para el perfil', 'warn');
      return;
    }

    try {
      const r = await fetch('php/billetera_acciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'cambiar_perfil_demo', perfil: 'personalizado', nickname, saldo, nivel })
      });
      const d = await r.json();
      if (d.ok) {
        mostrarToast(`✓ Perfil ${nickname} configurado`, 'success');
        setTimeout(() => location.reload(), 800);
      }
    } catch (e) {}
  };

  // 4. Inyección de Bots
  window.inyectarBots = async function (cantidad) {
    const cod = document.getElementById('v22-bot-codigo').value.trim().toUpperCase() || (window.codigo || '');
    if (!cod) {
      mostrarToast('Ingresa el código de la sala de espera', 'warn');
      return;
    }

    try {
      const r = await fetch('php/billetera_acciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'simular_jugadores_sala', codigo: cod, cantidad })
      });
      const d = await r.json();
      if (d.ok) {
        mostrarToast(`✓ ${d.mensaje}`, 'success');
        const msg = document.getElementById('v22-bot-msg');
        msg.textContent = `Total jugadores: ${d.total_jugadores} | Pozo: $${d.pozo_total.toLocaleString()}`;
        msg.style.display = 'block';
        if (typeof poll === 'function') poll();
      } else {
        mostrarToast(d.error, 'error');
      }
    } catch (e) {}
  };

  // 5. Simulación de Webhooks
  window.dispararWebhookSimulado = async function () {
    const monto = parseFloat(document.getElementById('v22-wh-monto').value) || 50000;
    const evento = document.getElementById('v22-wh-evento').value;

    try {
      const r = await fetch('php/billetera_acciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'simular_webhook_pasarela', monto, evento })
      });
      const d = await r.json();
      if (d.ok) {
        mostrarToast(`⚡ Webhook ${evento} procesado (+$${monto.toLocaleString()} COP)`, 'success');
        const resBox = document.getElementById('v22-wh-res');
        resBox.style.display = 'block';
        resBox.innerHTML = `<strong>Firma SHA-256:</strong><br><span style="color:var(--yellow);font-family:monospace">${d.firma_sha256}</span><br><strong>Ref:</strong> ${d.referencia}`;
        setTimeout(() => {
          if (location.pathname.includes('wallet.php')) location.reload();
        }, 1500);
      }
    } catch (e) {}
  };

  // 6. Calculadora de Split Pot
  window.recalcularSplitPot = function () {
    const buyIn = parseFloat(document.getElementById('calc-buyin').value) || 0;
    const jugadores = parseInt(document.getElementById('calc-jugadores').value) || 1;
    const numGanadores = parseInt(document.getElementById('calc-ganadores').value) || 1;

    const totalBruto = buyIn * jugadores;
    const rake = Math.round(totalBruto * 0.10);
    const neto = totalBruto - rake;
    const porGanador = Math.floor(neto / numGanadores);

    document.getElementById('calc-res-bruto').textContent = '$ ' + totalBruto.toLocaleString();
    document.getElementById('calc-res-rake').textContent = '- $ ' + rake.toLocaleString();
    document.getElementById('calc-res-neto').textContent = '$ ' + neto.toLocaleString();
    document.getElementById('calc-res-por-ganador').textContent = '$ ' + porGanador.toLocaleString() + (numGanadores > 1 ? ` (×${numGanadores})` : '');
  };

  // 7. Modal de Checkout
  let metodoModalSel = 'PSE';
  let montoModalSel = 20000;

  window.abrirCheckoutModal = function (monto) {
    montoModalSel = monto || 20000;
    document.getElementById('v22-modal-monto').textContent = '$ ' + montoModalSel.toLocaleString() + ' COP';
    document.getElementById('v22-modal-ref').textContent = 'WOMPI-' + Math.random().toString(36).substring(2, 8).toUpperCase();
    document.getElementById('v22-checkout-overlay').style.display = 'flex';
  };

  window.cerrarCheckoutModal = function () {
    document.getElementById('v22-checkout-overlay').style.display = 'none';
  };

  window.selMetodoModal = function (el, m) {
    document.querySelectorAll('#v22-checkout-modal .pay-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    metodoModalSel = m;
  };

  window.confirmarPagoModal = async function () {
    const btn = document.getElementById('v22-btn-pagar-modal');
    btn.disabled = true;
    btn.textContent = 'Procesando pago seguro...';

    try {
      const r = await fetch('php/billetera_acciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'recargar', monto: montoModalSel, metodo: metodoModalSel })
      });
      const d = await r.json();
      if (d.ok) {
        cerrarCheckoutModal();
        mostrarToast(`✓ ¡Pago exitoso vía ${metodoModalSel}! +$${montoModalSel.toLocaleString()} acreditados.`, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.disabled = false;
        btn.textContent = 'Confirmar y Acreditar Fichas →';
        mostrarToast(d.error, 'error');
      }
    } catch (e) {
      btn.disabled = false;
      btn.textContent = 'Confirmar y Acreditar Fichas →';
    }
  };

  // 8. Sistema de Notificaciones Toast
  window.mostrarToast = function (mensaje, tipo) {
    const container = document.getElementById('v22-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `v22-toast ${tipo || 'info'}`;
    toast.innerHTML = `<span>${mensaje}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('fade-out');
      setTimeout(() => toast.remove(), 350);
    }, 3500);
  };

  // Auto-inicializar cuando cargue el DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inyectarInterfazV22);
  } else {
    inyectarInterfazV22();
  }
})();
