<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contract_no')->unique();
            $table->string('customer_name');
            $table->string('project_name');
            $table->string('mobile_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('amc_type', 50);
            $table->string('visit_frequency', 50);
            $table->unsignedSmallInteger('total_visits')->default(0);
            $table->unsignedInteger('total_cameras')->default(0);
            $table->json('camera_types')->nullable();
            $table->string('dvr_nvr_model')->nullable();
            $table->string('hard_disk_size')->nullable();
            $table->text('installation_location')->nullable();
            $table->json('service_scope')->nullable();
            $table->decimal('amc_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_mode', 50)->nullable();
            $table->string('payment_status', 50)->default('pending');
            $table->text('service_timing')->nullable();
            $table->text('parts_replacement_policy')->nullable();
            $table->text('emergency_support_terms')->nullable();
            $table->string('technician_name')->nullable();
            $table->string('technician_contact_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amcs');
    }
};
