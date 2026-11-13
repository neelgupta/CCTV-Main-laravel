<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mailer')->default('smtp');
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('username');
            $table->text('password')->nullable();
            $table->string('encryption', 20)->nullable();
            $table->string('from_email');
            $table->string('from_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_mail_settings');
    }
};
