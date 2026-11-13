<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 30)->default('normal');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->foreignId('email_template_id')->nullable()->constrained('marketing_email_templates')->nullOnDelete();
            $table->string('whatsapp_template_name')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('batch_size')->default(200);
            $table->unsignedInteger('batch_delay_seconds')->default(30);
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('bounced_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'scheduled_at']);
        });

        Schema::create('festival_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('selected_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('festival_name');
            $table->date('festival_date');
            $table->text('description')->nullable();
            $table->string('greetings_text')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('brand_logo_path')->nullable();
            $table->string('ai_provider', 30)->nullable();
            $table->json('prompt_payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'festival_date']);
        });

        Schema::create('generated_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('festival_campaign_id')->nullable()->constrained('festival_campaigns')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('ai');
            $table->text('prompt_text')->nullable();
            $table->string('file_path');
            $table->string('mime_type', 120)->default('image/svg+xml');
            $table->unsignedInteger('width')->default(1200);
            $table->unsignedInteger('height')->default(628);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('festival_campaign_id')->nullable()->after('whatsapp_template_name')->constrained('festival_campaigns')->nullOnDelete();
            $table->foreignId('generated_banner_id')->nullable()->after('festival_campaign_id')->constrained('generated_banners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('festival_campaign_id');
            $table->dropConstrainedForeignId('generated_banner_id');
        });

        Schema::dropIfExists('generated_banners');
        Schema::dropIfExists('festival_campaigns');
        Schema::dropIfExists('campaigns');
    }
};
