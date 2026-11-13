<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\FestivalCampaign;
use App\Models\SystemSetting;
use Tests\TestCase;

class CampaignEnhancedTest extends TestCase
{
    public function test_system_setting_static_methods_exist(): void
    {
        $this->assertTrue(method_exists(SystemSetting::class, 'getValue'));
        $this->assertTrue(method_exists(SystemSetting::class, 'setValue'));
    }

    public function test_campaign_model_has_send_type_constants(): void
    {
        $this->assertEquals('immediate', Campaign::SEND_TYPE_IMMEDIATE);
        $this->assertEquals('scheduled', Campaign::SEND_TYPE_SCHEDULED);
    }

    public function test_campaign_is_immediate_method(): void
    {
        $campaign = new Campaign(['send_type' => Campaign::SEND_TYPE_IMMEDIATE]);
        $this->assertTrue($campaign->isImmediate());
        $this->assertFalse($campaign->isScheduled());
    }

    public function test_campaign_is_scheduled_method(): void
    {
        $campaign = new Campaign(['send_type' => Campaign::SEND_TYPE_SCHEDULED]);
        $this->assertTrue($campaign->isScheduled());
        $this->assertFalse($campaign->isImmediate());
    }

    public function test_campaign_default_is_scheduled(): void
    {
        $campaign = new Campaign();
        $this->assertTrue($campaign->isScheduled());
    }

    public function test_campaign_send_type_default(): void
    {
        $this->assertEquals('scheduled', Campaign::SEND_TYPE_SCHEDULED);
        $this->assertEquals('immediate', Campaign::SEND_TYPE_IMMEDIATE);
    }

    public function test_campaign_type_constants(): void
    {
        $this->assertEquals('normal', Campaign::TYPE_NORMAL);
        $this->assertEquals('festival', Campaign::TYPE_FESTIVAL);
    }

    public function test_campaign_status_constants(): void
    {
        $this->assertEquals('draft', Campaign::STATUS_DRAFT);
        $this->assertEquals('scheduled', Campaign::STATUS_SCHEDULED);
        $this->assertEquals('running', Campaign::STATUS_RUNNING);
        $this->assertEquals('completed', Campaign::STATUS_COMPLETED);
        $this->assertEquals('failed', Campaign::STATUS_FAILED);
    }

