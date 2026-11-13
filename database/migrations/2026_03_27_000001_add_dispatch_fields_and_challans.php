<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_productions', function (Blueprint $table) {
            if (!Schema::hasColumn('project_productions', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('qa_done');
            }
        });

        if (!Schema::hasTable('project_challans')) {
            Schema::create('project_challans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->onDelete('cascade');
                $table->string('challan_no')->unique();
                $table->json('production_ids');
                $table->string('transport_name')->nullable();
                $table->string('vehicle_no')->nullable();
                $table->string('driver_name')->nullable();
                $table->string('lr_no')->nullable();
                $table->date('dispatch_date')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_challans')) {
            Schema::dropIfExists('project_challans');
        }

        Schema::table('project_productions', function (Blueprint $table) {
            if (Schema::hasColumn('project_productions', 'dispatched_at')) {
                $table->dropColumn('dispatched_at');
            }
        });
    }
};

