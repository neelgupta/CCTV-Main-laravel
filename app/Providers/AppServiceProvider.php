<?php

namespace App\Providers;

use App\Services\DashboardNotificationService;
use App\Services\GlobalSettingService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Ogwebsolutions\FormBuilder\Models\OgForm;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        try {
            if (Schema::hasTable('global_settings')) {
                config()->set('services.google_maps.key', setting('google_maps_api_key'));
                app(GlobalSettingService::class)->applyPaymentGatewayConfig();
                $mailFromAddress = setting('email_from_address');
                $mailFromName = setting('email_from_name');

                if (filled($mailFromAddress)) {
                    config()->set('mail.from.address', $mailFromAddress);
                }

                if (filled($mailFromName)) {
                    config()->set('mail.from.name', $mailFromName);
                }
                $dbSmtpHost = setting('smtp_host');
                $dbSmtpUser = setting('smtp_username');
                $dbSmtpPass = setting('smtp_password');

                if (filled($dbSmtpHost) && filled($dbSmtpUser) && filled($dbSmtpPass)) {
                    config()->set('mail.mailers.smtp.host', $dbSmtpHost);
                    config()->set('mail.mailers.smtp.port', (int) (setting('smtp_port') ?: 587));
                    config()->set('mail.mailers.smtp.username', $dbSmtpUser);
                    config()->set('mail.mailers.smtp.password', $dbSmtpPass);
                    config()->set('mail.mailers.smtp.encryption', setting('smtp_encryption') ?: 'tls');
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        OgForm::creating(function (OgForm $form) {
            if (empty($form->form_key)) {
                $form->form_key = 'quotation';
            }

            $user = auth()->user();
            if ($user && !$user->isSuperAdmin()) {
                $form->company_id = (int) $user->company_id;
            }
        });

        OgForm::updating(function (OgForm $form) {
            $user = auth()->user();
            if ($user && !$user->isSuperAdmin()) {
                $form->company_id = (int) $user->company_id;
                $form->form_key = $form->form_key ?: 'quotation';
            }
        });

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if (! $user) {
                $view->with('layoutNotifications', [
                    'unread_count' => 0,
                    'today_schedule' => collect(),
                    'amc_reminders' => collect(),
                    'blocker_notifications' => collect(),
                    'total_count' => 0,
                    'has_items' => false,
                ]);

                return;
            }

            $view->with('layoutNotifications', app(DashboardNotificationService::class)->buildSidebar($user));
        });
    }
}