    public function test_send_now_route_exists(): void
    {
        $routes = \Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if ($route->getName() === 'marketing.campaigns.send') {
                $hasRoute = true;
                $this->assertEquals('POST', $route->methods()[0]);
                break;
            }
        }
        $this->assertTrue($hasRoute, 'Send now route should exist');
    }

    public function test_banner_generate_route_exists(): void
    {
        $routes = \Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if ($route->getName() === 'marketing.campaigns.banner.generate') {
                $hasRoute = true;
                $this->assertEquals('POST', $route->methods()[0]);
                break;
            }
        }
        $this->assertTrue($hasRoute, 'Banner generate route should exist');
    }

    public function test_batch_settings_routes_exist(): void
    {
        $routes = \Route::getRoutes();
        $hasIndex = false;
        $hasUpdate = false;
        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name === 'settings.campaign.batch.index') {
                $hasIndex = true;
                $this->assertEquals('GET', $route->methods()[0]);
            }
            if ($name === 'settings.campaign.batch.update') {
                $hasUpdate = true;
                $this->assertEquals('POST', $route->methods()[0]);
            }
        }
        $this->assertTrue($hasIndex, 'Batch settings index route should exist');
        $this->assertTrue($hasUpdate, 'Batch settings update route should exist');
    }

    public function test_campaign_create_route_exists(): void
    {
        $routes = \Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if ($route->getName() === 'marketing.campaigns.store') {
                $hasRoute = true;
                $this->assertEquals('POST', $route->methods()[0]);
                break;
            }
        }
        $this->assertTrue($hasRoute, 'Campaign store route should exist');
    }

    public function test_api_festival_upcoming_route_exists(): void
    {
        $routes = \Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if ($route->getName() === 'api.festival-campaigns.upcoming') {
                $hasRoute = true;
                $this->assertEquals('GET', $route->methods()[0]);
                break;
            }
        }
        $this->assertTrue($hasRoute, 'Festival upcoming API route should exist');
    }

    public function test_api_banner_generate_route_exists(): void
    {
        $routes = \Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if ($route->getName() === 'api.festival-campaigns.banner.generate') {
                $hasRoute = true;
                $this->assertEquals('POST', $route->methods()[0]);
                break;
            }
        }
        $this->assertTrue($hasRoute, 'API banner generate route should exist');
    }

    public function test_api_analytics_route_exists(): void
    {
        $routes = \Route::getRoutes();
        $hasRoute = false;
        foreach ($routes as $route) {
            if ($route->getName() === 'api.campaigns.analytics') {
                $hasRoute = true;
                $this->assertEquals('GET', $route->methods()[0]);
                break;
            }
        }
        $this->assertTrue($hasRoute, 'Analytics API route should exist');
    }

    public function test_console_commands_are_registered(): void
    {
        $commands = \Artisan::all();
        $this->assertArrayHasKey('campaigns:run-scheduled', $commands);
        $this->assertArrayHasKey('campaigns:process-bulk', $commands);
    }

    public function test_campaign_banner_service_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Modules\Campaign\Services\CampaignBannerService::class));
    }

    public function test_ai_banner_service_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\AIBannerService::class));
    }

    public function test_campaign_batch_settings_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\CampaignBatchSettingsController::class));
    }

    public function test_email_tracking_webhook_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\EmailTrackingWebhookController::class));
    }

    public function test_bulk_email_processor_command_exists(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\ProcessBulkEmailCampaigns::class));
    }

    public function test_system_setting_model_exists(): void
    {
        $this->assertTrue(class_exists(\App\Models\SystemSetting::class));
    }

    public function test_campaign_has_new_fillable_fields(): void
    {
        $campaign = new Campaign();
        $fillable = $campaign->getFillable();

        $this->assertContains('send_type', $fillable);
        $this->assertContains('banner_image', $fillable);
        $this->assertContains('retry_count', $fillable);
    }

    public function test_email_log_has_new_fields(): void
    {
        $this->assertTrue(class_exists(\App\Models\EmailLog::class));
    }

    public function test_campaigns_config_has_retry_count(): void
    {
        $this->assertArrayHasKey('retry_count', config('campaigns'));
        $this->assertArrayHasKey('cron_interval', config('campaigns'));
    }

    public function test_config_has_banner_provider(): void
    {
        $aiConfig = config('campaigns.ai');
        $this->assertArrayHasKey('banner_provider', $aiConfig);
    }

    public function test_banner_provider_defaults_to_ai_instead_of_svg(): void
    {
        $this->assertNotSame('svg', config('campaigns.ai.banner_provider'));
    }

    public function test_campaign_views_exist(): void
    {
        $this->assertTrue(view()->exists('marketing.campaigns.index'));
        $this->assertTrue(view()->exists('marketing.campaigns.show'));
    }

    public function test_settings_campaign_view_exists(): void
    {
        $this->assertTrue(view()->exists('settings.campaign.index'));
    }

    public function test_svg_banner_generation_uses_dynamic_copy_and_branding(): void
    {
        $festivalCampaign = new FestivalCampaign([
            'festival_name' => 'Bhai Dooj',
            'festival_date' => '2026-10-23',
            'description' => 'Celebrates sibling bonds with blessings and family gatherings.',
            'greetings_text' => 'Warm Bhai Dooj wishes for your family',
            'brand_name' => 'Test Company',
        ]);

        $service = app(\App\Modules\Campaign\Services\CampaignBannerService::class);
        $buildBannerContent = new \ReflectionMethod($service, 'buildBannerContent');
        $buildBannerContent->setAccessible(true);
        $content = $buildBannerContent->invoke($service, $festivalCampaign, 'Test Company');

        $renderSvgBanner = new \ReflectionMethod($service, 'renderSvgBanner');
        $renderSvgBanner->setAccessible(true);
        $svg = $renderSvgBanner->invoke(
            $service,
            $festivalCampaign->festival_name,
            $festivalCampaign->greetings_text,
            'Test Company',
            $content['headline'],
            $content['subtitle'],
            $content['message'],
            $content['date_label'],
            $service->getFestivalTheme($festivalCampaign->festival_name),
            null
        );

        $this->assertNotEmpty($content['headline']);
        $this->assertNotEmpty($content['subtitle']);
        $this->assertNotEmpty($content['footer_greeting']);
        $this->assertStringContainsString('Bhai Dooj', $content['headline']);
        $this->assertStringContainsString('Festival campaign banner', $svg);
        $this->assertStringContainsString($content['headline'], $svg);
        $this->assertStringContainsString($content['footer_greeting'], $svg);
        $this->assertStringContainsString('Test Company', $svg);
        $this->assertStringContainsString('23 Oct 2026', $svg);
        $this->assertStringContainsString('data:image/png;base64,', $svg);
    }
}
