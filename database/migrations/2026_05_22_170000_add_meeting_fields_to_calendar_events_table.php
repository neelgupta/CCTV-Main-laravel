<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_events', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('event_date');
            }

            if (!Schema::hasColumn('calendar_events', 'meeting_type')) {
                $table->string('meeting_type', 20)->nullable()->after('event_type');
            }

            if (!Schema::hasColumn('calendar_events', 'meeting_link')) {
                $table->string('meeting_link', 500)->nullable()->after('meeting_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'meeting_link')) {
                $table->dropColumn('meeting_link');
            }

            if (Schema::hasColumn('calendar_events', 'meeting_type')) {
                $table->dropColumn('meeting_type');
            }

            if (Schema::hasColumn('calendar_events', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
        });
    }
};
