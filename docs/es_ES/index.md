# Plugin BambuJab

**BambuJab** permite **supervisar y controlar tus impresoras 3D BambuLab** desde Jeedom, totalmente
en la **red local** (LAN-only), sin usar la nube de Bambu.

Modelos compatibles: **X1 / X1C, P1P / P1S, A1 / A1 mini**.

# Compatibilidad

Requiere Jeedom **≥ 4.4** y una impresora BambuLab con el **Modo LAN activado**
(en la impresora: *Ajustes › Red › Modo LAN*).

# Instalación

1. Instala el plugin desde el Market y actívalo.
2. En la pestaña **Configuración**, pulsa **Instalar dependencias**. Se crea un entorno Python
   aislado (*venv*) con `paho-mqtt` y `requests`.
3. Espera a **«Dependencias OK»** y luego **inicia el demonio**.

> El plugin no se conecta a ningún servidor Bambu: toda la comunicación es local.

# Añadir una impresora

Pulsa **Añadir** o **Buscar en la red**.

| Campo | Descripción |
|---|---|
| **Dirección IP** | IP local de la impresora. |
| **Código de acceso** | De *Ajustes › Red › Modo LAN*. Almacenado cifrado. |
| **Número de serie** | *Opcional* — déjalo vacío para la **detección automática**. |
| **Modelo** | *Detectado automáticamente* a partir del número de serie (editable). |

Marca **Activar** y **Visible**, luego **Guardar**.

# Comandos

**Información (supervisión):** estado, fase, progreso (%), capa, tiempo restante, nombre del trabajo,
temperaturas **boquilla / cama / cámara**, ventiladores, velocidad, Wi-Fi, luz, boquilla, avisos
**HMS**, **en línea**. **AMS** (dinámico): tipo de filamento, **color**, restante, humedad, bandeja
activa, bobina externa.

**Acciones (control):** Pausar, Reanudar, Detener, Home, Luz ON/OFF, Velocidad (1–4),
Temperatura boquilla/cama, Actualizar.

# Archivos

El botón **Archivos** permite **listar** los proyectos presentes (`.3mf`/`.gcode`), **subir** un
archivo y **reiniciar** una impresión (mapeo AMS + opciones).

> ⚠️ Iniciar una impresión arranca la impresora: asegúrate de que la cama esté libre.

# Cámara

Un panel **Cámara** (best-effort, sobre todo P1/A1) captura una imagen de la cámara con actualización
automática. La disponibilidad depende del modelo y del firmware.

# Widget

Un widget dedicado muestra una tarjeta resumen: estado, progreso, capa/tiempo, temperaturas y
filamentos AMS con color.

# Seguridad y privacidad

El código de acceso se almacena cifrado y nunca aparece en los registros. No se envía ningún dato a
la nube de Bambu.

# Apoyo

BambuJab es gratuito y de código abierto (AGPL v3):
[Ko-fi](https://ko-fi.com/aldarande), [GitHub Sponsors](https://github.com/sponsors/Aldarande). ¡Gracias!

# Changelog

Consulta [changelog](changelog.md).
