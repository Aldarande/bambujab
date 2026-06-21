# BambuJab changelog

>**IMPORTANT**
>
>If there is no information about an update, it only concerns documentation, translation or text.

# 0.5.0 (beta)

- ☁️ **BambuLab Cloud mode**: choose LAN or Cloud when adding a printer (fields adapt).
  Account login (email + verification code), bound-printer selection, monitoring and control
  over cloud MQTT. Widget adapts (LAN/Cloud badge; camera/FTPS are LAN-only).
  ⚠️ Cloud control may be restricted by BambuLab (Bambu Connect).

# 0.3.0 (beta)

- 📷 Camera (best-effort P1/A1): chamber image capture + auto-refresh.
- 🧩 Dedicated dashboard widget (card: state, progress, layer/time, temperatures, colored AMS, HMS).
- 📁 **Files** window: list, upload (`.3mf`/`.gcode`) and restart a print.
- ❤️ Donation button and window.

# 0.2.0 (beta)

- 🎮 Control: pause, resume, stop, home, light, speed, temperature targets, AMS.
- 📁 FTPS: list existing files, upload a file, restart a project (AMS mapping + options).

# 0.1.0 (beta)

- 🖨️ Real-time monitoring via local MQTT: states, stages, progress, time, temperatures, fans, speed, Wi-Fi, light, nozzle.
- 🎨 AMS handling: type, color, humidity, active tray, external spool (dynamic commands).
- ⚠️ HMS alert decoding.
- 🔎 Network discovery, optional serial (auto-discovery) and auto-detected model.
- 🔒 LAN-only, encrypted access code, no secret leakage in logs.
