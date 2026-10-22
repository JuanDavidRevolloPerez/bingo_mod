-- ============================================
-- Bingo Online V2.2 - Esquema de Base de Datos
-- Motor: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4
--
-- Versión CONSOLIDADA (Post-convergencia V2.2):
--   9 tablas. Fusiones aplicadas:
--     - sesiones            -> eliminada (la app usa sesiones PHP nativas)
--     - configuracion       -> eliminada (valores constantes en php/funciones.php)
--     - verificaciones_kyc  -> fusionada en usuarios (nivel_verificacion,
--                              documento_tipo, documento_num)
--     - solicitudes_retiro  -> conecta a usuarios solo vía usuario_id
--   Normalización del resultado de partidas:
--     - ganadores -> en salas -> extraída a partidas_resultado para que
--       salas tenga una sola conexión a usuarios (host_id).
-- ============================================

CREATE DATABASE IF NOT EXISTS bingo_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bingo_db;

-- ============================================
-- Tabla: usuarios
-- Almacena los usuarios registrados y sus datos KYC consolidados
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nickname            VARCHAR(30)  NOT NULL,
    email               VARCHAR(255) NOT NULL,
    password_hash       VARCHAR(255) NULL,           -- NULL si es cuenta Google
    google_id           VARCHAR(255) NULL,           -- ID de Google OAuth
    nivel_verificacion  TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0, 1 (OTP), 2 (documento)
    documento_tipo      ENUM('CC', 'CE', 'TI', 'NIT') NULL,  -- ex verificaciones_kyc
    documento_num       VARCHAR(20) NULL,                    -- ex verificaciones_kyc
    rol                 ENUM('JUGADOR', 'ADMIN', 'MODERADOR') NOT NULL DEFAULT 'JUGADOR',
    estado              ENUM('ACTIVO', 'SUSPENDIDO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_usuarios_email UNIQUE (email),
    CONSTRAINT uq_usuarios_nickname UNIQUE (nickname),
    CONSTRAINT uq_usuarios_google_id UNIQUE (google_id),
    INDEX idx_usuarios_documento (documento_num)
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
-- Salas de bingo creadas por los usuarios.
-- Una sola conexión a usuarios (host_id). El resultado
-- de cada partida vive en partidas_resultado.
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
-- Tabla: partidas_resultado
-- Resultado de cada partida: un ganador (y su premio) por sala.
-- UNIQUE(sala_id) garantiza idempotencia al registrar el ganador.
-- ============================================
CREATE TABLE IF NOT EXISTS partidas_resultado (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sala_id             INT UNSIGNED NOT NULL,
    ganador_id          INT UNSIGNED NULL,           -- Usuario ganador
    premio_ganado       DECIMAL(12,2) NULL,
    fecha               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resultado_sala FOREIGN KEY (sala_id)
        REFERENCES salas(id) ON DELETE CASCADE,
    CONSTRAINT fk_resultado_ganador FOREIGN KEY (ganador_id)
        REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT uq_resultado_sala UNIQUE (sala_id)
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
    titular             VARCHAR(100) NOT NULL,
    estado              ENUM('PENDIENTE', 'APROBADO', 'RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    referencia          VARCHAR(50) NULL,
    fecha               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_retiros_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_retiros_estado (estado),
    INDEX idx_retiros_fecha (fecha)
) ENGINE=InnoDB;

-- ============================================
-- Estrategia de configuración
-- Los parámetros de sistema viven como constantes en
-- php/funciones.php (p. ej. VALOR_FICHA, comision_default,
-- max_jugadores_sala). La tabla configuracion fue eliminada
-- porque ninguna parte de la aplicación la consumía.
-- ============================================

-- ============================================
-- Trigger funcional
-- ============================================

DELIMITER //

-- Trigger: Actualizar pozo_total de la sala cuando se une un jugador
-- (parte del flujo de buy-in; el código PHP lo utiliza al agregar jugadores)
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

DELIMITER ;

-- ============================================
-- Fin del esquema
-- ============================================
