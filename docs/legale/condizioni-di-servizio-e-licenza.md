# Condizioni di servizio e licenza d'uso di ManuTrack

> **BOZZA da far rivedere a un avvocato.** Sostituire i segnaposto tra parentesi quadre.
> Riguarda il rapporto tra il fornitore e l'azienda cliente. **Non è la licenza del codice
> sorgente di questo repository**, che è stabilita a parte dal titolare del progetto.

Versione del [DATA]

## 1. Parti e definizioni

- **Fornitore**: [FORNITORE], sede in [INDIRIZZO], P. IVA [P. IVA].
- **Cliente**: l'azienda che sottoscrive l'ordine, [RAGIONE SOCIALE CLIENTE].
- **ManuTrack** o **Programma**: il programma di gestione della manutenzione, nella versione
  installata per il Cliente, con i suoi aggiornamenti.
- **Installazione**: la copia del Programma dedicata al Cliente, con il suo database e i suoi
  file, presso [HOSTING DEL FORNITORE / SERVER DEL CLIENTE].
- **Utenti**: le persone autorizzate dal Cliente ad accedere all'Installazione.
- **Dati del Cliente**: tutti i dati inseriti o generati nell'Installazione.

## 2. Oggetto

Il Fornitore concede al Cliente l'uso del Programma tramite l'Installazione e fornisce i servizi
indicati nell'ordine: [INSTALLAZIONE, HOSTING, AGGIORNAMENTI, COPIE DI SICUREZZA, ASSISTENZA,
FORMAZIONE].

## 3. Licenza d'uso

3.1 Per la durata del contratto il Fornitore concede al Cliente una licenza **non esclusiva, non
trasferibile e non sublicenziabile** di usare il Programma per le proprie esigenze interne di
gestione della manutenzione, per il numero di Utenti e di sedi indicato nell'ordine
([NUMERO] / senza limite).

3.2 Il Cliente non può: cedere o mettere a disposizione di terzi il Programma; usarlo per
offrire servizi a terzi; decompilarlo o modificarlo salvo quanto consentito dalla legge
[O DALLA LICENZA DEL CODICE SORGENTE, SE PIÙ AMPIA]; rimuovere indicazioni di titolarità.

3.3 Il Programma resta di titolarità di [TITOLARE DEI DIRITTI]. Il nome e il logo "ManuTrack"
non possono essere usati senza autorizzazione. Il logo del Cliente caricato nell'Installazione
resta del Cliente.

3.4 Il Programma include componenti di terze parti distribuiti con le proprie licenze, elencati
in `docs/LIBRERIE.md`; tali licenze prevalgono per i rispettivi componenti.

## 4. Dati del Cliente

4.1 I Dati del Cliente restano del Cliente. Il Fornitore li tratta solo per fornire il servizio,
come responsabile del trattamento secondo l'accordo ai sensi dell'art. 28 GDPR
([nomina-responsabile-art28.md](nomina-responsabile-art28.md)), che fa parte del contratto.

4.2 Il Cliente può in ogni momento scaricare l'esportazione completa dei propri dati dalla
pagina Sistema › Dati e privacy.

4.3 Il Cliente è responsabile della liceità dei dati inseriti, di informare i propri Utenti
([informativa-privacy-utenti.md](informativa-privacy-utenti.md)) e degli adempimenti verso i
lavoratori (anche ai sensi dell'art. 4 della legge 300/1970, se applicabile).

## 5. Obblighi del Cliente

Il Cliente custodisce le credenziali, attiva la verifica in due passaggi almeno per gli
amministratori, disattiva gli account delle persone che non ne hanno più bisogno, non carica
contenuti illeciti né programmi dannosi, e segnala al Fornitore ogni uso non autorizzato.

## 6. Livello del servizio *(se il Fornitore offre l'hosting)*

| | |
|---|---|
| Disponibilità obiettivo | [99,5]% mensile, escluse le manutenzioni programmate |
| Manutenzioni programmate | comunicate con [48] ore di anticipo, preferibilmente in [FASCIA ORARIA] |
| Copie di sicurezza | [FREQUENZA], conservate per [DURATA] |
| Assistenza | [CANALE], [GIORNI E ORARI], presa in carico entro [TEMPO] |

Il Programma funziona anche in assenza temporanea di rete per l'invio differito delle
segnalazioni dal telefono; non è un sistema di sicurezza e **non sostituisce** i dispositivi,
le procedure e gli obblighi di sicurezza delle macchine.

## 7. Aggiornamenti

Il Fornitore rilascia aggiornamenti correttivi e di sicurezza per la versione principale in
uso. Gli aggiornamenti possono essere applicati automaticamente nella fascia di quiete
configurata; prima di ogni aggiornamento il Programma fa una copia di sicurezza.

## 8. Corrispettivi

[CANONE], [PERIODICITÀ], [MODALITÀ DI PAGAMENTO], [ADEGUAMENTI]. In caso di mancato pagamento
oltre [N] giorni dal sollecito, il Fornitore può sospendere il servizio con preavviso di [N]
giorni, senza cancellare i dati.

## 9. Garanzie e responsabilità

9.1 Il Fornitore garantisce che il Programma svolge in modo sostanziale le funzioni descritte
nella documentazione. Non garantisce l'assenza totale di errori.

9.2 Gli indicatori (MTBF, MTTR, disponibilità, costi) sono calcolati sui dati inseriti dal
Cliente secondo `docs/KPI.md`; la loro correttezza dipende dalla completezza dei dati.

9.3 [LIMITAZIONE DI RESPONSABILITÀ: DA DEFINIRE CON L'AVVOCATO. Esclusi in ogni caso dolo e
colpa grave e i casi in cui la legge non consente limitazioni.]

## 10. Durata, recesso e fine del contratto

10.1 Durata: [DURATA], rinnovo [TACITO / ESPRESSO], disdetta con [N] giorni di preavviso.

10.2 Alla fine del contratto:

1. il Fornitore mette a disposizione l'esportazione completa dei dati per [30] giorni;
2. su richiesta scritta del Cliente, o comunque trascorso tale termine, esegue la
   **cancellazione totale dell'installazione** e consegna il rapporto di cosa è stato eliminato;
3. elimina le copie di sicurezza entro [N] giorni, salvo obblighi di legge.

Restano sotto la responsabilità del Cliente le esportazioni e i file già scaricati.

## 11. Riservatezza

Le parti mantengono riservate le informazioni dell'altra parte di cui vengono a conoscenza,
per la durata del contratto e per [N] anni successivi.

## 12. Varie

Modifiche solo per iscritto. Legge applicabile: italiana. Foro competente: [FORO], salvo i
casi in cui la legge preveda un foro inderogabile.

[LUOGO], [DATA]

Il Fornitore ______________________ Il Cliente ______________________

Ai sensi degli art. 1341 e 1342 del Codice civile il Cliente approva specificamente: 3.2
(limiti della licenza), 8 (sospensione), 9.3 (limitazione di responsabilità), 10 (durata e
recesso), 12 (foro). [DA VERIFICARE CON L'AVVOCATO.]

Il Cliente ______________________
