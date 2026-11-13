<?php

use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'board_stage')) {
                $table->string('board_stage')->nullable()->after('status');
            }
        });

        DB::table('tickets')
            ->select('id', 'status', 'priority', 'assigned_to')
            ->orderBy('id')
            ->chunkById(200, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update([
                            'board_stage' => Ticket::stageForStatus(
                                (string) $ticket->status,
                                $ticket->priority ? (string) $ticket->priority : null,
                                $ticket->assigned_to ? (int) $ticket->assigned_to : null
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'board_stage')) {
                $table->dropColumn('board_stage');
            }
        });
    }
};
