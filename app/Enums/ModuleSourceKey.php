<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

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

    case KycPepStatus = 'kyc.pep_status';
    case KycFinancingPurpose = 'kyc.financing_purpose';
    case KycEconomicActivity = 'kyc.economic_activity';
    case KycActivitySector = 'kyc.activity_sector';
    case KycActivityLocation = 'kyc.activity_location';
    case KycFinancingNature = 'kyc.financing_nature';
    case KycIncomeBand = 'kyc.income_band';
    case KycWealthBand = 'kyc.wealth_band';
    case KycLegalNature = 'kyc.legal_nature';
    case KycGeographicArea = 'kyc.geographic_area';
    case KycExecutorLink = 'kyc.executor_link';
    case KycExecutorName = 'kyc.executor.name';
    case KycExecutorFirstName = 'kyc.executor.first_name';
    case KycExecutorTaxCode = 'kyc.executor.tax_code';
    case KycExecutorBirthPlace = 'kyc.executor.birth_place';
    case KycExecutorBirthDate = 'kyc.executor.birth_date';
    case KycExecutorCitizenship = 'kyc.executor.citizenship';
    case KycExecutorSex = 'kyc.executor.sex';
    case KycExecutorCity = 'kyc.executor.city';
    case KycExecutorProvince = 'kyc.executor.province';
    case KycExecutorAddress = 'kyc.executor.address';
    case KycExecutorZip = 'kyc.executor.zip';
    case KycExecutorDocType = 'kyc.executor.doc_type';
    case KycExecutorDocNumber = 'kyc.executor.doc_number';
    case KycExecutorDocIssuer = 'kyc.executor.doc_issuer';
    case KycExecutorDocIssuedAt = 'kyc.executor.doc_issued_at';
    case KycExecutorDocExpiresAt = 'kyc.executor.doc_expires_at';
    case KycExecutorPepStatus = 'kyc.executor.pep_status';
    case KycOwner1Name = 'kyc.owner1.name';
    case KycOwner1FirstName = 'kyc.owner1.first_name';
    case KycOwner1TaxCode = 'kyc.owner1.tax_code';
    case KycOwner1BirthPlace = 'kyc.owner1.birth_place';
    case KycOwner1BirthDate = 'kyc.owner1.birth_date';
    case KycOwner1Citizenship = 'kyc.owner1.citizenship';
    case KycOwner1Sex = 'kyc.owner1.sex';
    case KycOwner1City = 'kyc.owner1.city';
    case KycOwner1Province = 'kyc.owner1.province';
    case KycOwner1Address = 'kyc.owner1.address';
    case KycOwner1Zip = 'kyc.owner1.zip';
    case KycOwner1DocType = 'kyc.owner1.doc_type';
    case KycOwner1DocNumber = 'kyc.owner1.doc_number';
    case KycOwner1DocIssuer = 'kyc.owner1.doc_issuer';
    case KycOwner1DocIssuedAt = 'kyc.owner1.doc_issued_at';
    case KycOwner1DocExpiresAt = 'kyc.owner1.doc_expires_at';
    case KycOwner1Criterion = 'kyc.owner1.criterion';
    case KycOwner1PepStatus = 'kyc.owner1.pep_status';
    case KycOwner2Name = 'kyc.owner2.name';
    case KycOwner2FirstName = 'kyc.owner2.first_name';
    case KycOwner2TaxCode = 'kyc.owner2.tax_code';
    case KycOwner2BirthPlace = 'kyc.owner2.birth_place';
    case KycOwner2BirthDate = 'kyc.owner2.birth_date';
    case KycOwner2Citizenship = 'kyc.owner2.citizenship';
    case KycOwner2Sex = 'kyc.owner2.sex';
    case KycOwner2City = 'kyc.owner2.city';
    case KycOwner2Province = 'kyc.owner2.province';
    case KycOwner2Address = 'kyc.owner2.address';
    case KycOwner2Zip = 'kyc.owner2.zip';
    case KycOwner2DocType = 'kyc.owner2.doc_type';
    case KycOwner2DocNumber = 'kyc.owner2.doc_number';
    case KycOwner2DocIssuer = 'kyc.owner2.doc_issuer';
    case KycOwner2DocIssuedAt = 'kyc.owner2.doc_issued_at';
    case KycOwner2DocExpiresAt = 'kyc.owner2.doc_expires_at';
    case KycOwner2Criterion = 'kyc.owner2.criterion';
    case KycOwner2PepStatus = 'kyc.owner2.pep_status';
    case KycOwner3Name = 'kyc.owner3.name';
    case KycOwner3FirstName = 'kyc.owner3.first_name';
    case KycOwner3TaxCode = 'kyc.owner3.tax_code';
    case KycOwner3BirthPlace = 'kyc.owner3.birth_place';
    case KycOwner3BirthDate = 'kyc.owner3.birth_date';
    case KycOwner3Citizenship = 'kyc.owner3.citizenship';
    case KycOwner3Sex = 'kyc.owner3.sex';
    case KycOwner3City = 'kyc.owner3.city';
    case KycOwner3Province = 'kyc.owner3.province';
    case KycOwner3Address = 'kyc.owner3.address';
    case KycOwner3Zip = 'kyc.owner3.zip';
    case KycOwner3DocType = 'kyc.owner3.doc_type';
    case KycOwner3DocNumber = 'kyc.owner3.doc_number';
    case KycOwner3DocIssuer = 'kyc.owner3.doc_issuer';
    case KycOwner3DocIssuedAt = 'kyc.owner3.doc_issued_at';
    case KycOwner3DocExpiresAt = 'kyc.owner3.doc_expires_at';
    case KycOwner3Criterion = 'kyc.owner3.criterion';
    case KycOwner3PepStatus = 'kyc.owner3.pep_status';

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
            default => (string) Str::of($this->value)->after('kyc.')->replace(['.', '_'], ' ')->prepend('KYC: '),
        };
    }
}
