<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_type', 20)->default('Manual');
            $table->unsignedInteger('repeat_count_handled')->default(0);
            $table->boolean('is_current')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'is_current']);
            $table->index(['staff_id', 'assigned_at']);
        });

        if (! Schema::hasTable('tickets')) {
            return;
        }

        $now = now();

        DB::table('tickets')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->chunkById(500, function ($tickets) use ($now): void {
                $rows = [];

                foreach ($tickets as $ticket) {
                    $rows[] = [
                        'ticket_id' => $ticket->id,
                        'staff_id' => $ticket->assigned_to,
                        'assigned_at' => $ticket->created_at ?? $now,
                        'released_at' => null,
                        'assigned_by' => $ticket->user_id ?? null,
                        'assignment_type' => 'Initial',
                        'repeat_count_handled' => (int) ($ticket->repeat_count ?? 0),
                        'is_current' => true,
                        'note' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('ticket_assignment_histories')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignment_histories');
    }
};
