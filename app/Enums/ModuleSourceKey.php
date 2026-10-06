<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Whitelist delle chiavi dati utilizzabili per compilare i campi dei moduli PDF.
 * E' l'unica fonte di verita' per la UI di mappatura e per ModuleDataResolver:
 * aggiungere una chiave = una riga qui + il suo ramo nel resolver.
 */
enum ModuleSourceKey: string implements HasLabel
{
    case PraticaCodice = 'pratica.codice_pratica';
    case PraticaImporto = 'pratica.amount';
    case PraticaRata = 'pratica.rata';
    case PraticaNumeroRate = 'pratica.nrate';
    case PraticaBanca = 'pratica.denominazione_banca';
    case PraticaAbi = 'pratica.abi';
    case PraticaProdotto = 'pratica.denominazione_prodotto';
    case PraticaDataInserimento = 'pratica.data_inserimento_pratica';
    case PraticaOggi = 'pratica.oggi';
    case ClienteCognome = 'client.name';
    case ClienteNome = 'client.first_name';
    case ClienteNominativo = 'client.nominativo';
    case ClienteCodiceFiscale = 'client.tax_code';
    case ClientePartitaIva = 'client.vat_number';
    case ClienteEmail = 'client.email';
    case ClienteTelefono = 'client.phone';
    case ClienteStipendio = 'client.salary';
    case ClienteIban = 'client.iban';
    case ClientePersonaFisica = 'client.is_person';
    case ClienteDataNascita = 'client.birth_date';
    case ClienteLuogoNascita = 'client.birth_place';
    case ClienteSesso = 'client.sex';
    case ClienteCittadinanza = 'client.citizenship';

    case ClientePec = 'client.pec';
    case ClienteAteco = 'client.ateco_code';
    case ClienteCciaa = 'client.cciaa_registration';

    case RappresentanteNominativo = 'legal_rep.nominativo';
    case RappresentanteEmail = 'legal_rep.email';
    case RappresentanteTelefono = 'legal_rep.phone';

    case AgenteNominativo = 'agent.name';
    case AgenteIndirizzo = 'agent.indirizzo_completo';
    case AgenteEmail = 'agent.email';
    case AgenteTelefono = 'agent.tel';
    case AgenteCodiceFiscale = 'agent.cf';

    case TerziBanca = 'third_party.denominazione_banca';
    case TerziProdotto = 'third_party.denominazione_prodotto';
    case TerziRata = 'third_party.rata';
    case DatoreNome = 'employer.name';
    case DatorePartitaIva = 'employer.vat_number';
    case DatoreIndirizzo = 'employer.address';
    case SedeIndirizzo = 'branch.address';
    case SedeCivico = 'branch.street_number';
    case SedeCitta = 'branch.city';
    case SedeCap = 'branch.zip_code';
    case SedeProvincia = 'branch.province';
    case SedeIndirizzoCompleto = 'branch.indirizzo_completo';
    case DocumentoNumero = 'document.identity.docnumber';
    case DocumentoRilasciatoDa = 'document.identity.emitted_by';
    case DocumentoRilasciatoIl = 'document.identity.emitted_at';
    case DocumentoScadenza = 'document.identity.expires_at';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PraticaCodice => 'Pratica: codice',
            self::PraticaImporto => 'Pratica: importo richiesto',
            self::PraticaRata => 'Pratica: rata',
            self::PraticaNumeroRate => 'Pratica: numero rate',
            self::PraticaBanca => 'Pratica: banca',
            self::PraticaAbi => 'Pratica: ABI',
            self::PraticaProdotto => 'Pratica: prodotto',
            self::PraticaDataInserimento => 'Pratica: data inserimento',
            self::PraticaOggi => 'Data odierna',
            self::ClienteCognome => 'Cliente: cognome / ragione sociale',
            self::ClienteNome => 'Cliente: nome',
            self::ClienteNominativo => 'Cliente: cognome e nome',
            self::ClienteCodiceFiscale => 'Cliente: codice fiscale / P.IVA',
            self::ClientePartitaIva => 'Cliente: partita IVA',
            self::ClienteEmail => 'Cliente: email',
            self::ClienteTelefono => 'Cliente: telefono',
            self::ClienteStipendio => 'Cliente: stipendio',
            self::ClienteIban => 'Cliente: IBAN',
            self::ClientePersonaFisica => 'Cliente: è persona fisica (casella)',
            self::ClienteDataNascita => 'Cliente: data di nascita',
            self::ClienteLuogoNascita => 'Cliente: luogo di nascita',
            self::ClienteSesso => 'Cliente: sesso (M/F)',
            self::ClienteCittadinanza => 'Cliente: cittadinanza',
            self::ClientePec => 'Cliente: PEC',
            self::ClienteAteco => 'Cliente: codice Ateco',
            self::ClienteCciaa => 'Cliente: iscrizione CCIAA',
            self::RappresentanteNominativo => 'Legale rappresentante: cognome e nome',
            self::RappresentanteEmail => 'Legale rappresentante: email',
            self::RappresentanteTelefono => 'Legale rappresentante: telefono',
            self::AgenteNominativo => 'Collaboratore (fornitore): nome',
            self::AgenteIndirizzo => 'Collaboratore (fornitore): indirizzo completo',
            self::AgenteEmail => 'Collaboratore (fornitore): email',
            self::AgenteTelefono => 'Collaboratore (fornitore): telefono',
            self::AgenteCodiceFiscale => 'Collaboratore (fornitore): codice fiscale',
            self::TerziBanca => 'Finanziamento di terzi: banca',
            self::TerziProdotto => 'Finanziamento di terzi: prodotto',
            self::TerziRata => 'Finanziamento di terzi: rata',
            self::DatoreNome => 'Datore di lavoro: ragione sociale',
            self::DatorePartitaIva => 'Datore di lavoro: partita IVA',
            self::DatoreIndirizzo => 'Datore di lavoro: indirizzo sede',
            self::SedeIndirizzo => 'Sede cliente: indirizzo',
            self::SedeCivico => 'Sede cliente: civico',
            self::SedeCitta => 'Sede cliente: città',
            self::SedeCap => 'Sede cliente: CAP',
            self::SedeProvincia => 'Sede cliente: provincia',
            self::SedeIndirizzoCompleto => 'Sede cliente: indirizzo completo',
            self::DocumentoNumero => 'Documento identità: numero',
            self::DocumentoRilasciatoDa => 'Documento identità: rilasciato da',
            self::DocumentoRilasciatoIl => 'Documento identità: data rilascio',
            self::DocumentoScadenza => 'Documento identità: scadenza',
        };
    }
}
