<?php

namespace App\Modules\Campaign\Providers;

use App\Events\CampaignStatusChanged;
use App\Events\EmailBounceDetected;
use App\Listeners\SendCampaignStatusNotifications;
use App\Listeners\SendEmailBounceNotifications;
use App\Modules\Campaign\Contracts\CampaignRecipientRepositoryInterface;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\Contracts\EmailLogRepositoryInterface;
use App\Modules\Campaign\Repositories\EloquentCampaignRecipientRepository;
use App\Modules\Campaign\Repositories\EloquentCampaignRepository;
use App\Modules\Campaign\Repositories\EloquentEmailLogRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CampaignModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, EloquentCampaignRepository::class);
        $this->app->bind(CampaignRecipientRepositoryInterface::class, EloquentCampaignRecipientRepository::class);
        $this->app->bind(EmailLogRepositoryInterface::class, EloquentEmailLogRepository::class);
    }

    public function boot(): void
    {
        Event::listen(CampaignStatusChanged::class, SendCampaignStatusNotifications::class);
        Event::listen(EmailBounceDetected::class, SendEmailBounceNotifications::class);
    }
}
