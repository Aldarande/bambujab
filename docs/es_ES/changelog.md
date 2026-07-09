# Changelog BambuJab

# 0.7.1 (beta)

- 🐛 **Corrección**: error SQL (`Unknown column 'SECRET_KEYS'`) que impedía la creación del equipo en algunas instalaciones.

# 0.3.0 (beta)

- 📷 Cámara (best-effort P1/A1): captura de imagen de la cámara + actualización automática.
- 🧩 Widget de panel dedicado (estado, progreso, capa/tiempo, temperaturas, AMS con color, HMS).
- 📁 Ventana **Archivos**: listar, subir (`.3mf`/`.gcode`), reiniciar una impresión.
- ❤️ Botón y ventana de donación.

# 0.2.0 (beta)

- 🎮 Control: pausar, reanudar, detener, home, luz, velocidad, temperaturas, AMS.
- 📁 FTPS: listar archivos, subir, reiniciar un proyecto (mapeo AMS + opciones).

# 0.1.0 (beta)

- 🖨️ Supervisión en tiempo real vía MQTT local.
- 🎨 Gestión AMS (tipo, color, humedad, bandeja activa, bobina externa).
- ⚠️ Decodificación de avisos HMS. 🔎 Descubrimiento de red, número de serie opcional, modelo auto-detectado.
- 🔒 LAN-only, código de acceso cifrado, sin fugas de secretos en los registros.
