# Changelog BambuJab

# 0.7.4 (beta)

- 🐛 **Correzione di visualizzazione**: la scheda del widget poteva allargarsi su tutta la larghezza della finestra. La larghezza è ora limitata (max 440 px) pur restando fluida su mobile.

# 0.7.3 (beta)

- 🔌 **Stato di connessione**: nuova info `Connesso alla stampante` (collegamento MQTT al broker), distinta da "Online" (dati aggiornati). Pallino verde/grigio nel widget per verificare a colpo d'occhio che il plugin dialoghi davvero con la stampante, anche in standby.

# 0.7.2 (beta)

- 🐛 **Correzione**: la finestra **File** non veniva mostrata (pagina grigia senza contenuto) durante la modifica di una stampante. Il modale è stato spostato fuori dal pannello nascosto.

# 0.7.1 (beta)

- 🐛 **Correzione**: errore SQL (`Unknown column 'SECRET_KEYS'`) che impediva la creazione del dispositivo su alcune installazioni.

# 0.3.0 (beta)

- 📷 Telecamera (best-effort P1/A1): immagine della camera + aggiornamento automatico.
- 🧩 Widget dashboard dedicato (stato, avanzamento, strato/tempo, temperature, AMS colorato, HMS).
- 📁 Finestra **File**: elencare, caricare (`.3mf`/`.gcode`), riavviare una stampa.
- ❤️ Pulsante e finestra di donazione.

# 0.2.0 (beta)

- 🎮 Controllo: pausa, riprendi, arresta, home, luce, velocità, temperature, AMS.
- 📁 FTPS: elencare file, caricare, riavviare un progetto (mappatura AMS + opzioni).

# 0.1.0 (beta)

- 🖨️ Monitoraggio in tempo reale via MQTT locale.
- 🎨 Gestione AMS (tipo, colore, umidità, slot attivo, bobina esterna).
- ⚠️ Decodifica avvisi HMS. 🔎 Rilevamento rete, numero di serie opzionale, modello auto-rilevato.
- 🔒 LAN-only, codice di accesso cifrato, nessuna fuga di segreti nei log.
