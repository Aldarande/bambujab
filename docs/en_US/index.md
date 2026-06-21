# BambuJab plugin

**BambuJab** lets you **monitor and control your BambuLab 3D printers** from Jeedom, entirely over the **local network** (LAN-only), without using the Bambu cloud.

Supported models: **X1 / X1C, P1P / P1S, A1 / A1 mini**.

# Compatibility

Requires Jeedom **≥ 4.4** and a BambuLab printer with **LAN Mode enabled**
(on the printer: *Settings › Network › LAN Mode*).

# Installation

1. Install the plugin from the Market and enable it.
2. In the **Configuration** tab, click **Install / Reinstall dependencies**. An isolated Python
   environment (*venv*) is created with `paho-mqtt` and `requests`.
3. Wait for **"Dependencies OK"**, then **start the daemon**.

> The plugin never connects to any Bambu server: all communication is local.

# Adding a printer

Click **Add**, or **Search the network** to detect printers.

| Field | Description |
|---|---|
| **IP address** | Printer's local IP. |
| **Access code** | From *Settings › Network › LAN Mode*. Stored encrypted. |
| **Serial number** | *Optional* — leave empty for **automatic discovery**. |
| **Model** | *Auto-detected* from the serial (editable). |

Tick **Enable** and **Visible**, then **Save**. The daemon connects and commands populate within seconds.

# Commands

## Information (monitoring)

Printer state, current stage, progress (%), current / total layer, remaining time, job name,
**nozzle / bed / chamber** temperatures (and targets), fans, speed level and percentage, Wi-Fi
signal, light, nozzle diameter / type, **HMS** severity and messages, **online**.

**AMS** (created dynamically): per slot → filament type, **color**, remaining; per unit → humidity,
temperature; active tray; external spool.

## Actions (control)

**Pause**, **Resume**, **Stop**, **Home**, **Light ON/OFF**, **Set speed** (1–4),
**Set nozzle** (°C), **Set bed** (°C), **Refresh**.

# Printer files

On a printer's page, the **Files** button lets you **list** existing projects (`.3mf` / `.gcode`),
**upload** a file, and **restart** a print (with AMS mapping and options).

> ⚠️ Starting a print starts the printer: make sure the plate is ready.

# Camera

A **Camera** panel (best-effort, mainly P1/A1) captures a chamber image with an auto-refresh mode.
Availability depends on model and firmware.

# Widget

A dedicated widget shows a summary card: state, progress, layer and remaining time, temperatures
and colored AMS filaments.

# FAQ

**Commands stay empty / "offline".** Check the IP, access code and **LAN Mode**. If the serial was
left empty and nothing shows up, enter it manually (Bambu Handy app › *Device info*).

**Network search finds nothing.** SSDP discovery may be blocked (e.g. Docker bridge): use **manual
add** (IP + access code).

# Security & privacy

The access code is encrypted and never appears in logs. No data is sent to the Bambu cloud.

# Support

BambuJab is free and open-source (AGPL v3). To support development:
[Ko-fi](https://ko-fi.com/aldarande), [GitHub Sponsors](https://github.com/sponsors/Aldarande),
[Liberapay](https://liberapay.com/Aldarande/donate). Thank you!

# Changelog

See the [changelog](changelog.md) page.
