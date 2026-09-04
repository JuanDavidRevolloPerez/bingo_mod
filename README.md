# 🎱 Bingo Online Multijugador

Una aplicación web interactiva y en tiempo real para jugar al Bingo tradicional en salas multijugador utilizando **PHP**, **JavaScript moderno** y **CSS personalizado**.

---

## 🚀 Características Principales

- **Salas Multijugador en Tiempo Real:** Crea una sala con un código único de 4 letras o únete a una sala existente mediante dicho código.
- **Generación Dinámica de Cartones:** Cada jugador recibe un cartón único de 5x5 generado aleatoriamente según la modalidad seleccionada.
- **Modalidades de Nomenclatura:**
  - **Normal:** Fichas de $A00$ a $E99$ (rango 00–99 por cada letra).
  - **Reducida:** Distribución segmentada ($A00\text{–}19$, $B20\text{–}39$, $C40\text{–}59$, $D60\text{–}79$, $E80\text{–}99$).
- **Extracción Automática de Balotas:** El sistema extrae balotas automáticamente a intervalos configurables (5s, 10s o 15s) por el anfitrión (host).
- **Voz y Efectos Sonoros:** Síntesis de voz (Web Speech API) para cantar cada balota extraída, con opción de silenciar/activar audio.
- **Validación Automática de Bingo:** Comprobación instantánea del patrón ganador (línea horizontal, vertical o diagonal) contra el historial de balotas extraídas para evitar trampas o falsos positivos.
- **Pantalla de Ganador y Celebración:** Pantalla final con animación de confeti interactivo y efectos de victoria.
- **Almacenamiento Liviano:** Persistencia de estado en archivos JSON estructurados con protección `.htaccess`.

---

## 📂 Estructura del Proyecto

```text
bingo_mod/
├── data/                       # Almacenamiento de salas activas (JSON)
│   └── .htaccess               # Protección contra accesos directos por URL
├── php/                        # Backend y controladores de la API
│   ├── funciones.php           # Lógica central del juego, generadores y validadores
│   ├── crear_sala.php          # Creación de nuevas salas de juego
│   ├── unirse_sala.php         # Validación e ingreso de jugadores a salas
│   ├── iniciar_partida.php     # Inicio de juego y configuración por el host
│   ├── cambiar_velocidad.php   # Ajuste dinámico de velocidad de balotas
│   ├── cantar_bingo.php        # Validación de reclamos de Bingo
│   └── verificar_estado.php    # Polling y sincronización del estado de juego
├── index.php                   # Página principal (Lobby: Crear / Unirse)
├── espera.php                  # Sala de espera y panel del anfitrión
├── juego.php                   # Tablero interactivo y juego en vivo
├── ganador.php                 # Pantalla de premiación y celebración
├── style.css                   # Sistema de diseño, temas y animaciones
├── SRS_BINGO_ONLINE.md         # Especificación de Requerimientos de Software (SRS)
└── README.md                   # Documentación principal del repositorio
```

---

## 🛠️ Requisitos e Instalación

### Requisitos
- **Servidor Web:** Apache (incluido en XAMPP, WampServer, Laragon) o el servidor embebido de PHP.
- **PHP:** Versión 7.4 o superior (recomendado PHP 8.0+).
- **Navegador:** Cualquier navegador moderno con soporte para JavaScript ES6 y Web Speech API.

### Pasos de Instalación

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/JuanDavidRevolloPerez/bingo_mod.git
   ```

2. **Ubicar en el servidor web:**
   - Si usas **XAMPP**, copia la carpeta dentro de `C:\xampp\htdocs\`.
   - Inicia el módulo **Apache** desde el Panel de Control de XAMPP.

3. **Ejecutar en el navegador:**
   - Abre tu navegador e ingresa a:
     ```
     http://localhost/bingo_modificado/bingo_mod/
     ```
   - O usando el servidor integrado de PHP:
     ```bash
     cd bingo_mod
     php -S localhost:8000
     ```
     e ingresa a `http://localhost:8000`.

---

## 🎮 Reglas del Juego

1. El anfitrión crea una sala y comparte el código de 4 caracteres.
2. Los demás participantes ingresan su nombre y el código de sala.
3. El anfitrión configura la velocidad y la nomenclatura y presiona **Iniciar Partida**.
4. A medida que salen las balotas, los jugadores hacen clic sobre sus casillas para marcarlas.
5. Quien complete una línea horizontal, columna vertical o diagonal completa podrá presionar **¡CANTAR BINGO!**.
6. El sistema validará que todos los números marcados hayan salido efectivamente en el juego.

---

## 👤 Autor

- **Juan Revollo** - [GitHub](https://github.com/JuanDavidRevolloPerez)
- Contacto: `juandavidrevolloperez@gmail.com`
