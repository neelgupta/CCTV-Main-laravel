<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('assigned_to')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('leads')
            ->whereNull('created_by')
            ->update([
                'created_by' => DB::raw('assigned_to'),
            ]);

        DB::statement("
            UPDATE leads
            SET created_by = (
                SELECT lead_activities.user_id
                FROM lead_activities
                WHERE lead_activities.lead_id = leads.id
                    AND lead_activities.user_id IS NOT NULL
                ORDER BY lead_activities.id ASC
                LIMIT 1
            )
            WHERE created_by IS NULL
        ");

        DB::table('leads')
            ->whereNull('assigned_to')
            ->whereNotNull('created_by')
            ->update([
                'assigned_to' => DB::raw('created_by'),
            ]);

        DB::table('leads')
            ->select(['id', 'assigned_to'])
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->chunkById(200, function ($leads): void {
                $rows = [];

                foreach ($leads as $lead) {
                    $rows[] = [
                        'lead_id' => $lead->id,
                        'user_id' => $lead->assigned_to,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('lead_user_assignments')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
