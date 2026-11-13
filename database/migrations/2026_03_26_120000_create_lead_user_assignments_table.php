<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lead_id', 'user_id']);
        });

        DB::table('leads')
            ->whereNotNull('assigned_to')
            ->select(['id', 'assigned_to'])
            ->orderBy('id')
            ->chunkById(200, function ($leads): void {
                $rows = [];
                $now = now();

                foreach ($leads as $lead) {
                    if (!$lead->assigned_to) {
                        continue;
                    }

                    $rows[] = [
                        'lead_id' => $lead->id,
                        'user_id' => $lead->assigned_to,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('lead_user_assignments')->insertOrIgnore($rows);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_user_assignments');
    }
};

