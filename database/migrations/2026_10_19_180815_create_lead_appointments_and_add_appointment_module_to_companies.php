<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'allow_appointment_module')) {
                $table->boolean('allow_appointment_module')->default(false)->after('allow_growth_staff_tracking');
            }
        });

        if (! Schema::hasTable('lead_appointments')) {
            Schema::create('lead_appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->date('appointment_date');
                $table->string('slot_label', 50); // e.g. "09:00 - 10:00"
                $table->time('slot_start_time');
                $table->time('slot_end_time');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('scheduled'); // scheduled, completed, cancelled, rescheduled
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'appointment_date', 'slot_start_time'], 'company_date_slot_idx');
                $table->index(['lead_id'], 'lead_appointment_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_appointments');

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'allow_appointment_module')) {
                $table->dropColumn('allow_appointment_module');
            }
        });
    }
};
