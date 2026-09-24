# Changelog BambuJab

# 0.7.11 (beta)

- ⚡ **Panel mucho más ligero**: el widget ya no consulta al servidor cuando la pestaña está en segundo plano, espacia sus consultas a 60 s con la impresora en reposo (7 s durante la impresión) y ahora solo pide una huella del estado: la tarjeta completa se reenvía únicamente si algo ha cambiado de verdad. Un panel abierto todo el día ya casi no cuesta nada.
- 🐛 **Botón «Detener» reparado**: el apóstrofo del mensaje de confirmación en francés rompía el controlador y el botón no hacía nada.
- 🐛 **Bobina externa**: el filamento del soporte de bobina (o del AMS lite) vuelve a reportarse en impresoras sin AMS y en las actualizaciones parciales.
- 🐛 **Alerta HMS de gravedad desconocida**: ahora se muestra en lugar de anunciarse como «Ninguna» cuando existía un mensaje.
- 🔒 **Callback HTTPS**: el certificado se verifica cuando la URL de retorno apunta fuera de la red local (sin cambios para una instalación Jeedom habitual).
- 🧪 **Pruebas automatizadas**: estados de impresión, decodificación de errores BambuLab, AMS/filamentos y el bucle de actualización del widget se verifican en cada cambio.

# 0.7.10 (beta)

- ✨ **Actualización del widget sin parpadeo**: la tarjeta solo se vuelve a dibujar cuando los datos cambian realmente. Se acabó el parpadeo recurrente cuando la impresora está en reposo o nada se mueve; durante la impresión, las actualizaciones siguen el ritmo de los cambios.

# 0.7.9 (beta)

- 🐛 **Corrección de visualización del widget**: al cargar el panel, la tarjeta podía «saltar» al centro y cubrir las demás baldosas, y podían apilarse copias en sucesivas actualizaciones. Ahora las dimensiones se fijan desde el primer render (sin saltos) y los duplicados se eliminan en cada actualización.

# 0.7.8 (beta)

- 🖼️ **Miniatura actualizada más rápido**: la caché fija del navegador de 10 min se sustituye por revalidación ETag. La vista previa se actualiza en cuanto una nueva impresión regenera la miniatura, sin descargas innecesarias cuando nada ha cambiado (respuesta 304).

# 0.7.7 (beta)

- 🖼️ **Miniatura más fiable**: cuando varios archivos `.3mf` coinciden con el nombre del trabajo, el plugin ahora elige la coincidencia exacta y luego el archivo más reciente (fecha MDTM), en lugar del primero encontrado — corrige las vistas previas de bandeja incorrectas.

# 0.7.6 (beta)

- 🐛 **Corrección de visualización**: durante la actualización automática, la tarjeta del widget se reposicionaba en el centro y cubría los demás widgets. La actualización ahora solo reemplaza el contenido (el nodo posicionado por la cuadrícula de Jeedom se conserva).

# 0.7.5 (beta)

- 🩺 **Diagnóstico del demonio más claro**: cuando ninguna impresora está lista, el mensaje ahora indica el equipo y el campo exacto que falta (p. ej. «… (cloud): número de serie (impresora no seleccionada) falta») en lugar del genérico «IP + código de acceso requerido», engañoso para una impresora cloud.

# 0.7.4 (beta)

- 🐛 **Corrección de visualización**: la tarjeta del widget podía estirarse a lo ancho de toda la ventana. El ancho ahora está limitado (máx. 440 px) manteniéndose fluido en móvil.

# 0.7.3 (beta)

- 🔌 **Estado de conexión**: nueva info `Conectado a la impresora` (enlace MQTT con el broker), distinta de "En línea" (datos recientes). Punto verde/gris en el widget para verificar de un vistazo que el plugin dialoga realmente con la impresora, incluso en reposo.

# 0.7.2 (beta)

- 🐛 **Corrección**: la ventana **Archivos** no se mostraba (página gris sin contenido) al editar una impresora. El modal se ha movido fuera del panel oculto.

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
