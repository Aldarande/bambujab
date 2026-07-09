# BambuJab Changelog

# 0.7.2 (beta)

- 🐛 **Fehlerbehebung**: Das Fenster **Dateien** wurde im Bearbeitungsmodus eines Druckers nicht angezeigt (ausgegraute Seite ohne Inhalt). Das Modal wurde aus dem ausgeblendeten Panel verschoben.

# 0.7.1 (beta)

- 🐛 **Fehlerbehebung**: SQL-Fehler (`Unknown column 'SECRET_KEYS'`), der auf einigen Installationen das Anlegen des Geräts verhinderte.

# 0.3.0 (beta)

- 📷 Kamera (best-effort P1/A1): Kammerbild + Auto-Aktualisierung.
- 🧩 Dediziertes Dashboard-Widget (Status, Fortschritt, Schicht/Zeit, Temperaturen, farbiges AMS, HMS).
- 📁 Fenster **Dateien**: auflisten, hochladen (`.3mf`/`.gcode`), Druck neu starten.
- ❤️ Spenden-Schaltfläche und -Fenster.

# 0.2.0 (beta)

- 🎮 Steuerung: Pause, Fortsetzen, Stopp, Home, Licht, Geschwindigkeit, Solltemperaturen, AMS.
- 📁 FTPS: Dateien auflisten, hochladen, Projekt neu starten (AMS-Zuordnung + Optionen).

# 0.1.0 (beta)

- 🖨️ Echtzeit-Überwachung über lokales MQTT.
- 🎨 AMS-Verwaltung (Typ, Farbe, Feuchtigkeit, aktives Fach, externe Spule).
- ⚠️ HMS-Dekodierung. 🔎 Netzwerkerkennung, optionale Seriennummer, automatische Modellerkennung.
- 🔒 LAN-only, verschlüsselter Zugangscode, keine Geheimnis-Lecks in den Logs.
