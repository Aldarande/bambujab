# BambuJab Plugin

**BambuJab** ermöglicht die **Überwachung und Steuerung Ihrer BambuLab 3D-Drucker** aus Jeedom heraus, vollständig über das **lokale Netzwerk** (LAN-only), ohne die Bambu-Cloud.

Unterstützte Modelle: **X1 / X1C, P1P / P1S, A1 / A1 mini**.

# Kompatibilität

Erfordert Jeedom **≥ 4.4** und einen BambuLab-Drucker.

> ⚠️ **Wichtig — ein Drucker ist entweder im LAN- oder im Cloud-Modus, nie in beiden.**
> Ist der **„Nur-LAN-Modus"** am Drucker **aktiviert** (*Einstellungen › Netzwerk*), wird er **von der
> Cloud getrennt**: nutzen Sie den **LAN**-Modus. Ist er **deaktiviert**, läuft der Drucker über die
> **Cloud**: nutzen Sie den **Cloud**-Modus. Wählen Sie **einen Modus** pro Drucker — ein Moduswechsel
> am Drucker nimmt den anderen offline.

# Installation

1. Plugin aus dem Market installieren und aktivieren.
2. Im Reiter **Konfiguration** auf **Abhängigkeiten installieren** klicken. Eine isolierte
   Python-Umgebung (*venv*) wird mit `paho-mqtt` und `requests` erstellt.
3. Auf **„Abhängigkeiten OK"** warten, dann den **Dienst starten**.

> Das Plugin verbindet sich mit keinem Bambu-Server – die gesamte Kommunikation ist lokal.

# Drucker hinzufügen

Auf **Hinzufügen** klicken oder **Im Netzwerk suchen**.

| Feld | Beschreibung |
|---|---|
| **IP-Adresse** | Lokale IP des Druckers. |
| **Zugangscode** | Aus *Einstellungen › Netzwerk › LAN-Modus*. Verschlüsselt gespeichert. |
| **Seriennummer** | *Optional* – leer lassen für **automatische Erkennung**. |
| **Modell** | *Automatisch erkannt* anhand der Seriennummer (änderbar). |

**Aktivieren** und **Sichtbar** anhaken, dann **Speichern**.

# Befehle

**Information (Überwachung):** Druckerstatus, aktuelle Phase, Fortschritt (%), Schicht, Restzeit,
Auftragsname, Temperaturen **Düse / Bett / Kammer**, Lüfter, Geschwindigkeit, WLAN, Licht, Düse,
**HMS**-Warnungen, **online**. **AMS** (dynamisch): Filamenttyp, **Farbe**, Restmenge, Feuchtigkeit,
aktives Fach, externe Spule.

**Aktionen (Steuerung):** Pause, Fortsetzen, Stopp, Home, Licht EIN/AUS, Geschwindigkeit (1–4),
Düse/Bett-Solltemperatur, Aktualisieren.

# Dateien

Die Schaltfläche **Dateien** erlaubt: vorhandene Projekte (`.3mf`/`.gcode`) **auflisten**, eine Datei
**hochladen** und einen Druck **neu starten** (AMS-Zuordnung + Optionen).

> ⚠️ Der Druckstart startet den Drucker: Achten Sie auf ein freies Druckbett.

# Kamera

Ein **Kamera**-Panel (best-effort, v. a. P1/A1) erfasst ein Kammerbild mit Auto-Aktualisierung.
Die Verfügbarkeit hängt von Modell und Firmware ab.

# Widget

Ein dediziertes Widget zeigt eine Übersichtskarte: Status, Fortschritt, Schicht/Zeit, Temperaturen
und farbige AMS-Filamente.

# Sicherheit & Datenschutz

Der Zugangscode wird verschlüsselt gespeichert und erscheint nie in den Logs. Es werden keine Daten
an die Bambu-Cloud gesendet.

# Unterstützung

BambuJab ist kostenlos und Open Source (AGPL v3):
[Ko-fi](https://ko-fi.com/aldarande), [GitHub Sponsors](https://github.com/sponsors/Aldarande). Danke!

# Changelog

Siehe [changelog](changelog.md).
