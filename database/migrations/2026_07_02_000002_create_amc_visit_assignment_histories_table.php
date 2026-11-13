<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amc_visit_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amc_visit_id')->constrained('amc_visits')->cascadeOnDelete();
            $table->string('old_technician_name', 191)->nullable();
            $table->string('new_technician_name', 191)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_source', 30)->default('manual');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['amc_visit_id', 'created_at'], 'amc_visit_assignment_histories_visit_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amc_visit_assignment_histories');
    }
};
