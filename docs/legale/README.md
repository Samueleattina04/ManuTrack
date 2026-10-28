# Modelli legali — BOZZE

> **Bozze da far rivedere a un avvocato prima dell'uso.** Non sono consulenza legale, non
> sono complete per ogni caso e non sono la licenza di questo repository (la licenza del
> codice è decisa a parte dal titolare del progetto).

Servono a chi offre ManuTrack ai clienti (un'installazione per azienda) come punto di partenza
per i documenti che il GDPR (Regolamento UE 2016/679) e il contratto richiedono.

| File | A cosa serve | Chi lo firma o lo pubblica |
|---|---|---|
| [informativa-privacy-utenti.md](informativa-privacy-utenti.md) | Informativa (art. 13 GDPR) per le persone che usano ManuTrack in azienda | L'azienda cliente (titolare) la consegna ai propri dipendenti e collaboratori |
| [nomina-responsabile-art28.md](nomina-responsabile-art28.md) | Accordo sul trattamento dei dati (art. 28 GDPR) tra azienda cliente e fornitore del servizio | Azienda cliente (titolare) e fornitore (responsabile) |
| [sub-responsabili.md](sub-responsabili.md) | Elenco dei sub-responsabili (hosting, posta) e procedura di modifica | Fornitore; allegato all'accordo art. 28 |
| [condizioni-di-servizio-e-licenza.md](condizioni-di-servizio-e-licenza.md) | Condizioni del servizio e licenza d'uso dell'installazione per il cliente | Fornitore e azienda cliente |

## Come usarli

1. Sostituire tutti i segnaposto tra parentesi quadre, per esempio `[RAGIONE SOCIALE CLIENTE]`,
   `[FORNITORE]`, `[HOSTING]`, `[DATA]`. Cercare `[` nel testo per non dimenticarne nessuno.
2. Togliere le parti marcate *(se applicabile)* che non servono.
3. Far rivedere il testo a un avvocato, in particolare:
   - i **tempi di lavoro** registrati sugli ordini e il **registro attività** possono rientrare
     nei controlli a distanza dei lavoratori (art. 4 della legge 300/1970, Statuto dei
     lavoratori, e art. 114 del Codice privacy): serve una valutazione caso per caso,
     eventualmente un accordo sindacale o l'autorizzazione dell'Ispettorato;
   - la necessità di una **valutazione d'impatto** (art. 35 GDPR);
   - i **trasferimenti fuori dallo Spazio economico europeo**, se l'hosting o la posta li prevedono;
   - limiti di responsabilità, foro e legge applicabile nelle condizioni di servizio.
4. Tenere allineati i documenti con le impostazioni reali dell'installazione
   (Sistema › Dati e privacy › Conservazione) e con [docs/GDPR.md](../GDPR.md).

## Cosa fa ManuTrack per aiutare (riassunto)

- Esportazione completa dei dati dell'azienda in uno ZIP con indice (portabilità, restituzione
  dei dati a fine contratto).
- Anonimizzazione di una persona mantenendo coerenti storico e indicatori (diritto alla
  cancellazione, fine del rapporto di lavoro).
- Regole di conservazione: anonimizzazione automatica delle persone disattivate dopo N mesi,
  durata del registro attività.
- Cancellazione totale dell'installazione a fine contratto con rapporto di cosa è stato
  eliminato.
- Nessun cookie di profilazione, nessuno strumento di statistiche esterno, nessuna risorsa
  caricata da siti terzi.

Dettagli tecnici e limiti (copie di sicurezza, file già esportati) in [docs/GDPR.md](../GDPR.md).
