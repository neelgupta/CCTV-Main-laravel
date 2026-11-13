<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('razorpay_key_id')->nullable()->after('password');
            $table->string('razorpay_secret')->nullable()->after('razorpay_key_id');
            $table->string('razorpay_currency')->nullable()->after('razorpay_secret');
            $table->string('razorpay_public_key')->nullable()->after('razorpay_currency');
            $table->boolean('razorpay_verify_ssl')->default(false)->after('razorpay_public_key');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'razorpay_key_id',
                'razorpay_secret',
                'razorpay_currency',
                'razorpay_public_key',
                'razorpay_verify_ssl',
            ]);
        });
    }
};
