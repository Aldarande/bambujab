# Plugin BambuJab

**BambuJab** permette di **monitorare e controllare le stampanti 3D BambuLab** da Jeedom, interamente
sulla **rete locale** (LAN-only), senza usare il cloud Bambu.

Modelli supportati: **X1 / X1C, P1P / P1S, A1 / A1 mini**.

# Compatibilità

Richiede Jeedom **≥ 4.4** e una stampante BambuLab.

> ⚠️ **Importante — una stampante è in modalità LAN oppure Cloud, mai entrambe.**
> Se la **«Modalità solo LAN»** è **attiva** sulla stampante (*Impostazioni › Rete*), essa si
> **disconnette dal cloud**: usa la modalità **LAN**. Se la **disattivi**, la stampante passa al
> **cloud**: usa la modalità **Cloud**. Scegli **una sola modalità** per stampante — cambiare modalità
> sulla stampante mette l'altra offline.

# Installazione

1. Installare il plugin dal Market e attivarlo.
2. Nella scheda **Configurazione**, cliccare su **Installa dipendenze**. Viene creato un ambiente
   Python isolato (*venv*) con `paho-mqtt` e `requests`.
3. Attendere **«Dipendenze OK»**, poi **avviare il demone**.

> Il plugin non si connette ad alcun server Bambu: tutta la comunicazione è locale.

# Aggiungere una stampante

Cliccare su **Aggiungi** oppure **Cerca sulla rete**.

| Campo | Descrizione |
|---|---|
| **Indirizzo IP** | IP locale della stampante. |
| **Codice di accesso** | Da *Impostazioni › Rete › Modalità LAN*. Memorizzato cifrato. |
| **Numero di serie** | *Opzionale* — lasciare vuoto per il **rilevamento automatico**. |
| **Modello** | *Rilevato automaticamente* dal numero di serie (modificabile). |

Spuntare **Attiva** e **Visibile**, poi **Salva**.

# Comandi

**Informazioni (monitoraggio):** stato, fase, avanzamento (%), strato, tempo rimanente, nome lavoro,
temperature **ugello / piano / camera**, ventole, velocità, Wi-Fi, luce, ugello, avvisi **HMS**,
**online**. **AMS** (dinamico): tipo filamento, **colore**, residuo, umidità, slot attivo, bobina esterna.

**Azioni (controllo):** Pausa, Riprendi, Arresta, Home, Luce ON/OFF, Velocità (1–4),
Temperatura ugello/piano, Aggiorna.

# File

Il pulsante **File** consente di **elencare** i progetti presenti (`.3mf`/`.gcode`), **caricare** un
file e **riavviare** una stampa (mappatura AMS + opzioni).

> ⚠️ L'avvio di una stampa avvia la stampante: assicurarsi che il piano sia libero.

# Telecamera

Un pannello **Telecamera** (best-effort, soprattutto P1/A1) cattura un'immagine della camera con
aggiornamento automatico. La disponibilità dipende dal modello e dal firmware.

# Widget

Un widget dedicato mostra una scheda riassuntiva: stato, avanzamento, strato/tempo, temperature e
filamenti AMS colorati.

# Sicurezza e privacy

Il codice di accesso è cifrato e non compare mai nei log. Nessun dato viene inviato al cloud Bambu.

# Sostegno

BambuJab è gratuito e open-source (AGPL v3):
[Ko-fi](https://ko-fi.com/aldarande), [GitHub Sponsors](https://github.com/sponsors/Aldarande). Grazie!

# Changelog

Vedi [changelog](changelog.md).
