<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignBatchSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;

        $settings = [
            'email_batch_size' => SystemSetting::getValue('email_batch_size', 200, $companyId),
            'cron_interval' => SystemSetting::getValue('cron_interval', 'everyMinute', $companyId),
            'retry_count' => SystemSetting::getValue('retry_count', 3, $companyId),
            'batch_delay_seconds' => SystemSetting::getValue('batch_delay_seconds', 30, $companyId),
            'email_provider' => SystemSetting::getValue('email_provider', 'smtp', $companyId),
        ];

        return view('settings.campaign.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'email_batch_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'cron_interval' => ['required', 'string', 'in:everyMinute,everyFiveMinutes,everyTenMinutes,everyFifteenMinutes,everyThirtyMinutes'],
            'retry_count' => ['required', 'integer', 'min:0', 'max:20'],
            'batch_delay_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'email_provider' => ['required', 'string', 'in:smtp,sendgrid,ses,mailgun,postmark'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::setValue($key, $value, $companyId);
        }

        return redirect()->route('settings.campaign.batch.index')
            ->with('success', 'Campaign batch settings updated successfully.');
    }
}
