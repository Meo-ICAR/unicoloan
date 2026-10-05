<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colonne e indici aggiunti a tabelle gia' esistenti in unicooam_races.
     */
    public function up(): void
    {
        if (Schema::hasTable('audits') && ! Schema::hasColumn('audits', 'drive_folder_id')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->string('drive_folder_id')->nullable();
            });
        }

        if (Schema::hasTable('complaint_registry')) {
            $newColumns = [
                'data_subject_request_id' => fn (Blueprint $t) => $t->unsignedBigInteger('data_subject_request_id')->nullable(),
                'event_sequence' => fn (Blueprint $t) => $t->unsignedInteger('event_sequence')->nullable(),
                'event_at' => fn (Blueprint $t) => $t->dateTime('event_at')->nullable(),
                'event_phase' => fn (Blueprint $t) => $t->string('event_phase')->nullable(),
                'mandating_company' => fn (Blueprint $t) => $t->string('mandating_company')->nullable(),
                'master_agency' => fn (Blueprint $t) => $t->string('master_agency')->nullable(),
                'sub_supplier' => fn (Blueprint $t) => $t->string('sub_supplier')->nullable(),
                'caller_number' => fn (Blueprint $t) => $t->string('caller_number')->nullable(),
                'agcom_roc_compliance' => fn (Blueprint $t) => $t->string('agcom_roc_compliance')->nullable(),
                'event_channel_label' => fn (Blueprint $t) => $t->string('event_channel_label')->nullable(),
                'event_direction' => fn (Blueprint $t) => $t->string('event_direction')->nullable(),
                'event_counterparty' => fn (Blueprint $t) => $t->string('event_counterparty')->nullable(),
                'complainant_phone' => fn (Blueprint $t) => $t->string('complainant_phone')->nullable(),
                'complainant_fiscal_code' => fn (Blueprint $t) => $t->string('complainant_fiscal_code')->nullable(),
                'operational_action' => fn (Blueprint $t) => $t->text('operational_action')->nullable(),
                'dnc_blacklist_status' => fn (Blueprint $t) => $t->string('dnc_blacklist_status')->nullable(),
                'sla_deadline_note' => fn (Blueprint $t) => $t->string('sla_deadline_note')->nullable(),
                'log_freeze_retention' => fn (Blueprint $t) => $t->text('log_freeze_retention')->nullable(),
                'evidence_attachment' => fn (Blueprint $t) => $t->string('evidence_attachment')->nullable(),
                'phase_status' => fn (Blueprint $t) => $t->string('phase_status')->nullable(),
                'assigned_to' => fn (Blueprint $t) => $t->string('assigned_to')->nullable(),
            ];

            foreach ($newColumns as $column => $definition) {
                if (! Schema::hasColumn('complaint_registry', $column)) {
                    Schema::table('complaint_registry', fn (Blueprint $table) => $definition($table));
                }
            }

            Schema::table('complaint_registry', function (Blueprint $table) {
                if (Schema::hasIndex('complaint_registry', 'complaint_registry_protocol_number_unique')) {
                    $table->dropUnique('complaint_registry_protocol_number_unique');
                }
                if (! Schema::hasIndex('complaint_registry', 'complaint_registry_protocol_number_index')) {
                    $table->index('protocol_number');
                }
                if (! Schema::hasIndex('complaint_registry', 'complaint_registry_data_subject_request_id_index')) {
                    $table->index('data_subject_request_id');
                }
                if (! Schema::hasIndex('complaint_registry', 'complaint_registry_protocol_number_event_sequence_unique')) {
                    $table->unique(['protocol_number', 'event_sequence']);
                }
            });
        }

        if (Schema::hasTable('clienti_oam') && ! Schema::hasIndex('clienti_oam', 'clienti_oam_clienti_id_oam_code_id_unique')) {
            Schema::table('clienti_oam', function (Blueprint $table) {
                $table->unique(['clienti_id', 'oam_code_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('clienti_oam') && Schema::hasIndex('clienti_oam', 'clienti_oam_clienti_id_oam_code_id_unique')) {
            Schema::table('clienti_oam', fn (Blueprint $table) => $table->dropUnique('clienti_oam_clienti_id_oam_code_id_unique'));
        }

        if (Schema::hasTable('complaint_registry')) {
            Schema::table('complaint_registry', function (Blueprint $table) {
                $table->dropUnique('complaint_registry_protocol_number_event_sequence_unique');
                $table->dropIndex('complaint_registry_data_subject_request_id_index');
                $table->dropIndex('complaint_registry_protocol_number_index');
            });

            Schema::table('complaint_registry', function (Blueprint $table) {
                $table->dropColumn([
                    'data_subject_request_id', 'event_sequence', 'event_at', 'event_phase', 'mandating_company',
                    'master_agency', 'sub_supplier', 'caller_number', 'agcom_roc_compliance', 'event_channel_label',
                    'event_direction', 'event_counterparty', 'complainant_phone', 'complainant_fiscal_code',
                    'operational_action', 'dnc_blacklist_status', 'sla_deadline_note', 'log_freeze_retention',
                    'evidence_attachment', 'phase_status', 'assigned_to',
                ]);
                $table->unique('protocol_number');
            });
        }

        if (Schema::hasTable('audits') && Schema::hasColumn('audits', 'drive_folder_id')) {
            Schema::table('audits', fn (Blueprint $table) => $table->dropColumn('drive_folder_id'));
        }
    }
};
