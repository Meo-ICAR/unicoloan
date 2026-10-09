# To-do dalla revisione del 2026-10-09 (firma OTP, portale agenti, storico stati)

Legenda: [x] fatto, [ ] da fare, [-] escluso per decisione.

## Sicurezza
- [x] 1. `checkPiano` non deve concedere nulla al ruolo `agent` (download documenti incluso).
- [x] 2. Un agente non puo' agganciare una pratica a un cliente gia' in anagrafica che non e' suo.
- [ ] 3. (rimandato a dopo) OTP e approvazione KYC alla firma: i contatti del firmatario sono modificabili dal produttore e `verified_by` e' il produttore; decidere il processo.
- [-] 4. File su disco pubblico: si sposteranno su SharePoint.
- [x] 5. Perimetro pratiche dell'agente: solo P.IVA esatta (la P.IVA agente non e' modificabile), senza sentinella.

## Funzionali e dati
- [x] 6. Nomi degli slot coerenti (`cliente` / `collaboratore`) in seeder, form, DB e test.
- [x] 7. I documenti eliminati escono dallo scadenziario (anche al momento della sostituzione col firmato).
- [-] 8. Atomicita' pratica+cliente: si portera' tutto su un unico DB.
- [x] 9. `rejected_at` e storico stati leggono `pratiches_statos` (modello `PraticaStati`); salvataggi sempre via modello.
- [-] 10. Gestione worker e disattivazione agenti.
- [-] 11. Punti Yousign da verificare e dati personali nell'oggetto email.

## Operativita' e qualita'
- [ ] 12. Rate limiting su creazione pratiche e invii OTP (da fare dopo, insieme al punto 3).
- [x] 13. Cancellare i dati di prova dal DB di sviluppo (KYC, richieste di firma, documenti).
- [x] 14. Refactoring: stesso codice per il form KYC (admin e portale), niente nomi di classe inline.
