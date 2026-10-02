<?php
/**
 * billetera_acciones.php - Endpoints de Billetera, Pagos, Verificación KYC y Simulador V2.2
 */
require_once __DIR__ . '/funciones.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true) ?? $_POST;
$accion = $req['accion'] ?? ($_GET['accion'] ?? '');

inicializarSesionConBD();

switch ($accion) {
    case 'estado':
        jsonOk([
            'usuario'   => $_SESSION['usuario'],
            'billetera' => $_SESSION['billetera'],
            'kyc'       => $_SESSION['kyc'],
            'retiros'   => $_SESSION['solicitudes_retiro']
        ]);
        break;

    case 'recargar':
        $montoCop = floatval($req['monto'] ?? 0);
        $metodo = sanitizar($req['metodo'] ?? 'PSE');
        if ($montoCop < 10000) {
            jsonError('El monto mínimo de recarga es de $10.000 COP (1 ficha)');
        }
        $fichas = intval($montoCop / VALOR_FICHA);
        if ($fichas < 1) {
            jsonError('El monto mínimo de recarga es de $10.000 COP (1 ficha)');
        }
        $ref = 'WOMPI-' . strtoupper(bin2hex(random_bytes(4)));
        $_SESSION['billetera']['saldo_disponible'] += $fichas;
        registrarTransaccion(
            'RECARGA',
            $fichas,
            $_SESSION['billetera']['saldo_disponible'],
            "Recarga exitosa vía {$metodo} ($fichas fichas = $" . number_format($montoCop) . " COP)",
            $ref
        );
        
        // Sincronizar con BD si el usuario está logueado
        if (isset($_SESSION['usuario_id'])) {
            require_once __DIR__ . '/../database/db.php';
            global $pdo;
            $usuarioId = (int)$_SESSION['usuario_id'];
            
            $stmt = $pdo->prepare('SELECT id, saldo_disponible FROM billeteras WHERE usuario_id = ? FOR UPDATE');
            $stmt->execute([$usuarioId]);
            $billetera = $stmt->fetch();
            
            if ($billetera) {
                $nuevoSaldo = floatval($billetera['saldo_disponible']) + $fichas;
                $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = ? WHERE id = ?');
                $stmt->execute([$nuevoSaldo, $billetera['id']]);
                
                $stmt = $pdo->prepare('INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$billetera['id'], 'RECARGA', $fichas, $nuevoSaldo, "Recarga vía {$metodo}", $ref]);
            }
        }
        
        jsonOk([
            'mensaje' => "¡Recarga aprobada! Se acreditaron {$fichas} fichas ($" . number_format($montoCop) . " COP)",
            'referencia' => $ref,
            'fichas_acreditadas' => $fichas,
            'billetera' => $_SESSION['billetera']
        ]);
        break;

    case 'solicitar_retiro':
        $nivelKyc = intval($_SESSION['kyc']['nivel'] ?? 0);
        if ($nivelKyc < 2) {
            jsonError('Para solicitar retiros debes completar la Verificación de Documento de Identidad.');
        }
        $fichas = intval($req['fichas'] ?? $req['monto'] ?? 0);
        if ($fichas <= 0) {
            jsonError('Cantidad de fichas inválida.');
        }
        if ($fichas > $_SESSION['billetera']['saldo_disponible']) {
            jsonError('Saldo insuficiente para realizar este retiro.');
        }

        $montoCop = $fichas * VALOR_FICHA;
        $banco      = sanitizar($req['banco'] ?? 'Bancolombia');
        $tipoCuenta = sanitizar($req['tipo_cuenta'] ?? 'Ahorros');
        $numCuenta  = sanitizar($req['num_cuenta'] ?? '');
        $titular    = sanitizar($req['titular'] ?? $_SESSION['usuario']['nickname']);

        if (empty($numCuenta)) {
            jsonError('Debes ingresar tu número de cuenta bancaria.');
        }

        $_SESSION['billetera']['saldo_disponible'] -= $fichas;
        $ref = 'RET-' . strtoupper(bin2hex(random_bytes(4)));

        $solicitud = [
            'id' => count($_SESSION['solicitudes_retiro']) + 1,
            'referencia' => $ref,
            'fichas' => $fichas,
            'monto_cop' => $montoCop,
            'banco' => $banco,
            'tipo_cuenta' => $tipoCuenta,
            'num_cuenta' => substr($numCuenta, -4),
            'titular' => $titular,
            'estado' => 'PENDIENTE',
            'fecha' => date('Y-m-d H:i:s')
        ];
        array_unshift($_SESSION['solicitudes_retiro'], $solicitud);

        registrarTransaccion(
            'RETIRO',
            -$fichas,
            $_SESSION['billetera']['saldo_disponible'],
            "Solicitud de retiro a {$banco} ({$tipoCuenta}) - {$fichas} fichas ($" . number_format($montoCop) . " COP)",
            $ref
        );
        
        // Sincronizar con BD si el usuario está logueado
        if (isset($_SESSION['usuario_id'])) {
            require_once __DIR__ . '/../database/db.php';
            global $pdo;
            $usuarioId = (int)$_SESSION['usuario_id'];
            
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare('SELECT id, saldo_disponible FROM billeteras WHERE usuario_id = ? FOR UPDATE');
                $stmt->execute([$usuarioId]);
                $billetera = $stmt->fetch();
                
                if ($billetera) {
                    $nuevoSaldo = floatval($billetera['saldo_disponible']) - $fichas;
                    
                    $stmt = $pdo->prepare('UPDATE billeteras SET saldo_disponible = ? WHERE id = ?');
                    $stmt->execute([$nuevoSaldo, $billetera['id']]);
                    
                    $stmt = $pdo->prepare('INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$billetera['id'], 'RETIRO', -$fichas, $nuevoSaldo, "Retiro a {$banco}", $ref]);
                    
                    $stmt = $pdo->prepare('INSERT INTO solicitudes_retiro (usuario_id, fichas, monto_cop, banco, tipo_cuenta, num_cuenta, titular, estado, referencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$usuarioId, $fichas, $montoCop, $banco, $tipoCuenta, $numCuenta, $titular, 'PENDIENTE', $ref]);
                }
                
                $pdo->commit();
            } catch (\PDOException $e) {
                $pdo->rollBack();
                error_log('[RETIRO BD] Error: ' . $e->getMessage());
            }
        }

        jsonOk([
            'mensaje' => "Solicitud de retiro recibida por {$fichas} fichas ($" . number_format($montoCop) . " COP). Será procesada en 24-48 horas.",
            'solicitud' => $solicitud,
            'billetera' => $_SESSION['billetera']
        ]);
        break;

    case 'enviar_otp':
        $email = sanitizar($req['email'] ?? $_SESSION['usuario']['email']);
        $otp = (string)random_int(100000, 999999); // Criptográficamente seguro
        $_SESSION['kyc']['otp_enviado'] = $otp;
        $_SESSION['usuario']['email'] = $email;

        // En producción, enviar email real con PHPMailer o similar
        // Por ahora, mostramos el OTP en desarrollo
        $isDev = ($_ENV['APP_ENV'] ?? 'local') === 'local';
        jsonOk([
            'mensaje' => "Código OTP de verificación enviado a {$email}.",
            'otp_demo' => $isDev ? $otp : null
        ]);
        break;

    case 'validar_otp':
        $otp = trim($req['otp'] ?? '');
        $guardado = $_SESSION['kyc']['otp_enviado'] ?? null;
        if (!$guardado || $otp !== $guardado) {
            jsonError('Código OTP inválido o expirado.');
        }
        $_SESSION['kyc']['nivel'] = max(1, $_SESSION['kyc']['nivel'] ?? 1);
        $_SESSION['usuario']['nivel_kyc'] = $_SESSION['kyc']['nivel'];
        jsonOk([
            'mensaje' => '¡Identidad confirmada! Has ascendido a KYC Nivel 1 (Habilitado para Payplay).',
            'kyc' => $_SESSION['kyc']
        ]);
        break;

    case 'subir_kyc':
        $tipoDoc = sanitizar($req['tipo_doc'] ?? 'CC');
        $numDoc  = sanitizar($req['num_doc'] ?? '');
        if (empty($numDoc)) {
            jsonError('Por favor ingresa tu número de documento de identidad.');
        }
        $_SESSION['kyc']['documento_tipo'] = $tipoDoc;
        $_SESSION['kyc']['documento_num']  = $numDoc;
        $_SESSION['kyc']['estado_doc']     = 'APROBADO';
        $_SESSION['kyc']['nivel']          = 2;
        $_SESSION['usuario']['nivel_kyc']  = 2;

        // Persistir la verificación en la base de datos
        if (isset($_SESSION['usuario_id'])) {
            try {
                require_once __DIR__ . '/../database/db.php';
                global $pdo;
                $uid = intval($_SESSION['usuario_id']);
                $stmtKyc = $pdo->prepare(
                    "UPDATE verificaciones_kyc SET estado = 'APROBADO', nivel = 2, documento_tipo = ?, documento_num = ? WHERE usuario_id = ?"
                );
                $stmtKyc->execute([$tipoDoc, $numDoc, $uid]);
                // Si no existía registro KYC, crearlo
                if ($stmtKyc->rowCount() === 0) {
                    $stmtIns = $pdo->prepare(
                        "INSERT INTO verificaciones_kyc (usuario_id, estado, nivel, documento_tipo, documento_num) VALUES (?, 'APROBADO', 2, ?, ?)"
                    );
                    $stmtIns->execute([$uid, $tipoDoc, $numDoc]);
                }
                $stmtU = $pdo->prepare("UPDATE usuarios SET nivel_verificacion = 2 WHERE id = ?");
                $stmtU->execute([$uid]);
            } catch (\PDOException $e) {
                error_log('[KYC] Error persistiendo verificación: ' . $e->getMessage());
            }
        }
        $_SESSION['usuario']['documento']  = "{$tipoDoc} {$numDoc}";

        jsonOk([
            'mensaje' => '¡Documentación verificada exitosamente! Ya puedes solicitar retiros.',
            'kyc' => $_SESSION['kyc']
        ]);
        break;

    // ── V2.2: ACCIONES DE SIMULACIÓN Y PANEL INTERACTIVO ─────────────

    case 'cambiar_perfil_demo':
        $tipoPerfil = $req['perfil'] ?? 'nivel_1';
        switch ($tipoPerfil) {
            case 'nivel_0':
                $_SESSION['usuario']['nickname'] = 'Carlos (Básico)';
                $_SESSION['usuario']['email'] = 'carlos.basico@bingo.local';
                $_SESSION['usuario']['nivel_kyc'] = 0;
                $_SESSION['kyc']['nivel'] = 0;
                $_SESSION['kyc']['estado_doc'] = 'PENDIENTE';
                $_SESSION['billetera']['saldo_disponible'] = 0;
                $_SESSION['billetera']['saldo_retenido'] = 0;
                break;

            case 'nivel_1':
                $_SESSION['usuario']['nickname'] = 'María (Jugadora)';
                $_SESSION['usuario']['email'] = 'maria.jugadora@bingo.local';
                $_SESSION['usuario']['nivel_kyc'] = 1;
                $_SESSION['kyc']['nivel'] = 1;
                $_SESSION['kyc']['estado_doc'] = 'PENDIENTE';
                $_SESSION['billetera']['saldo_disponible'] = 25;
                $_SESSION['billetera']['saldo_retenido'] = 0;
                break;

            case 'nivel_2':
                $_SESSION['usuario']['nickname'] = 'Andrés (VIP)';
                $_SESSION['usuario']['email'] = 'andres.vip@bingo.local';
                $_SESSION['usuario']['nivel_kyc'] = 2;
                $_SESSION['usuario']['documento'] = 'CC 1020304050';
                $_SESSION['kyc']['nivel'] = 2;
                $_SESSION['kyc']['estado_doc'] = 'APROBADO';
                $_SESSION['billetera']['saldo_disponible'] = 150;
                $_SESSION['billetera']['saldo_retenido'] = 0;
                break;

            case 'personalizado':
                $nombre = sanitizar($req['nickname'] ?? 'Jugador');
                $saldo  = intval($req['saldo'] ?? 10);
                $nivel  = intval($req['nivel'] ?? 0); // Nivel 0 por defecto (consistente con registro)
                $_SESSION['usuario']['nickname'] = $nombre;
                $_SESSION['usuario']['nivel_kyc'] = $nivel;
                $_SESSION['kyc']['nivel'] = $nivel;
                $_SESSION['billetera']['saldo_disponible'] = $saldo;
                break;
        }

        jsonOk([
            'mensaje'   => "Perfil cambiado exitosamente a {$_SESSION['usuario']['nickname']}",
            'usuario'   => $_SESSION['usuario'],
            'billetera' => $_SESSION['billetera'],
            'kyc'       => $_SESSION['kyc']
        ]);
        break;

    case 'simular_jugadores_sala':
        $codigoSala = strtoupper(trim($req['codigo'] ?? ''));
        $cantidad   = intval($req['cantidad'] ?? 3);
        if (!$codigoSala) jsonError('Código de sala requerido');

        $sala = cargarSala($codigoSala);
        if (!$sala) jsonError('Sala no encontrada');
        if ($sala['estado'] !== 'esperando') jsonError('Solo se pueden añadir jugadores en sala de espera');

        $nombresBots = ['Camila', 'Felipe', 'Valentina', 'Santiago', 'Daniela', 'Mateo', 'Lucía', 'Sebastián'];
        $buyIn = floatval($sala['buy_in'] ?? 0);

        for ($i = 0; $i < $cantidad; $i++) {
            if (count($sala['jugadores']) >= 20) break;
            $nombreBot = $nombresBots[array_rand($nombresBots)] . ' (Sim)';
            $botId     = 'bot_' . bin2hex(random_bytes(4));
            $sala['jugadores'][] = ['id' => $botId, 'nombre' => $nombreBot, 'buy_in_pagado' => $buyIn];
            if ($sala['tipo_sala'] === 'payplay') {
                $sala['pozo_total'] = ($sala['pozo_total'] ?? 0) + $buyIn;
            }
        }

        guardarSala($sala);
        jsonOk([
            'mensaje'    => "Se añadieron {$cantidad} jugadores simulados a la sala {$codigoSala}",
            'total_jugadores' => count($sala['jugadores']),
            'pozo_total' => $sala['pozo_total'] ?? 0
        ]);
        break;

    case 'simular_webhook_pasarela':
        $montoCop  = floatval($req['monto'] ?? 50000);
        $fichas    = intval($montoCop / VALOR_FICHA);
        $evento    = $req['evento'] ?? 'APPROVED';
        $referencia = 'SIM-WH-' . strtoupper(bin2hex(random_bytes(4)));
        $secretKey  = $_ENV['WEBHOOK_SECRET_KEY'] ?? 'sec_events_bingo_prod_test';
        
        // Reconstrucción de Hash SHA-256 (RF-15)
        $firmaGenerada = hash('sha256', "{$referencia}{$montoCop}COP{$secretKey}");

        if ($evento === 'APPROVED') {
            $_SESSION['billetera']['saldo_disponible'] += $fichas;
            registrarTransaccion('RECARGA', $fichas, $_SESSION['billetera']['saldo_disponible'], "Simulación Webhook Aprobado ({$fichas} fichas = $" . number_format($montoCop) . " COP)", $referencia);
        }

        jsonOk([
            'mensaje'        => "Webhook {$evento} procesado con éxito",
            'referencia'     => $referencia,
            'firma_sha256'   => $firmaGenerada,
            'fichas'         => $fichas,
            'monto_cop'      => $montoCop,
            'evento'         => $evento,
            'saldo_actual'   => $_SESSION['billetera']['saldo_disponible']
        ]);
        break;

    default:
        jsonError('Acción no reconocida.');
}
