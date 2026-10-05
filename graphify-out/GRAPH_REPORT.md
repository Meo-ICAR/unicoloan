# Graph Report - unicoloan  (2026-10-05)

## Corpus Check
- Large corpus: 2438 files · ~481,791 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 2049 nodes · 4969 edges · 143 communities (66 shown, 77 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 26 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Dynamic Group Export
- Dashboard & AI Assistant
- Document Status Enums
- Clienti & Blacklist
- Plans & User Roles
- Employee Types
- Table Helpers
- Clienti OAM
- Relation Managers & Tables
- Clienti Forms
- Branches
- BPM Activity Actions
- Pratica & Model Field APIs
- Races Sync Migration
- BPM Actions & Clienti Pages
- Document Type Forms
- Fornitori Roles & Permissions
- Clienti Model
- Panel Providers
- Requisito Tipo Finanziamento
- Permissions Relation Manager
- Clients Table
- Document Reminders
- Client Model
- Company Inspections & OAM
- Reference Seeders
- Compliance Activity Log & User
- Company/Document Seeders
- Reminder Commands
- Pratica Pages
- Pratica Table & Model
- Core Migrations
- Socialite & Complaints Migrations
- Document Download
- Client Resource
- Company Resource
- Fornitori Role Resource
- Pratica Requisito Operativo
- Pratica Requisito
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139

## God Nodes (most connected - your core abstractions)
1. `HasPlanAccess` - 60 edges
2. `HasRelationPlanAccess` - 53 edges
3. `Document` - 46 edges
4. `Clienti` - 40 edges
5. `Company` - 34 edges
6. `Resource` - 33 edges
7. `Task` - 31 edges
8. `Fornitore` - 30 edges
9. `EmployeeType` - 29 edges
10. `DocumentType` - 28 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --calls--> `Task`  [INFERRED]
  app/Filament/Resources/Companies/Pages/EditCompany.php → app/Models/Task.php
- `{closure#2}()` --calls--> `Task`  [EXTRACTED]
  app/Filament/Resources/Employees/Tables/EmployeesTable.php → app/Models/Task.php
- `{closure#6}()` --calls--> `Task`  [EXTRACTED]
  app/Filament/Resources/Fornitores/Tables/FornitoresTable.php → app/Models/Task.php
- `{closure#1}()` --calls--> `Task`  [EXTRACTED]
  app/Filament/Resources/Tasks/Tables/TasksTable.php → app/Models/Task.php
- `{closure#4}()` --references--> `Document`  [EXTRACTED]
  app/Services/DocumentReminderService.php → app/Models/Document.php

## Import Cycles
- None detected.

## Communities (143 total, 77 thin omitted)

### Community 0 - "Dynamic Group Export"
Cohesion: 0.05
Nodes (14): {closure#1}(), DynamicGroupExport, M510MasterExport, {closure#1}(), M510AnagraficaSheet, {closure#1}(), M510EconomicoBaseSheet, {closure#1}() (+6 more)

### Community 1 - "Dashboard & AI Assistant"
Cohesion: 0.06
Nodes (18): AssistenteAi, Dashboard, DocumentTypeResource, DocumentTypeForm, DocumentTypesTable, FornitoreResource, FornitoreForm, FornitoresTable (+10 more)

### Community 2 - "Document Status Enums"
Cohesion: 0.05
Nodes (30): DocumentStatus, APPROVED, EXPIRED, NA, NOREADABLE, PENDING, PROVISIONAL, REJECTED (+22 more)

### Community 3 - "Clienti & Blacklist"
Cohesion: 0.05
Nodes (20): BlacklistRelationManager, DipendentiBlacklistatiRelationManager, LimitsRelationManager, ProvvigioniRelationManager, ClientisTable, ClientMandatesRelationManager, ClientPratichesRelationManager, CompanyRolesRelationManager (+12 more)

### Community 4 - "Plans & User Roles"
Cohesion: 0.07
Nodes (33): PlanType, Base, Full, Medium, UserRole, ADMIN, INSPECTOR, QUALITY (+25 more)

### Community 5 - "Employee Types"
Cohesion: 0.06
Nodes (17): EmployeeTypeResource, CreateEmployeeType, EditEmployeeType, ListEmployeeTypes, ViewEmployeeType, EmployeeTypeForm, EmployeeTypeInfolist, EmployeeTypesTable (+9 more)

### Community 6 - "Table Helpers"
Cohesion: 0.06
Nodes (20): {closure#1}(), {closure#2}(), {closure#3}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}() (+12 more)

### Community 7 - "Clienti OAM"
Cohesion: 0.06
Nodes (10): ClientiOam, {closure#1}(), ClientRelation, PraticaDocumentRequest, PraticaStatusHistory, ProvvigioniRule, RequisitoTipoFinanziamento, {closure#1}() (+2 more)

### Community 10 - "Branches"
Cohesion: 0.09
Nodes (9): {closure#1}(), {closure#3}(), Branch, ClientMandate, ClientType, MailAccount, Organization, Provvigione (+1 more)

### Community 11 - "BPM Activity Actions"
Cohesion: 0.08
Nodes (12): {closure#1}(), ListClientis, ListDocuments, ListDocumentTypes, ListEmailTemplates, ListEmployees, ListFornitores, ListMailAccounts (+4 more)

### Community 12 - "Pratica & Model Field APIs"
Cohesion: 0.11
Nodes (8): ModelFieldsApiController, ModelFieldValueApiController, PraticaApiController, UserLookupApiController, BpmBridgeController, Controller, BpmActivitiesClient, ModelFieldIntrospector

### Community 13 - "Races Sync Migration"
Cohesion: 0.12
Nodes (29): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+21 more)

### Community 14 - "BPM Actions & Clienti Pages"
Cohesion: 0.08
Nodes (10): BpmActivitiesAction, ClientiResource, CreateClienti, EditClienti, EditDocumentType, EditFornitore, EditRequisitoTipoFinanziamento, EditTask (+2 more)

### Community 15 - "Document Type Forms"
Cohesion: 0.08
Nodes (14): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#3}(), {closure#4}() (+6 more)

### Community 16 - "Fornitori Roles & Permissions"
Cohesion: 0.09
Nodes (6): {closure#1}(), {closure#2}(), LeadSource, PraticaStato, Tipoprodotto, TipoprodottoSub

### Community 18 - "Clienti Model"
Cohesion: 0.09
Nodes (3): Clienti, Fornitore, BlacklistChecker

### Community 20 - "Requisito Tipo Finanziamento"
Cohesion: 0.09
Nodes (6): CreateRequisitoTipoFinanziamento, ListRequisitoTipoFinanziamentos, RequisitoTipoFinanziamentoResource, RequisitoTipoFinanziamentoForm, RequisitoTipoFinanziamentosTable, HasPlanAccess

### Community 21 - "Permissions Relation Manager"
Cohesion: 0.15
Nodes (16): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}() (+8 more)

### Community 22 - "Clients Table"
Cohesion: 0.09
Nodes (4): {closure#8}(), {closure#3}(), {closure#4}(), TableHelper

### Community 23 - "Document Reminders"
Cohesion: 0.14
Nodes (5): DocumentReminder, EmailTemplate, DocumentReminderService, EmailTemplateRenderer, EmailTemplateSeeder

### Community 25 - "Company Inspections & OAM"
Cohesion: 0.11
Nodes (5): BranchFactory, CompanyFactory, CompanyIspectionFactory, OamSemestraleFactory, ResourceFactory

### Community 26 - "Reference Seeders"
Cohesion: 0.12
Nodes (7): ClientTypeSeeder, DocumentTypeSeeder, LeadSourceSeeder, MailAccountSeeder, OrganizationSeeder, ProvvigioniRuleSeeder, TipoProdottoSubConstraintsSeeder

### Community 27 - "Compliance Activity Log & User"
Cohesion: 0.15
Nodes (3): LogsComplianceActivity, {closure#1}(), User

### Community 28 - "Company/Document Seeders"
Cohesion: 0.11
Nodes (6): CompanySeeder, DocumentSeeder, PraticaStatiTransizioniSeeder, TipoProdottoSeeder, TipoProdottoSubSeeder, WebsiteSeeder

### Community 29 - "Reminder Commands"
Cohesion: 0.12
Nodes (5): SendDocumentRemindersCommand, {closure#1}(), SyncDocumentSchedules, SyncResourcesCommand, DocumentSchedule

### Community 30 - "Pratica Pages"
Cohesion: 0.14
Nodes (7): CreatePratica, EditPratica, ListPraticas, PraticaResource, PraticaForm, PraticasTable, PraticaStati

### Community 31 - "Pratica Table & Model"
Cohesion: 0.14
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), Pratica

### Community 32 - "Core Migrations"
Cohesion: 0.10
Nodes (5): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 33 - "Socialite & Complaints Migrations"
Cohesion: 0.10
Nodes (5): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 34 - "Document Download"
Cohesion: 0.13
Nodes (4): {closure#8}(), DocumentDownloadController, {closure#1}(), Document

### Community 35 - "Client Resource"
Cohesion: 0.16
Nodes (6): ClientResource, CreateClient, EditClient, ListClients, ClientForm, ClientsTable

### Community 36 - "Company Resource"
Cohesion: 0.15
Nodes (6): CompanyResource, CreateCompany, EditCompany, ListCompanies, CompanyForm, CompaniesTable

### Community 37 - "Fornitori Role Resource"
Cohesion: 0.16
Nodes (6): FornitoriRoleResource, CreateFornitoriRole, EditFornitoriRole, ListFornitoriRoles, FornitoriRoleForm, FornitoriRolesTable

### Community 38 - "Pratica Requisito Operativo"
Cohesion: 0.16
Nodes (6): CreatePraticaRequisitoOperativo, EditPraticaRequisitoOperativo, ListPraticaRequisitoOperativos, PraticaRequisitoOperativoResource, PraticaRequisitoOperativoForm, PraticaRequisitoOperativosTable

### Community 39 - "Pratica Requisito"
Cohesion: 0.16
Nodes (6): CreatePraticaRequisito, EditPraticaRequisito, ListPraticaRequisitos, PraticaRequisitoResource, PraticaRequisitoForm, PraticaRequisitosTable

### Community 40 - "Community 40"
Cohesion: 0.16
Nodes (6): CreateTipoprodotto, EditTipoprodotto, ListTipoprodottos, TipoprodottoForm, TipoprodottosTable, TipoprodottoResource

### Community 41 - "Community 41"
Cohesion: 0.16
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#6}(), {closure#7}()

### Community 42 - "Community 42"
Cohesion: 0.14
Nodes (6): CreatePraticaStato, EditPraticaStato, ListPraticaStatos, PraticaStatoResource, PraticaStatoForm, PraticaStatosTable

### Community 43 - "Community 43"
Cohesion: 0.18
Nodes (5): CreateTipoprodottoSub, EditTipoprodottoSub, ListTipoprodottoSubs, TipoprodottoSubForm, TipoprodottoSubResource

### Community 45 - "Community 45"
Cohesion: 0.50
Nodes (5): CompanyType, ALBERGO, CALL_CENTER, MEDIATORE, SOFTWARE_HOUSE

### Community 46 - "Community 46"
Cohesion: 0.18
Nodes (5): CreateDocumentType, CreateFornitore, CreateTask, CreateTipoprodottoSubConstraint, CreateUser

### Community 47 - "Community 47"
Cohesion: 0.20
Nodes (5): CreateProvvigioniRule, EditProvvigioniRule, ListProvvigioniRules, ProvvigioniRuleResource, ProvvigioniRulesTable

### Community 48 - "Community 48"
Cohesion: 0.16
Nodes (5): EmailTemplateResource, CreateEmailTemplate, EditEmailTemplate, EmailTemplateForm, EmailTemplatesTable

### Community 49 - "Community 49"
Cohesion: 0.15
Nodes (5): EmployeeResource, CreateEmployee, EditEmployee, EmployeeForm, EmployeesTable

### Community 50 - "Community 50"
Cohesion: 0.15
Nodes (5): OrganizationResource, CreateOrganization, EditOrganization, OrganizationForm, OrganizationsTable

### Community 51 - "Community 51"
Cohesion: 0.15
Nodes (4): ProvvigioniRuleForm, ProvvigioniRelationManager, ProvvigioniRelationManager, FornitoriRole

### Community 52 - "Community 52"
Cohesion: 0.18
Nodes (4): {closure#12}(), Task, TaskDocumentTypeSeeder, TaskSeeder

### Community 54 - "Community 54"
Cohesion: 0.19
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 56 - "Community 56"
Cohesion: 0.17
Nodes (5): MailAccountResource, CreateMailAccount, EditMailAccount, MailAccountForm, MailAccountsTable

### Community 63 - "Community 63"
Cohesion: 0.36
Nodes (8): ComplaintCategory, Behavior, Delay, Fraud, GdprAccess, GdprErasure, Rates, Transparency

### Community 64 - "Community 64"
Cohesion: 0.24
Nodes (3): PraticaRequisito, PraticaRequisitiSeeder, RequisitoTipoFinanziamentoSeeder

### Community 67 - "Community 67"
Cohesion: 0.43
Nodes (5): AmlReportStatus, ARCHIVED, DRAFTED, EVALUATING, REPORTED

### Community 68 - "Community 68"
Cohesion: 0.61
Nodes (6): AuditStatus, CANCELLED, COMPLETED, FOLLOW_UP, IN_PROGRESS, PLANNED

### Community 69 - "Community 69"
Cohesion: 0.61
Nodes (6): ComplaintStatus, Accepted, Escalated, Investigating, Open, Rejected

### Community 70 - "Community 70"
Cohesion: 0.61
Nodes (6): FindingStatus, AcceptedRisk, Closed, InProgress, Open, Resolved

### Community 71 - "Community 71"
Cohesion: 0.68
Nodes (5): SyncStatus, FAILED, LOCAL, SYNCED, SYNCING

### Community 73 - "Community 73"
Cohesion: 0.25
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 74 - "Community 74"
Cohesion: 0.48
Nodes (5): ComplaintMacroCategory, Financial, Insurance, Operational, Privacy

### Community 75 - "Community 75"
Cohesion: 0.67
Nodes (5): FindingSeverity, Critical, Major, Minor, Observation

### Community 76 - "Community 76"
Cohesion: 0.48
Nodes (5): GdprDsrStatus, EXTENDED, FULFILLED, PENDING, REJECTED

### Community 77 - "Community 77"
Cohesion: 0.48
Nodes (5): ReceptionChannel, BreviManu, Email, Pec, Raccomandata

### Community 78 - "Community 78"
Cohesion: 0.48
Nodes (5): RegulatoryFramework, GDPR, IVASS, OAM, SAFETY

### Community 83 - "Community 83"
Cohesion: 0.53
Nodes (4): GdprBreachStatus, CLOSED, CONTAINED, INVESTIGATING

### Community 84 - "Community 84"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 85 - "Community 85"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 86 - "Community 86"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

## Knowledge Gaps
- **4 isolated node(s):** `Base`, `Medium`, `Full`, `SOS`
  These have ≤1 connection - possible missing edges. (Counts symbols only; 447 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **77 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DocumentStatus` connect `Document Status Enums` to `Community 54`, `Community 73`, `Community 62`, `Clients Table`?**
  _High betweenness centrality (0.112) - this node is a cross-community bridge._
- **What connects `Base`, `Medium`, `Full` to the rest of the system?**
  _4 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Dynamic Group Export` be split into smaller, more focused modules?**
  _Cohesion score 0.05365296803652968 - nodes in this community are weakly interconnected._
- **Why does `Company` connect `Community 44` to `Community 65`, `Company Resource`, `Table Helpers`, `Community 41`, `Branches`, `Company/Document Seeders`, `Community 80`, `Community 52`, `Community 88`, `Company Inspections & OAM`, `Community 59`, `Community 60`?**
  _High betweenness centrality (0.075) - this node is a cross-community bridge._
- **Should `Dashboard & AI Assistant` be split into smaller, more focused modules?**
  _Cohesion score 0.056189640035118525 - nodes in this community are weakly interconnected._
- **Why does `DynamicGroupExport` connect `Dynamic Group Export` to `Dashboard & AI Assistant`, `Document Status Enums`, `Clienti & Blacklist`, `Relation Managers & Tables`, `Community 41`, `Community 49`, `Clients Table`, `Pratica Pages`, `Pratica Table & Model`?**
  _High betweenness centrality (0.043) - this node is a cross-community bridge._
- **Should `Document Status Enums` be split into smaller, more focused modules?**
  _Cohesion score 0.0506558118498417 - nodes in this community are weakly interconnected._