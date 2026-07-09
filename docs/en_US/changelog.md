# BambuJab changelog

>**IMPORTANT**
>
>If there is no information about an update, it only concerns documentation, translation or text.

# 0.7.4 (beta)

- 🐛 **Display fix**: the widget card could stretch across the full window width. Width is now capped (max 440 px) while staying fluid on mobile.

# 0.7.3 (beta)

- 🔌 **Connection status**: new `Connected to the printer` info (MQTT link to the broker), distinct from "Online" (fresh data). Green/grey dot in the widget to check at a glance that the plugin is actually talking to the printer, even in standby.

# 0.7.2 (beta)

- 🐛 **Fix**: the **Files** window did not show (greyed-out page with no content) while editing a printer. The modal is moved out of the hidden panel.

# 0.7.1 (beta)

- 🐛 **Fix**: SQL error (`Unknown column 'SECRET_KEYS'`) preventing equipment creation on some installations.

# 0.7.0 (beta)

- ⚠️ **Human-readable print errors**: HMS codes resolved to messages (Bambu database) + error banner in the widget.
- 🔒 **Encryption at rest** of secrets (access code, cloud tokens).
- 🔁 **Automatic cloud token refresh** (cron) + alert before failure.
- 🧩 **Selectable widget type**: BambuJab card or standard (configurable) Jeedom widget.
- 📁 **Files & printing in Cloud mode** (via local IP); camera too.
- ▶️⏸️⏹️ **Pause/resume/stop buttons** directly in the widget; widget **auto-refresh**.
- 📈 **History** on progress and temperatures. Continuous integration (CI) added.

# 0.6.0 (beta)

- 📷 **Live video stream (MJPEG)** from the camera in the widget (persistent connection, ~1 fps A1/P1), instead of frozen snapshots.
- 🟢💤🔌 **3 printer states**: Online / Sleeping / Off (network reachability probe to tell sleep from power-off).
- 💡 **Clickable light** directly in the widget; title bar (name → config, donate, refresh).
- 🎨 AMS spools **numbered and spread** across the widget width.
- ☁️ **Cloud mode** finalized: **Google/Apple/Facebook** accounts supported (token), reliable MQTT user id resolution, local camera available in cloud via the local IP.
- 🔒 **Security hardening**: config/camera pages admin-only, Jeedom apikey in HTTP header (no longer in URL), unverified TLS documented (LAN self-signed), template script removed.
- 🧹 Automatic daemon restart on connection change; robust camera stream teardown; various fixes.

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
