<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('employee_id')->unique();
            $table->string('name');

            // Correctly define role_id column first
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();

            $table->string('department')->nullable();
            $table->date('join_date')->nullable();
            $table->string('contact_number')->nullable();

            // Correctly define company_id column
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            $table->string('profile_photo')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
