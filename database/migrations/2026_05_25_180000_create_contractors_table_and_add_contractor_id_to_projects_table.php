<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors')) {
            Schema::create('contractors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('phone_number', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('referral_name')->nullable();
                $table->enum('commission_type', ['fixed', 'percentage'])->default('fixed');
                $table->decimal('commission_value', 12, 2)->default(0);
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'name']);
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'contractor_id')) {
                $table->foreignId('contractor_id')
                    ->nullable()
                    ->after('lead_id')
                    ->constrained('contractors')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'contractor_id')) {
                $table->dropConstrainedForeignId('contractor_id');
            }
        });

        Schema::dropIfExists('contractors');
    }
};
