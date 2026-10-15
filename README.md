# 🎱 BANG ONLINE Multijugador V2.2

Una aplicación web interactiva y en tiempo real para jugar al Bingo tradicional en salas multijugador utilizando **PHP 8.0+**, **JavaScript moderno** y **CSS personalizado**.

---

## 🚀 Características Principales

- **Salas Multijugador en Tiempo Real:** Crea una sala con un código único de 4 letras o únete a una sala existente mediante dicho código.
- **Generación Dinámica de Cartones:** Cada jugador recibe un cartón único de 5x5 generado aleatoriamente según la modalidad seleccionada.
- **Modalidades de Nomenclatura:**
  - **Normal:** Fichas de A00 a E99 (rango 00–99 por cada letra).
  - **Reducida:** Distribución segmentada (A00–19, B20–39, C40–59, D60–79, E80–99).
- **Extracción Automática de Balotas:** El sistema extrae balotas automáticamente a intervalos configurables (5s, 10s o 15s) por el anfitrión (host).
- **Voz y Efectos Sonoros:** Síntesis de voz (Web Speech API) para cantar cada balota extraída, con opción de silenciar/activar audio.
- **Validación Automática de Bingo:** Comprobación instantánea del patrón ganador (línea horizontal, vertical o diagonal) contra el historial de balotas extraídas para evitar trampas o falsos positivos.
- **Pantalla de Ganador y Celebración:** Pantalla final con animación de confeti interactivo y efectos de victoria.
- **Sistema de Billetera Virtual:** Economía virtual con fichas (1 ficha = $10,000 COP).
- **Verificación KYC:** Sistema de verificación de identidad en 3 niveles (0, 1, 2).
- **Autenticación con Google:** Login/registro con cuenta Google OAuth 2.0.
- **Salas Free y Payplay:** Salas gratuitas o con buy-in en fichas.
- **Sistema de Escrow:** Retención temporal de fondos en salas Payplay.
- **Panel de Administración:** Panel de control para gestión de usuarios, salas y configuración.

---

## 📂 Estructura del Proyecto

```text
bingo_mod-2.2/
├── data/                       # Almacenamiento de salas activas (JSON)
│   └── .htaccess               # Protección contra accesos directos por URL
├── database/                   # Capa de base de datos
│   ├── db.php                  # Conexión PDO a MySQL
│   └── schema.sql              # Esquema completo de BD
├── js/                         # JavaScript del cliente
│   └── panel_v22.js            # Panel de control V2.2
├── php/                        # Backend y API
│   ├── auth/                   # Autenticación
│   │   ├── init.php            # Inicialización centralizada de sesión
│   │   ├── google.php          # OAuth 2.0 con Google
│   │   ├── login.php           # Login con email/password
│   │   └── registro.php        # Registro de usuarios
│   ├── funciones.php           # Funciones centrales del juego
│   ├── billetera_acciones.php  # API de billetera, pagos, KYC
│   ├── crear_sala.php          # Creación de nuevas salas
│   ├── unirse_sala.php         # Validación e ingreso de jugadores
│   ├── iniciar_partida.php     # Inicio de juego y configuración
│   ├── cambiar_velocidad.php   # Ajuste dinámico de velocidad
│   ├── cantar_bingo.php        # Validación de reclamos de Bingo
│   └── verificar_estado.php    # Polling y sincronización del estado
├── .env                        # Variables de entorno (credenciales)
├── .env.example                # Plantilla de variables
├── .gitignore                  # Archivos ignorados por Git
├── index.php                   # Página principal (Lobby: Crear / Unirse)
├── login.php                   # Página de login
├── register.php                # Página de registro
├── espera.php                  # Sala de espera y panel del anfitrión
├── juego.php                   # Tablero interactivo y juego en vivo
├── ganador.php                 # Pantalla de premiación y celebración
├── perfil.php                  # Perfil y verificación KYC
├── wallet.php                  # Billetera virtual
├── style.css                   # Sistema de diseño, temas y animaciones
├── SRS_BINGO_ONLINE.md         # Especificación de Requerimientos de Software
└── README.md                   # Documentación principal del repositorio
```

---

## 🛠️ Requisitos e Instalación

