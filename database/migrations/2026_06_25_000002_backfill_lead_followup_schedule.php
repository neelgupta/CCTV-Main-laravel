<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lead_followup_schedule') || ! Schema::hasTable('leads')) {
            return;
        }

        $now = now();

        DB::table('leads')
            ->select(['id', 'next_follow_up', 'created_by'])
            ->whereNotNull('next_follow_up')
            ->when(Schema::hasColumn('leads', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('id')
            ->chunkById(500, function ($leads) use ($now) {
                foreach ($leads as $lead) {
                    $createdBy = $lead->created_by;

                    if ($createdBy !== null && ! DB::table('users')->where('id', $createdBy)->exists()) {
                        $createdBy = null;
                    }

                    $exists = DB::table('lead_followup_schedule')
                        ->where('lead_id', $lead->id)
                        ->whereDate('followup_date', $lead->next_follow_up)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('lead_followup_schedule')->insert([
                        'lead_id' => $lead->id,
                        'followup_date' => $lead->next_follow_up,
                        'status' => 'pending',
                        'completed_at' => null,
                        'created_by' => $createdBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally keep generated schedule rows; they may have been used after migration.
    }
};
