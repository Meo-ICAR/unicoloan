# To-do dalla revisione del 2026-10-09 (firma OTP, portale agenti, storico stati)

Legenda: [x] fatto, [ ] da fare, [-] escluso per decisione.

## Sicurezza
- [x] 1. `checkPiano` non deve concedere nulla al ruolo `agent` (download documenti incluso).
- [x] 2. Un agente non puo' agganciare una pratica a un cliente gia' in anagrafica che non e' suo.
- [ ] 3. OTP e approvazione KYC alla firma: decisione di processo ancora da prendere (contatti del firmatario modificabili dal produttore; `verified_by` = produttore).
- [-] 4. File su disco pubblico: si sposteranno su SharePoint.
- [x] 5. Perimetro pratiche dell'agente: solo P.IVA esatta (la P.IVA agente non e' modificabile), senza sentinella.

## Funzionali e dati
- [ ] 6. Nomi degli slot coerenti (`cliente` / `collaboratore`) in seeder, form, DB e test.
- [ ] 7. I documenti eliminati escono dallo scadenziario (anche al momento della sostituzione col firmato).
- [-] 8. Atomicita' pratica+cliente: si portera' tutto su un unico DB.
- [ ] 9. `rejected_at` e storico stati leggono `pratiches_statos` (modello `PraticaStati`); salvataggi sempre via modello.
- [-] 10. Gestione worker e disattivazione agenti.
- [-] 11. Punti Yousign da verificare e dati personali nell'oggetto email.

## Operativita' e qualita'
- [ ] 12. Rate limiting su creazione pratiche e invii OTP (non richiesto: aperto).
- [ ] 13. Cancellare i dati di prova dal DB di sviluppo (KYC, richieste di firma, documenti).
- [ ] 14. Refactoring: stesso codice per il form KYC (admin e portale), niente nomi di classe inline.
