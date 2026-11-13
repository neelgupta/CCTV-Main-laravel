<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_user_assignments')) {
            Schema::create('ticket_user_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['ticket_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('tickets')) {
            return;
        }

        $now = now();

        DB::table('tickets')
            ->whereNotNull('assigned_to')
            ->select(['id', 'assigned_to'])
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use ($now): void {
                $rows = [];

                foreach ($tickets as $ticket) {
                    if (! $ticket->assigned_to) {
                        continue;
                    }

                    $rows[] = [
                        'ticket_id' => $ticket->id,
                        'user_id' => $ticket->assigned_to,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($rows)) {
                    DB::table('ticket_user_assignments')->insertOrIgnore($rows);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_user_assignments');
    }
};

