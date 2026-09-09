<?php
/**
 * billetera_acciones.php - Endpoints de Billetera, Pagos y Verificación KYC (V2.1)
 */
require_once __DIR__ . '/funciones.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true) ?? $_POST;
$accion = $req['accion'] ?? ($_GET['accion'] ?? '');

inicializarSesionUsuario();

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
        $monto  = floatval($req['monto'] ?? 0);
        $metodo = sanitizar($req['metodo'] ?? 'PSE');
        if ($monto < 1000) {
            jsonError('El monto mínimo de recarga es de 1.000 fichas / COP');
        }
        $ref = 'WOMPI-' . strtoupper(bin2hex(random_bytes(4)));
        $_SESSION['billetera']['saldo_disponible'] += $monto;
        registrarTransaccion(
            'RECARGA',
            $monto,
            $_SESSION['billetera']['saldo_disponible'],
            "Recarga exitosa vía {$metodo}",
            $ref
        );
        jsonOk([
            'mensaje' => '¡Recarga aprobada y acreditada con éxito!',
            'referencia' => $ref,
            'billetera' => $_SESSION['billetera']
        ]);
        break;

    case 'solicitar_retiro':
        $nivelKyc = intval($_SESSION['kyc']['nivel'] ?? 0);
        if ($nivelKyc < 2) {
            jsonError('Para solicitar retiros debes completar la Verificación KYC Nivel 2 (Documento y Certificación Bancaria).');
        }
        $monto = floatval($req['monto'] ?? 0);
        if ($monto <= 0) {
            jsonError('Monto de retiro inválido.');
        }
        if ($monto > $_SESSION['billetera']['saldo_disponible']) {
            jsonError('Saldo insuficiente para realizar este retiro.');
        }

        $banco      = sanitizar($req['banco'] ?? 'Bancolombia');
        $tipoCuenta = sanitizar($req['tipo_cuenta'] ?? 'Ahorros');
        $numCuenta  = sanitizar($req['num_cuenta'] ?? '');
        $titular    = sanitizar($req['titular'] ?? $_SESSION['usuario']['nickname']);

        if (empty($numCuenta)) {
            jsonError('Debes ingresar tu número de cuenta bancaria.');
        }

        $_SESSION['billetera']['saldo_disponible'] -= $monto;
        $ref = 'RET-' . strtoupper(bin2hex(random_bytes(4)));

        $solicitud = [
            'id' => count($_SESSION['solicitudes_retiro']) + 1,
            'referencia' => $ref,
            'monto' => $monto,
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
            -$monto,
            $_SESSION['billetera']['saldo_disponible'],
            "Solicitud de retiro a {$banco} ({$tipoCuenta})",
            $ref
        );

        jsonOk([
            'mensaje' => 'Solicitud de retiro recibida. Será procesada en un plazo de 24 a 48 horas.',
            'solicitud' => $solicitud,
            'billetera' => $_SESSION['billetera']
        ]);
        break;

    case 'enviar_otp':
        $email = sanitizar($req['email'] ?? $_SESSION['usuario']['email']);
        $otp = (string)rand(100000, 999999);
        $_SESSION['kyc']['otp_enviado'] = $otp;
        $_SESSION['usuario']['email'] = $email;

        jsonOk([
            'mensaje' => "Código OTP de verificación enviado a {$email}.",
            'otp_demo' => $otp // Se entrega para facilitar pruebas interactivas
        ]);
        break;

    case 'validar_otp':
        $otp = trim($req['otp'] ?? '');
        $guardado = $_SESSION['kyc']['otp_enviado'] ?? '123456';
        if ($otp !== $guardado && $otp !== '123456') {
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
        $_SESSION['usuario']['documento']  = "{$tipoDoc} {$numDoc}";

        jsonOk([
            'mensaje' => '¡Documentación verificada exitosamente! Has ascendido a KYC Nivel 2 (Habilitado para retiros).',
            'kyc' => $_SESSION['kyc']
        ]);
        break;

    default:
        jsonError('Acción no reconocida.');
}