### Requisitos
- **Servidor Web:** Apache (incluido en XAMPP, WampServer, Laragon) o el servidor embebido de PHP.
- **PHP:** Versión 8.0 o superior.
- **MySQL:** Versión 8.0+ o MariaDB 10.5+.
- **Navegador:** Cualquier navegador moderno con soporte para JavaScript ES6 y Web Speech API.

### Pasos de Instalación

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/JuanDavidRevolloPerez/bingo_mod.git
   ```

2. **Ubicar en el servidor web:**
   - Si usas **XAMPP**, copia la carpeta dentro de `C:\xampp\htdocs\`.
   - Inicia los módulos **Apache** y **MySQL** desde el Panel de Control de XAMPP.

3. **Configurar variables de entorno:**
   ```bash
   cp .env.example .env
   ```
   Edita el archivo `.env` con tus credenciales de base de datos y Google OAuth.

4. **Crear la base de datos:**
   - Importa el archivo `database/schema.sql` en phpMyAdmin o MySQL CLI:
   ```bash
   mysql -u root -p < database/schema.sql
   ```

5. **Configurar Google OAuth (opcional):**
   - Crea un proyecto en [Google Cloud Console](https://console.cloud.google.com/).
   - Habilita la API de Google+.
   - Crea credenciales OAuth 2.0.
   - Configura el URI de redirección: `http://localhost/bingo_mod-2.2/bingo_mod-2.2/php/auth/google.php`
   - Copia el Client ID y Client Secret en el archivo `.env`.

6. **Ejecutar en el navegador:**
   - Abre tu navegador e ingresa a:
     ```
     http://localhost/bingo_mod-2.2/bingo_mod-2.2/
     ```

---

## 🎮 Reglas del Juego

1. El anfitrión crea una sala y comparte el código de 4 caracteres.
2. Los demás participantes ingresan su nombre y el código de sala.
3. El anfitrión configura la velocidad y la nomenclatura y presiona **Iniciar Partida**.
4. A medida que salen las balotas, los jugadores hacen clic sobre sus casillas para marcarlas.
5. Quien complete una línea horizontal, columna vertical o diagonal completa podrá presionar **¡CANTAR BINGO!**.
6. El sistema validará que todos los números marcados hayan salido efectivamente en el juego.

---

## 💰 Economía Virtual

| Concepto | Valor |
|----------|-------|
| **Valor de 1 ficha** | $10,000 COP |
| **Bono de bienvenida** | 5 fichas ($50,000 COP) |
| **Comisión plataforma (Rake)** | 10% |
| **Buy-in mínimo Payplay** | 1 ficha |

### Niveles de Verificación KYC

| Nivel | Descripción | Permisos |
|-------|-------------|----------|
| **Nivel 0** | Usuario básico | Solo salas gratuitas |
| **Nivel 1** | Email verificado (OTP) | Recargas y salas Payplay |
| **Nivel 2** | Documento + certificación bancaria | Retiros bancarios |

---

## 🔒 Seguridad

- **Prepared statements PDO** (anti-SQL injection)
- **htmlspecialchars()** para output (anti-XSS)
- **password_hash()** con BCRYPT (cost 12)
- **hash_equals()** para validación de webhooks
- **Bloqueo de filas FOR UPDATE** para race conditions
- **Validación de state** en OAuth Google
- **Rate limiting** en login (5 intentos por minuto)
- **Transacciones BD** para operaciones financieras
- **random_int()** para generación de OTPs

---

## 📊 Base de Datos

### Tablas principales
- `usuarios` - Usuarios registrados
- `billeteras` - Saldo de fichas
- `verificaciones_kyc` - Estado de verificación KYC
- `transacciones` - Libro mayor inmutable
- `salas` - Salas de bingo
- `jugadores_sala` - Relación N:M usuarios-salas
- `cartones` - Cartones de bingo
- `historial_fichas` - Secuencia de fichas cantadas
- `solicitudes_retiro` - Solicitudes de retiro bancario
- `ganadores` - Ganadores por sala
- `sesiones` - Sesiones activas
- `configuracion` - Configuración general del sistema

---

## 👤 Autor

- **Juan Revollo** - [GitHub](https://github.com/JuanDavidRevolloPerez)
- Contacto: `juandavidrevolloperez@gmail.com`

---

## 📄 Licencia

Este proyecto es de uso educativo y personal.
