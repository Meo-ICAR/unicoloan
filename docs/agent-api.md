# API in ingresso per unicoagent

unicoagent (l'agente WhatsApp) consegna a unicoloan **cliente, pratica e documenti** di una richiesta di finanziamento.
Base: `https://<unicoloan>/api/agente/v1`. Codice: `AgentRequestController`, `AgentRequestIntake`, `AuthenticateApiClient`.

## Credenziali e firma

`php artisan agent-api:client unicoagent` mostra **una sola volta** token e segreto (`--rotate` li rigenera). Ogni richiesta porta:

| Intestazione | Valore |
|---|---|
| `Authorization` | `Bearer <token>` |
| `X-Timestamp` | secondi Unix; scarto massimo 300 s |
| `X-Signature` | `hex(HMAC-SHA256(segreto, "<timestamp>.<METODO>.<percorso>.<sha256 del contenuto>"))` |

Contenuto firmato: il corpo JSON; nei caricamenti il **file**, il cui hash va anche in `X-Content-Sha256` (il server lo ricontrolla).
Percorso: ad es. `/api/agente/v1/richieste`. Qualsiasi errore di autenticazione risponde `401` senza dire quale controllo è fallito.

## Endpoint

**`POST /richieste`** crea cliente, pratica e documenti richiesti. Idempotente per `riferimento`: ripeterlo risponde `200` con `"creata": false`.

```json
{ "riferimento": "FIN-2026-0001", "prodotto": "quinto", "prodotto_etichetta": "Cessione del quinto",
  "pratica": { "importo_richiesto": 20000 },
  "cliente": { "cognome": "Rossi", "nome": "Mario", "codice_fiscale": "RSSMRA80A01H501U", "email": "…", "telefono": "…" },
  "agente": { "partita_iva": "12345678901" } }
```

Risposta `201`: `riferimento`, `codice_pratica` (`WA-<riferimento>`), `pratica_id`, `cliente_id`, `stato_pratica` (`INSERITA`), `creata`,
`documenti[{id, tipo, nome, stato, ricevuto, motivo_rifiuto}]` (i documenti richiesti dai plichi per cliente e pratica, in stato `richiesto`).

Errori: `422 agente_sconosciuto` (partita IVA non trovata), `409 cliente_di_altro_agente` (il cliente è già di un altro agente), `422` di validazione.
Il tipo prodotto deve esistere in Proforma: si usa `config/agent_api.php › product_map`, poi l'etichetta, poi `default_tipo_prodotto` (`Altro`).

**`GET /richieste/{riferimento}`** stato della pratica e dei documenti (per sapere se un documento è stato respinto e perché).

**`POST /richieste/{riferimento}/documenti`** (multipart) carica un file. Campi: `tipo` (obbligatorio), `file`, `id_origine` (id dell'allegato in unicoagent),
`analisi` (JSON con l'esito dell'analisi già fatta, conservato nei metadati). Intestazione `X-Content-Sha256` obbligatoria.
Il file va sul documento richiesto di quel tipo (o ne nasce uno nuovo sulla pratica); canale `whatsapp`, `channel_ref = id_origine`;
i controlli automatici partono da soli. Stesso file due volte: `200` con `"duplicato": true`, nessun secondo file.
Errori: `404` richiesta inesistente, `422 tipo_documento_sconosciuto`, `422 hash_non_corrispondente`, `422 hash_mancante`, `422` file non ammesso.
File: pdf, jpg, jpeg, png, webp, heic, fino a 15 MB.

**`GET /tipi-documento`** i tipi documento accettati in `tipo`, con gli alias configurati in `agent_api.document_map`.

## Da fare

Template, stampa del modulo compilato e firma con OTP (già presenti in unicoloan: `PdfFormFiller`, `SignatureRequestService`) come nuovi endpoint,
e la notifica a unicoagent quando un documento viene respinto. Lato unicoagent manca il driver `unicoloan` (vedi `docs/crm-drivers.md` di unicoagent).
