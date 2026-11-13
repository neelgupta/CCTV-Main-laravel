<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\GlobalSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const SECRET_KEYS = [
        'payment_gateway_secret_key',
        'payment_gateway_webhook_key',
        'payment_gateway_test_secret_key',
        'payment_gateway_test_webhook_key',
        'smtp_password',
    ];

    /**
     * Checkbox settings, stored as '1' / '0'.
     */
    private const BOOLEAN_KEYS = [
        'subscription_recurring_enabled',
        'subscription_trial_requires_mandate',
    ];

    public function __construct(private readonly GlobalSettingService $settings)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $keys = [
            'google_maps_api_key',
            'payment_gateway_public_key',
            'payment_gateway_secret_key',
            'payment_gateway_webhook_key',
            'payment_gateway_mode',
            'payment_gateway_test_public_key',
            'payment_gateway_test_secret_key',
            'payment_gateway_test_webhook_key',
            'subscription_gst_percentage',
            'subscription_recurring_enabled',
            'subscription_recurring_mode',
            'subscription_grace_days',
            'subscription_grace_access',
            'subscription_total_count_monthly',
            'subscription_total_count_yearly',
            'subscription_trial_requires_mandate',
            'subscription_renewal_reminder_days',
            'gst_number',
            'business_name',
            'support_email',
            'mobile_number',
            'support_call_number',
            'support_whatsapp_number',
            'address',
            'email_from_name',
            'email_from_address',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
        ];

        $settings = collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])
            ->all();

        return view('super-admin.settings', [
            'settings' => $settings,
            'secretKeys' => self::SECRET_KEYS,
            'recurringDefaults' => config('subscription.recurring', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        Validator::make(
            $request->all(),
            [
                'google_maps_api_key' => ['nullable', 'string', 'max:255'],
                'payment_gateway_public_key' => ['nullable', 'string', 'max:255'],
                'payment_gateway_secret_key' => ['nullable', 'string', 'max:255'],
                'payment_gateway_webhook_key' => ['nullable', 'string', 'max:255'],
                'payment_gateway_mode' => ['nullable', Rule::in(['live', 'test'])],
                'payment_gateway_test_public_key' => ['nullable', 'string', 'max:255'],
                'payment_gateway_test_secret_key' => ['nullable', 'string', 'max:255'],
                'payment_gateway_test_webhook_key' => ['nullable', 'string', 'max:255'],
                'subscription_gst_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'subscription_recurring_enabled' => ['nullable', 'boolean'],
                'subscription_recurring_mode' => ['nullable', Rule::in(['optional', 'mandatory'])],
                'subscription_grace_days' => ['nullable', 'integer', 'min:0', 'max:30'],
                'subscription_grace_access' => ['nullable', Rule::in(['full', 'read_only'])],
                'subscription_total_count_monthly' => ['nullable', 'integer', 'min:1', 'max:1200'],
                'subscription_total_count_yearly' => ['nullable', 'integer', 'min:1', 'max:100'],
                'subscription_trial_requires_mandate' => ['nullable', 'boolean'],
                'subscription_renewal_reminder_days' => ['nullable', 'integer', 'min:0', 'max:30'],
                'gst_number' => ['nullable', 'string', 'max:50'],
                'business_name' => ['nullable', 'string', 'max:191'],
                'support_email' => ['nullable', 'email', 'max:191'],
                'mobile_number' => ['nullable', 'string', 'max:50'],
                'support_call_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
                'support_whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
                'address' => ['nullable', 'string', 'max:1000'],
                'email_from_name' => ['nullable', 'string', 'max:191'],
                'email_from_address' => ['nullable', 'email', 'max:191'],
                'smtp_host' => ['nullable', 'string', 'max:191'],
                'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'smtp_username' => ['nullable', 'string', 'max:191'],
                'smtp_password' => ['nullable', 'string', 'max:255'],
                'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'null'])],
            ],
            [
                'smtp_port.integer' => 'SMTP port must be a valid number.',
                'smtp_encryption.in' => 'SMTP encryption must be tls, ssl, or null.',
                'payment_gateway_mode.in' => 'Payment gateway mode must be live or test.',
                'subscription_recurring_mode.in' => 'Recurring mode must be optional or mandatory.',
                'subscription_grace_access.in' => 'Grace period access must be full or read only.',
                'support_call_number.regex' => 'Support call number may only contain digits, spaces, dashes, brackets and a leading +.',
                'support_whatsapp_number.regex' => 'Support WhatsApp number may only contain digits, spaces, dashes, brackets and a leading +.',
            ]
        )->validateWithBag('settingsForm');

        foreach ([
            'google_maps_api_key',
            'payment_gateway_public_key',
            'payment_gateway_mode',
            'payment_gateway_test_public_key',
            'subscription_gst_percentage',
            'subscription_recurring_mode',
            'subscription_grace_days',
            'subscription_grace_access',
            'subscription_total_count_monthly',
            'subscription_total_count_yearly',
            'subscription_renewal_reminder_days',
            'gst_number',
            'business_name',
            'support_email',
            'mobile_number',
            'support_call_number',
            'support_whatsapp_number',
            'address',
            'email_from_name',
            'email_from_address',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_encryption',
        ] as $key) {
            $value = $request->input($key);

            if ($key === 'smtp_encryption' && $value === 'null') {
                $value = null;
            }

            $this->settings->put($key, $value, false);
        }

        foreach (self::BOOLEAN_KEYS as $key) {
            $this->settings->put($key, $request->boolean($key) ? '1' : '0', false);
        }

        foreach (self::SECRET_KEYS as $key) {
            if (filled($request->input($key))) {
                $this->settings->put($key, $request->input($key), true);
            }
        }

        return redirect()
            ->route('super-admin.settings.index')
            ->with('success', 'Settings updated successfully.');
    }

}
