<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amcs', function (Blueprint $table): void {
            if (! Schema::hasColumn('amcs', 'scheduled_visits')) {
                $table->unsignedSmallInteger('scheduled_visits')->default(0)->after('total_visits');
            }

            if (! Schema::hasColumn('amcs', 'remaining_visits')) {
                $table->unsignedSmallInteger('remaining_visits')->default(0)->after('scheduled_visits');
            }
        });

        Schema::table('amc_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('amc_visits', 'visit_type')) {
                $table->string('visit_type', 20)->default('scheduled')->after('visit_no');
            }

            if (! Schema::hasColumn('amc_visits', 'support_ticket_id')) {
                $table->foreignId('support_ticket_id')->nullable()->after('technician')->constrained('tickets')->nullOnDelete();
            }

            $table->unique(['amc_id', 'visit_no'], 'amc_visits_amc_id_visit_no_unique');
            $table->index(['amc_id', 'visit_date'], 'amc_visits_amc_id_visit_date_index');
        });

        DB::table('amcs')
            ->select(['id', 'total_visits'])
            ->orderBy('id')
            ->chunkById(200, function ($amcs): void {
                foreach ($amcs as $amc) {
                    $scheduledVisits = DB::table('amc_visits')
                        ->where('amc_id', $amc->id)
                        ->whereNotNull('visit_date')
                        ->count();

                    DB::table('amcs')
                        ->where('id', $amc->id)
                        ->update([
                            'scheduled_visits' => $scheduledVisits,
                            'remaining_visits' => max(0, (int) $amc->total_visits - $scheduledVisits),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('amc_visits', function (Blueprint $table): void {
            if (Schema::hasColumn('amc_visits', 'support_ticket_id')) {
                $table->dropConstrainedForeignId('support_ticket_id');
            }

            if (Schema::hasColumn('amc_visits', 'visit_type')) {
                $table->dropColumn('visit_type');
            }

            $table->dropUnique('amc_visits_amc_id_visit_no_unique');
            $table->dropIndex('amc_visits_amc_id_visit_date_index');
        });

        Schema::table('amcs', function (Blueprint $table): void {
            if (Schema::hasColumn('amcs', 'remaining_visits')) {
                $table->dropColumn('remaining_visits');
            }

            if (Schema::hasColumn('amcs', 'scheduled_visits')) {
                $table->dropColumn('scheduled_visits');
            }
        });
    }
};
