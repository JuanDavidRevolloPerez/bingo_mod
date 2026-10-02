-- ============================================
-- Bingo Online V2.2 - Esquema de Base de Datos
-- Motor: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4
-- ============================================

CREATE DATABASE IF NOT EXISTS bingo_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bingo_db;

-- ============================================
-- Tabla: usuarios
-- Almacena los usuarios registrados en la plataforma
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nickname            VARCHAR(30)  NOT NULL,
    email               VARCHAR(255) NOT NULL,
    password_hash       VARCHAR(255) NULL,           -- NULL si es cuenta Google
    google_id           VARCHAR(255) NULL,           -- ID de Google OAuth
    nivel_verificacion  TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0, 1, 2
    rol                 ENUM('JUGADOR', 'ADMIN', 'MODERADOR') NOT NULL DEFAULT 'JUGADOR',
    estado              ENUM('ACTIVO', 'SUSPENDIDO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_usuarios_email UNIQUE (email),
    CONSTRAINT uq_usuarios_nickname UNIQUE (nickname),
    CONSTRAINT uq_usuarios_google_id UNIQUE (google_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: billeteras
-- Saldo de fichas de cada usuario
-- ============================================
CREATE TABLE IF NOT EXISTS billeteras (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    saldo_disponible    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo_retenido      DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- Escrow
    actualizado_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_billeteras_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT uq_billeteras_usuario UNIQUE (usuario_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: verificaciones_kyc
-- Nivel de verificación de identidad del usuario
-- ============================================
CREATE TABLE IF NOT EXISTS verificaciones_kyc (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    estado              ENUM('PENDIENTE', 'EN_REVISION', 'APROBADO', 'RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    nivel               TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0, 1, 2
    documento_tipo      ENUM('CC', 'CE', 'TI', 'NIT') NULL,
    documento_num      VARCHAR(20) NULL,
    otp_enviado         VARCHAR(6) NULL,
    otp_verificado_at   TIMESTAMP NULL,
    actualizado_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_kyc_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT uq_kyc_usuario UNIQUE (usuario_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: transacciones
-- Historial de movimientos de la billetera
-- ============================================
CREATE TABLE IF NOT EXISTS transacciones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    billetera_id        INT UNSIGNED NOT NULL,
    tipo                ENUM('RECARGA', 'BUY_IN', 'REEMBOLSO', 'PREMIO', 'RETIRO', 'BONO') NOT NULL,
    monto               DECIMAL(12,2) NOT NULL,     -- Positivo = crédito, Negativo = débito
    saldo_resultante    DECIMAL(12,2) NOT NULL,
    descripcion         TEXT NULL,
    referencia          VARCHAR(50) NULL,
    fecha               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_transacciones_billetera FOREIGN KEY (billetera_id)
        REFERENCES billeteras(id) ON DELETE CASCADE,
    INDEX idx_transacciones_fecha (fecha),
    INDEX idx_transacciones_tipo (tipo)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: salas
-- Salas de bingo creadas por los usuarios
-- ============================================
CREATE TABLE IF NOT EXISTS salas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(4) NOT NULL,        -- Código único de 4 caracteres
    nombre              VARCHAR(30) NOT NULL,
    tipo_sala           ENUM('free', 'payplay') NOT NULL DEFAULT 'free',
    buy_in              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    comision_pct        DECIMAL(5,2) NOT NULL DEFAULT 10.00, -- 10%
    pozo_total          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado              ENUM('esperando', 'jugando', 'finalizado', 'cancelado') NOT NULL DEFAULT 'esperando',
    host_id             INT UNSIGNED NOT NULL,       -- Usuario que creó la sala
    modo                ENUM('normal', 'reducido') NOT NULL DEFAULT 'normal',
    intervalo_fichas    TINYINT UNSIGNED NOT NULL DEFAULT 10, -- Segundos entre fichas
    creado_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    iniciado_at         TIMESTAMP NULL,
    finalizado_at       TIMESTAMP NULL,

    CONSTRAINT fk_salas_host FOREIGN KEY (host_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT uq_salas_codigo UNIQUE (codigo),
    INDEX idx_salas_estado (estado),
    INDEX idx_salas_creado_at (creado_at)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: jugadores_sala
-- Relación N:M entre usuarios y salas
-- ============================================
CREATE TABLE IF NOT EXISTS jugadores_sala (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sala_id             INT UNSIGNED NOT NULL,
    usuario_id          INT UNSIGNED NOT NULL,
    buy_in_pagado       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    posicion            TINYINT UNSIGNED NOT NULL,  -- Orden de llegada
    es_bot              BOOLEAN NOT NULL DEFAULT FALSE, -- Jugador simulado
    joined_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_jugadores_sala FOREIGN KEY (sala_id)
        REFERENCES salas(id) ON DELETE CASCADE,
    CONSTRAINT fk_jugadores_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT uq_jugadores_sala UNIQUE (sala_id, usuario_id),
    INDEX idx_jugadores_usuario (usuario_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: cartones
-- Cartones de bingo de cada jugador en cada sala
-- ============================================
CREATE TABLE IF NOT EXISTS cartones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jugador_sala_id     INT UNSIGNED NOT NULL,
    numero_carton       TINYINT UNSIGNED NOT NULL DEFAULT 1, -- Número de cartón (1, 2, 3...)
    grid                JSON NOT NULL,               -- Matriz 5x5 [["A01","B02",...],...]
    marcadas            JSON NOT NULL,               -- Array de fichas marcadas
    es_ganador          BOOLEAN NOT NULL DEFAULT FALSE,
    creado_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_cartones_jugador FOREIGN KEY (jugador_sala_id)
        REFERENCES jugadores_sala(id) ON DELETE CASCADE,
    INDEX idx_cartones_jugador (jugador_sala_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: historial_fichas
-- Secuencia de fichas cantadas en cada sala
-- ============================================
CREATE TABLE IF NOT EXISTS historial_fichas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sala_id             INT UNSIGNED NOT NULL,
    ficha               VARCHAR(3) NOT NULL,         -- A01, B02, C03, etc.
    cantada_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historial_sala FOREIGN KEY (sala_id)
        REFERENCES salas(id) ON DELETE CASCADE,
    INDEX idx_historial_sala (sala_id),
    INDEX idx_historial_ficha (ficha)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: solicitudes_retiro
-- Retiros bancarios solicitados por usuarios
-- ============================================
CREATE TABLE IF NOT EXISTS solicitudes_retiro (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    fichas              DECIMAL(12,2) NOT NULL,
    monto_cop           DECIMAL(12,2) NOT NULL,
    banco               VARCHAR(50) NOT NULL,
    tipo_cuenta         ENUM('Ahorros', 'Corriente') NOT NULL DEFAULT 'Ahorros',
    num_cuenta          VARCHAR(20) NOT NULL,
    titular              VARCHAR(100) NOT NULL,
    estado              ENUM('PENDIENTE', 'PROCESANDO', 'APROBADO', 'RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    referencia          VARCHAR(50) NULL,
    procesado_por       INT UNSIGNED NULL,           -- Admin que procesó
    procesado_at        TIMESTAMP NULL,
    fecha               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_retiros_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_retiros_procesado FOREIGN KEY (procesado_por)
        REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_retiros_estado (estado),
    INDEX idx_retiros_fecha (fecha)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: ganadores
-- Ganador de cada sala con su premio
-- ============================================
CREATE TABLE IF NOT EXISTS ganadores (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sala_id             INT UNSIGNED NOT NULL,
    usuario_id          INT UNSIGNED NOT NULL,
    carton_id           INT UNSIGNED NULL,           -- Cartón ganador
    premio_ganado       DECIMAL(12,2) NOT NULL,
    fecha               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ganadores_sala FOREIGN KEY (sala_id)
        REFERENCES salas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ganadores_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ganadores_carton FOREIGN KEY (carton_id)
        REFERENCES cartones(id) ON DELETE SET NULL,
    CONSTRAINT uq_ganadores_sala UNIQUE (sala_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: sesiones
-- Sesiones activas de usuarios (opcional, para JWT o tokens)
-- ============================================
CREATE TABLE IF NOT EXISTS sesiones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    token               VARCHAR(255) NOT NULL,
    ip_address          VARCHAR(45) NULL,
    user_agent          VARCHAR(255) NULL,
    expira_at           TIMESTAMP NOT NULL,
    creado_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_sesiones_token (token),
    INDEX idx_sesiones_expira (expira_at)
) ENGINE=InnoDB;

-- ============================================
-- Tabla: configuracion
-- Configuración general del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS configuracion (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave               VARCHAR(50) NOT NULL,
    valor               TEXT NULL,
    descripcion         VARCHAR(255) NULL,
    actualizado_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_configuracion_clave UNIQUE (clave)
) ENGINE=InnoDB;

-- ============================================
-- Datos iniciales de configuración
-- ============================================
INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('valor_ficha', '10000', 'Valor de 1 ficha en COP'),
    ('comision_default', '10', 'Comisión por defecto en salas Payplay (%)'),
    ('buy_in_minimo', '1', 'Buy-in mínimo en fichas para salas Payplay'),
    ('max_jugadores_sala', '20', 'Máximo de jugadores por sala'),
    ('intervalo_default', '10', 'Intervalo entre fichas en segundos'),
    ('bono_bienvenida', '0', 'Fichas de bienvenida para nuevos usuarios');

-- ============================================
-- Vistas útiles
-- ============================================

-- Vista: Resumen de billetera con datos del usuario
CREATE OR REPLACE VIEW v_resumen_billeteras AS
SELECT
    u.id AS usuario_id,
    u.nickname,
    u.email,
    u.nivel_verificacion,
    b.saldo_disponible,
    b.saldo_retenido,
    (b.saldo_disponible + b.saldo_retenido) AS saldo_total
FROM usuarios u
JOIN billeteras b ON u.id = b.usuario_id;

-- Vista: Salas activas con conteo de jugadores
CREATE OR REPLACE VIEW v_salas_activas AS
SELECT
    s.id,
    s.codigo,
    s.nombre,
    s.tipo_sala,
    s.buy_in,
    s.pozo_total,
    s.estado,
    s.modo,
    u.nickname AS host_nombre,
    COUNT(js.id) AS num_jugadores,
    s.creado_at
FROM salas s
JOIN usuarios u ON s.host_id = u.id
LEFT JOIN jugadores_sala js ON s.id = js.sala_id
WHERE s.estado IN ('esperando', 'jugando')
GROUP BY s.id;

-- Vista: Historial de transacciones con datos del usuario
CREATE OR REPLACE VIEW v_transacciones_usuarios AS
SELECT
    t.id,
    t.tipo,
    t.monto,
    t.saldo_resultante,
    t.descripcion,
    t.referencia,
    t.fecha,
    u.id AS usuario_id,
    u.nickname
FROM transacciones t
JOIN billeteras b ON t.billetera_id = b.id
JOIN usuarios u ON b.usuario_id = u.id;

-- ============================================
-- Procedimientos almacenados útiles
-- ============================================

DELIMITER //

-- Procedimiento: Crear usuario con billetera y KYC automáticamente
CREATE PROCEDURE sp_crear_usuario(
    IN p_nickname VARCHAR(30),
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_google_id VARCHAR(255)
)
BEGIN
    DECLARE v_usuario_id INT UNSIGNED;
    DECLARE v_bono DECIMAL(12,2);

    -- Obtener bono de bienvenida
    SELECT CAST(valor AS DECIMAL(12,2)) INTO v_bono
    FROM configuracion WHERE clave = 'bono_bienvenida';

    IF v_bono IS NULL THEN
        SET v_bono = 5.00;
    END IF;

    START TRANSACTION;

    -- Insertar usuario
    INSERT INTO usuarios (nickname, email, password_hash, google_id, nivel_verificacion, rol, estado)
    VALUES (p_nickname, p_email, p_password_hash, p_google_id, 0, 'JUGADOR', 'ACTIVO');

    SET v_usuario_id = LAST_INSERT_ID();

    -- Crear billetera con bono de bienvenida
    INSERT INTO billeteras (usuario_id, saldo_disponible, saldo_retenido)
    VALUES (v_usuario_id, v_bono, 0.00);

    -- Crear registro KYC pendiente
    INSERT INTO verificaciones_kyc (usuario_id, estado, nivel)
    VALUES (v_usuario_id, 'PENDIENTE', 0);

    -- Registrar transacción de bono
    INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
    VALUES (
        (SELECT id FROM billeteras WHERE usuario_id = v_usuario_id),
        'BONO',
        v_bono,
        v_bono,
        'Bono de bienvenida plataforma V2.2',
        CONCAT('BONO-WELCOME-', LPAD(v_usuario_id, 6, '0'))
    );

    COMMIT;

    SELECT v_usuario_id AS usuario_id;
END //

-- Procedimiento: Recargar billetera
CREATE PROCEDURE sp_recargar_billetera(
    IN p_usuario_id INT UNSIGNED,
    IN p_monto DECIMAL(12,2),
    IN p_metodo VARCHAR(50),
    IN p_referencia VARCHAR(50)
)
BEGIN
    DECLARE v_billetera_id INT UNSIGNED;
    DECLARE v_saldo_actual DECIMAL(12,2);

    SELECT id, saldo_disponible INTO v_billetera_id, v_saldo_actual
    FROM billeteras WHERE usuario_id = p_usuario_id FOR UPDATE;

    UPDATE billeteras
    SET saldo_disponible = saldo_disponible + p_monto
    WHERE id = v_billetera_id;

    INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
    VALUES (
        v_billetera_id,
        'RECARGA',
        p_monto,
        v_saldo_actual + p_monto,
        CONCAT('Recarga vía ', p_metodo),
        p_referencia
    );
END //

-- Procedimiento: Retener saldo en escrow (buy-in)
CREATE PROCEDURE sp_retener_escrow(
    IN p_usuario_id INT UNSIGNED,
    IN p_monto DECIMAL(12,2),
    IN p_sala_codigo VARCHAR(4)
)
BEGIN
    DECLARE v_billetera_id INT UNSIGNED;
    DECLARE v_saldo_actual DECIMAL(12,2);

    SELECT id, saldo_disponible INTO v_billetera_id, v_saldo_actual
    FROM billeteras WHERE usuario_id = p_usuario_id FOR UPDATE;

    IF v_saldo_actual < p_monto THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Saldo insuficiente';
    END IF;

    UPDATE billeteras
    SET saldo_disponible = saldo_disponible - p_monto,
        saldo_retenido = saldo_retenido + p_monto
    WHERE id = v_billetera_id;

    INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
    VALUES (
        v_billetera_id,
        'BUY_IN',
        -p_monto,
        v_saldo_actual - p_monto,
        CONCAT('Retención Escrow Buy-in Sala ', p_sala_codigo),
        CONCAT('BUYIN-', p_sala_codigo, '-', UNIX_TIMESTAMP())
    );
END //

-- Procedimiento: Acreditar premio a ganador
CREATE PROCEDURE sp_acreditar_premio(
    IN p_usuario_id INT UNSIGNED,
    IN p_monto DECIMAL(12,2),
    IN p_sala_codigo VARCHAR(4)
)
BEGIN
    DECLARE v_billetera_id INT UNSIGNED;
    DECLARE v_saldo_actual DECIMAL(12,2);

    SELECT id, saldo_disponible INTO v_billetera_id, v_saldo_actual
    FROM billeteras WHERE usuario_id = p_usuario_id FOR UPDATE;

    UPDATE billeteras
    SET saldo_disponible = saldo_disponible + p_monto
    WHERE id = v_billetera_id;

    INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
    VALUES (
        v_billetera_id,
        'PREMIO',
        p_monto,
        v_saldo_actual + p_monto,
        CONCAT('Premio Pozo Neto Bingo Sala ', p_sala_codigo),
        CONCAT('PREMIO-', p_sala_codigo, '-', UNIX_TIMESTAMP())
    );
END //

-- Procedimiento: Solicitar retiro
CREATE PROCEDURE sp_solicitar_retiro(
    IN p_usuario_id INT UNSIGNED,
    IN p_fichas DECIMAL(12,2),
    IN p_banco VARCHAR(50),
    IN p_tipo_cuenta VARCHAR(20),
    IN p_num_cuenta VARCHAR(20),
    IN p_titular VARCHAR(100)
)
BEGIN
    DECLARE v_billetera_id INT UNSIGNED;
    DECLARE v_saldo_actual DECIMAL(12,2);
    DECLARE v_monto_cop DECIMAL(12,2);
    DECLARE v_valor_ficha DECIMAL(12,2);
    DECLARE v_nivel_kyc TINYINT UNSIGNED;

    -- Verificar nivel KYC
    SELECT nivel INTO v_nivel_kyc
    FROM verificaciones_kyc WHERE usuario_id = p_usuario_id;

    IF v_nivel_kyc < 2 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Se requiere KYC Nivel 2 para retiros';
    END IF;

    -- Obtener valor ficha
    SELECT CAST(valor AS DECIMAL(12,2)) INTO v_valor_ficha
    FROM configuracion WHERE clave = 'valor_ficha';

    IF v_valor_ficha IS NULL THEN
        SET v_valor_ficha = 10000.00;
    END IF;

    SET v_monto_cop = p_fichas * v_valor_ficha;

    SELECT id, saldo_disponible INTO v_billetera_id, v_saldo_actual
    FROM billeteras WHERE usuario_id = p_usuario_id FOR UPDATE;

    IF v_saldo_actual < p_fichas THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Saldo insuficiente';
    END IF;

    START TRANSACTION;

    UPDATE billeteras
    SET saldo_disponible = saldo_disponible - p_fichas
    WHERE id = v_billetera_id;

    INSERT INTO solicitudes_retiro (usuario_id, fichas, monto_cop, banco, tipo_cuenta, num_cuenta, titular, estado, referencia)
    VALUES (p_usuario_id, p_fichas, v_monto_cop, p_banco, p_tipo_cuenta, p_num_cuenta, p_titular, 'PENDIENTE', CONCAT('RET-', UPPER(SUBSTR(MD5(RAND()), 1, 8))));

    INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
    VALUES (
        v_billetera_id,
        'RETIRO',
        -p_fichas,
        v_saldo_actual - p_fichas,
        CONCAT('Solicitud de retiro a ', p_banco),
        CONCAT('RET-', UPPER(SUBSTR(MD5(RAND()), 1, 8)))
    );

    COMMIT;
END //

DELIMITER ;

-- ============================================
-- Triggers útiles
-- ============================================

DELIMITER //

-- Trigger: Actualizar pozo_total de la sala cuando se une un jugador
CREATE TRIGGER trg_jugador_sala_insert
AFTER INSERT ON jugadores_sala
FOR EACH ROW
BEGIN
    DECLARE v_buy_in DECIMAL(12,2);
    DECLARE v_tipo_sala VARCHAR(10);

    SELECT buy_in, tipo_sala INTO v_buy_in, v_tipo_sala
    FROM salas WHERE id = NEW.sala_id;

    IF v_tipo_sala = 'payplay' THEN
        UPDATE salas
        SET pozo_total = pozo_total + NEW.buy_in_pagado
        WHERE id = NEW.sala_id;
    END IF;
END //

-- Trigger: Registrar transacción cuando se aprueba un retiro
-- NOTA: El procedimiento sp_solicitar_retiro ya descuenta el saldo e inserta la transacción.
-- Este trigger solo actualiza el estado de la solicitud, no vuelve a descontar.
CREATE TRIGGER trg_retiro_aprobado
AFTER UPDATE ON solicitudes_retiro
FOR EACH ROW
BEGIN
    IF OLD.estado != 'APROBADO' AND NEW.estado = 'APROBADO' THEN
        -- Solo registramos en log, no duplicamos el débito contable
        INSERT INTO transacciones (billetera_id, tipo, monto, saldo_resultante, descripcion, referencia)
        SELECT
            b.id,
            'AJUSTE',
            0.00,
            b.saldo_disponible,
            CONCAT('Retiro aprobado - ', NEW.referencia),
            CONCAT('LOG-', NEW.referencia)
        FROM billeteras b
        WHERE b.usuario_id = NEW.usuario_id;
    END IF;
END //

DELIMITER ;

-- ============================================
-- Fin del esquema
-- ============================================
