<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Idempotent schema migrations for CometBilling.
 * Safe to call from activate, upgrade, and runtime import/pull paths.
 */
class SchemaMigrator
{
    /**
     * Minimal schema required for Bill History CSV import.
     * Prefer this on hot import paths so large usage ALTERs do not block.
     *
     * @return array{ok: bool, changes: list<string>, error: ?string}
     */
    public static function ensurePurchaseSchema(): array
    {
        $changes = [];
        try {
            if (!Capsule::schema()->hasTable('cb_purchase_import_batches')) {
                Capsule::schema()->create('cb_purchase_import_batches', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('imported_at');
                    $table->string('source_filename', 255)->nullable();
                    $table->date('earliest_date')->nullable();
                    $table->date('latest_date')->nullable();
                    $table->unsignedInteger('row_count')->default(0);
                    $table->unsignedInteger('purchase_count')->default(0);
                    $table->unsignedInteger('refund_count')->default(0);
                    $table->boolean('is_complete')->default(false);
                    $table->text('notes')->nullable();
                    $table->dateTime('created_at');
                });
                $changes[] = 'create cb_purchase_import_batches';
            }

            if (Capsule::schema()->hasTable('cb_credit_purchases')) {
                $sm = Capsule::schema();
                if (!$sm->hasColumn('cb_credit_purchases', 'record_type')) {
                    Capsule::connection()->statement(
                        "ALTER TABLE cb_credit_purchases ADD COLUMN record_type ENUM('purchase','refund','adjustment') NOT NULL DEFAULT 'purchase' AFTER currency"
                    );
                    $changes[] = 'cb_credit_purchases.record_type';
                }
                if (!$sm->hasColumn('cb_credit_purchases', 'import_batch_id')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_purchases ADD COLUMN import_batch_id BIGINT UNSIGNED NULL AFTER record_type'
                    );
                    $changes[] = 'cb_credit_purchases.import_batch_id';
                }
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'changes' => $changes, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'changes' => $changes, 'error' => null];
    }

    /**
     * Ensure audit-grade (1.0.3+) tables and columns exist.
     *
     * @return array{ok: bool, changes: list<string>, error: ?string}
     */
    public static function ensureLatest(): array
    {
        $purchase = self::ensurePurchaseSchema();
        if (!$purchase['ok']) {
            return $purchase;
        }
        $changes = $purchase['changes'];

        try {
            if (Capsule::schema()->hasTable('cb_credit_usage')) {
                $sm = Capsule::schema();
                if (!$sm->hasColumn('cb_credit_usage', 'content_fingerprint')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN content_fingerprint CHAR(32) NULL AFTER row_fingerprint'
                    );
                    $changes[] = 'cb_credit_usage.content_fingerprint';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'occurrence_number')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN occurrence_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER content_fingerprint'
                    );
                    $changes[] = 'cb_credit_usage.occurrence_number';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'packs_used_raw')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN packs_used_raw VARCHAR(512) NULL AFTER packs_used'
                    );
                    $changes[] = 'cb_credit_usage.packs_used_raw';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'packs_used_parsed')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN packs_used_parsed JSON NULL AFTER packs_used_raw'
                    );
                    $changes[] = 'cb_credit_usage.packs_used_parsed';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'first_seen_at')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN first_seen_at DATETIME NULL AFTER created_at'
                    );
                    $changes[] = 'cb_credit_usage.first_seen_at';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'last_seen_at')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN last_seen_at DATETIME NULL AFTER first_seen_at'
                    );
                    $changes[] = 'cb_credit_usage.last_seen_at';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'pull_manifest_id')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN pull_manifest_id BIGINT UNSIGNED NULL AFTER last_seen_at'
                    );
                    $changes[] = 'cb_credit_usage.pull_manifest_id';
                }
                if (!$sm->hasColumn('cb_credit_usage', 'is_present_in_latest_pull')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN is_present_in_latest_pull TINYINT(1) NOT NULL DEFAULT 1 AFTER pull_manifest_id'
                    );
                    $changes[] = 'cb_credit_usage.is_present_in_latest_pull';
                }

                Capsule::connection()->statement(
                    'UPDATE cb_credit_usage SET content_fingerprint = row_fingerprint WHERE content_fingerprint IS NULL OR content_fingerprint = ""'
                );
                Capsule::connection()->statement(
                    'UPDATE cb_credit_usage SET first_seen_at = created_at WHERE first_seen_at IS NULL'
                );

                $indexes = Capsule::select("SHOW INDEX FROM cb_credit_usage WHERE Key_name = 'uq_fingerprint'");
                if (!empty($indexes)) {
                    Capsule::connection()->statement('ALTER TABLE cb_credit_usage DROP INDEX uq_fingerprint');
                    $changes[] = 'drop uq_fingerprint';
                }
                $indexesOcc = Capsule::select("SHOW INDEX FROM cb_credit_usage WHERE Key_name = 'uq_content_occurrence'");
                if (empty($indexesOcc)) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD UNIQUE KEY uq_content_occurrence (content_fingerprint, occurrence_number)'
                    );
                    $changes[] = 'uq_content_occurrence';
                }
            }

            if (!Capsule::schema()->hasTable('cb_portal_pull_manifests')) {
                Capsule::schema()->create('cb_portal_pull_manifests', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('pulled_at');
                    $table->string('source', 32);
                    $table->unsignedInteger('row_count')->default(0);
                    $table->unsignedInteger('new_rows')->default(0);
                    $table->unsignedInteger('updated_rows')->default(0);
                    $table->char('checksum', 32)->nullable();
                    $table->json('meta')->nullable();
                    $table->dateTime('created_at');
                    $table->index('pulled_at');
                });
                $changes[] = 'create cb_portal_pull_manifests';
            }

            if (!Capsule::schema()->hasTable('cb_audit_runs')) {
                Capsule::schema()->create('cb_audit_runs', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('run_at');
                    $table->date('from_date');
                    $table->date('to_date');
                    $table->json('summary');
                    $table->json('coverage')->nullable();
                    $table->dateTime('created_at');
                    $table->index('run_at');
                });
                $changes[] = 'create cb_audit_runs';
            }

            if (!Capsule::schema()->hasTable('cb_audit_findings')) {
                Capsule::schema()->create('cb_audit_findings', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('audit_run_id');
                    $table->unsignedBigInteger('usage_id')->nullable();
                    $table->string('verdict', 32);
                    $table->string('debit_evidence', 16)->default('unclear');
                    $table->string('billing_verdict', 32);
                    $table->decimal('amount', 12, 4);
                    $table->string('account', 128)->nullable();
                    $table->string('device_id', 128)->nullable();
                    $table->string('category', 32)->nullable();
                    $table->date('usage_date');
                    $table->string('item_desc', 255)->nullable();
                    $table->date('expected_billing_end')->nullable();
                    $table->json('confidence_reasons')->nullable();
                    $table->json('evidence');
                    $table->index('audit_run_id');
                    $table->index('verdict');
                    $table->index('usage_date');
                });
                $changes[] = 'create cb_audit_findings';
            }

            if (!Capsule::schema()->hasTable('cb_ledger_rebuild_batches')) {
                Capsule::schema()->create('cb_ledger_rebuild_batches', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('started_at');
                    $table->dateTime('completed_at')->nullable();
                    $table->date('opening_date');
                    $table->date('closing_date');
                    $table->decimal('opening_credit', 12, 4);
                    $table->decimal('closing_credit', 12, 4)->nullable();
                    $table->boolean('is_complete')->default(false);
                    $table->json('validation')->nullable();
                    $table->dateTime('created_at');
                });
                $changes[] = 'create cb_ledger_rebuild_batches';
            }

            if (!Capsule::schema()->hasTable('cb_credit_usage_allocations')) {
                Capsule::schema()->create('cb_credit_usage_allocations', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('usage_id');
                    $table->unsignedBigInteger('lot_id');
                    $table->decimal('amount', 12, 4);
                    $table->decimal('lot_remaining_after', 12, 4);
                    $table->unsignedBigInteger('rebuild_batch_id')->nullable();
                    $table->dateTime('created_at');
                    $table->unique(['usage_id', 'lot_id'], 'uq_usage_lot');
                    $table->index('usage_id');
                });
                $changes[] = 'create cb_credit_usage_allocations';
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'changes' => $changes, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'changes' => $changes, 'error' => null];
    }
}
