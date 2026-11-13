<?php

namespace App\Models;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $attributes = [
        'enable_quotation_generator' => true,
        'enable_boq' => true,
        'manual_product_entry' => false,
        'allow_appointment_module' => false,
        'allow_create_company_from_lead' => false,
    ];

    protected $fillable = [
        'name',
        'company_code',
        'email',
        'mobile_number',
        'phone_number',
        'address',
        'latitude',
        'longitude',
        'city',
        'state',
        'timezone',
        'business_type',
        'enable_quotation_generator',
        'enable_boq',
        'manual_product_entry',
        'show_lead_gst_field',
        'show_lead_pan_field',
        'enable_print_settings',
        'print_settings',
        'enable_daily_staff_update',
        'daily_staff_update_time',
        'default_terms_conditions',
        'invoice_terms_conditions',
        'quotation_terms_conditions',
        'quotation_terms',
        'task_master_list',
        'gst_number',
        'pan_number',
        'msme_number',
        'show_msme_number_in_invoice',
        'show_msme_number_in_quotation',
        'registration_number',
        'registration_date',
        'onboarding_step',
        'is_onboarded',
        'lead_source_master_list',
        'lead_custom_fields',
        'project_custom_fields',
        'invoice_prefix',
        'invoice_year',
        'invoice_sequence',
        'has_default_invoice_notes',
        'default_invoice_notes',
        'quotation_prefix',
        'quotation_year',
        'quotation_sequence',
        'challan_prefix',
        'challan_sequence',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'from_address',
        'from_name',
        'to_address',
        'to_name',
        'project_reference_prefix',
        'project_reference_year',
        'project_reference_sequence',
        'working_days',
        'pf_enabled',
        'logo',
        'print_logo',
        'favicon',
        'letterhead_file',
        'storage_addon_mb',
        'allow_growth_staff_tracking',
        'allow_appointment_module',
        'allow_create_company_from_lead',
        'default_lead_assignee_id',
        'appointment_slot_agendas',
        'is_active',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'lead_source_master_list' => 'array',
        'lead_custom_fields' => 'array',
        'project_custom_fields' => 'array',
        'appointment_slot_agendas' => 'array',
        'invoice_year' => 'integer',
        'invoice_sequence' => 'integer',
        'has_default_invoice_notes' => 'boolean',
        'quotation_sequence' => 'integer',
        'quotation_year' => 'integer',
        'challan_sequence' => 'integer',
        'project_reference_year' => 'integer',
        'project_reference_sequence' => 'integer',
        'working_days' => 'array',
        'pf_enabled' => 'boolean',
        'enable_quotation_generator' => 'boolean',
        'enable_boq' => 'boolean',
        'manual_product_entry' => 'boolean',
        'show_lead_gst_field' => 'boolean',
        'show_lead_pan_field' => 'boolean',
        'show_msme_number_in_invoice' => 'boolean',
        'show_msme_number_in_quotation' => 'boolean',
        'enable_print_settings' => 'boolean',
        'print_settings' => 'array',
        'enable_daily_staff_update' => 'boolean',
        'quotation_terms' => 'array',
        'task_master_list' => 'array',
        'is_onboarded' => 'boolean',
        'registration_date' => 'date',
        'storage_addon_mb' => 'integer',
        'allow_growth_staff_tracking' => 'boolean',
        'allow_appointment_module' => 'boolean',
        'allow_create_company_from_lead' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            if (blank($company->company_code)) {
                $company->company_code = static::generateUniqueCompanyCode();
            }
        });

        static::deleting(function (Company $company): void {
            DB::table('users')
                ->where('company_id', $company->id)
                ->delete();
        });

        static::created(function (Company $company): void {
            // Every new company gets the same starter demo records.
            app(DemoDataSeeder::class)->seedCompany($company);
        });
    }

    public static function generateUniqueCompanyCode(): string
    {
        do {
            $code = 'CC-' . Str::upper(Str::random(6));
        } while (DB::table('companies')->where('company_code', $code)->exists());

        return $code;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function defaultLeadAssignee()
    {
        return $this->belongsTo(User::class, 'default_lead_assignee_id');
    }

    public function isInternalStaffOrAdminEmail(?string $email): bool
    {
        if (! filled($email)) {
            return false;
        }

        $normalizedEmail = Str::lower(trim((string) $email));

        if (Str::lower(trim((string) $this->email)) === $normalizedEmail) {
            return true;
        }

        return User::query()
            ->with('staff')
            ->where('company_id', $this->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->get()
            ->contains(fn (User $user) => $user->isStaffOrAdmin());
    }

    public function mailSetting(): HasOne
    {
        return $this->hasOne(CompanyMailSetting::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function appointments()
    {
        return $this->hasMany(LeadAppointment::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function units()
    {
        return $this->hasMany(CompanyUnit::class)->orderBy('name');
    }

    public function gstRates()
    {
        return $this->hasMany(CompanyGstRate::class)->orderBy('rate');
    }

    public function contractors()
    {
        return $this->hasMany(Contractor::class)->orderBy('name');
    }

    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class)->latestOfMany();
    }

    public function storageAddonMb(): int
    {
        return (int) max(0, (int) ($this->storage_addon_mb ?? 0));
    }

    public function currentStorageBaseMb(): int
    {
        return (int) max(1, (int) ($this->activeSubscription?->plan?->planStorageLimitMb() ?? SubscriptionPlan::storageLimitMb()));
    }

    public function currentStorageTotalMb(): int
    {
        return (int) $this->totalStorageLimitMb($this->currentStorageBaseMb());
    }

    public function currentStorageBaseLabel(): string
    {
        $baseMb = $this->currentStorageBaseMb();

        return $baseMb >= 1024
            ? number_format($baseMb / 1024, 1) . ' GB'
            : number_format($baseMb) . ' MB';
    }

    public function currentStorageAddonLabel(): string
    {
        $addonMb = $this->storageAddonMb();

        return $addonMb > 0
            ? number_format($addonMb) . ' MB'
            : 'No add-on';
    }

    public function currentStorageTotalLabel(): string
    {
        $totalMb = $this->currentStorageTotalMb();

        return $totalMb >= 1024
            ? number_format($totalMb / 1024, 1) . ' GB'
            : number_format($totalMb) . ' MB';
    }

    public function defaultTermsConditionsText(): string
    {
        $terms = trim((string) ($this->default_terms_conditions ?? ''));

        if ($terms !== '') {
            return $terms;
        }

        $legacyTerms = $this->quotation_terms;

        if (! is_array($legacyTerms)) {
            return '';
        }

        return collect([
            'Warranty' => trim((string) ($legacyTerms['warranty'] ?? '')),
            'Payment Terms' => trim((string) ($legacyTerms['payment'] ?? '')),
            'Delivery Timeline' => trim((string) ($legacyTerms['delivery'] ?? '')),
        ])
            ->filter(fn (string $value) => $value !== '')
            ->map(fn (string $value, string $label) => $label . ': ' . $value)
            ->values()
            ->implode("\n");
    }

    public function defaultTermsConditionsHtml(): string
    {
        $terms = trim((string) ($this->default_terms_conditions ?? ''));

        if ($terms === '') {
            return '';
        }

        if (! Str::contains($terms, '<')) {
            return nl2br(e($terms));
        }

        $sanitized = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\\1\s*>/is', '', $terms) ?? $terms;
        $sanitized = strip_tags($sanitized, '<p><br><strong><b><em><i><u><ul><ol><li><a><div><span>');
        $sanitized = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/<(p|div|li)[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/\1>/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(<(?:p|div|li)[^>]*>)(?:\s|&nbsp;|<br\s*\/?>)+/i', '$1', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br><br>', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s{2,}/', ' ', $sanitized) ?? $sanitized;

        return $sanitized;
    }

    public function invoiceTermsConditionsText(): string
    {
        $terms = trim((string) ($this->invoice_terms_conditions ?? ''));

        if ($terms !== '') {
            return $terms;
        }

        return $this->defaultTermsConditionsText();
    }

    public function invoiceTermsConditionsHtml(): string
    {
        $terms = trim((string) ($this->invoice_terms_conditions ?? ''));

        if ($terms === '') {
            return $this->defaultTermsConditionsHtml();
        }

        if (! Str::contains($terms, '<')) {
            return nl2br(e($terms));
        }

        $sanitized = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\\1\s*>/is', '', $terms) ?? $terms;
        $sanitized = strip_tags($sanitized, '<p><br><strong><b><em><i><u><ul><ol><li><a><div><span>');
        $sanitized = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/<(p|div|li)[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/\1>/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(<(?:p|div|li)[^>]*>)(?:\s|&nbsp;|<br\s*\/?>)+/i', '$1', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br><br>', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s{2,}/', ' ', $sanitized) ?? $sanitized;

        return $sanitized;
    }

    public function quotationTermsConditionsText(): string
    {
        $terms = trim((string) ($this->quotation_terms_conditions ?? ''));

        if ($terms !== '') {
            return $terms;
        }

        return $this->defaultTermsConditionsText();
    }

    public function quotationTermsConditionsHtml(): string
    {
        $terms = trim((string) ($this->quotation_terms_conditions ?? ''));

        if ($terms === '') {
            return $this->defaultTermsConditionsHtml();
        }

        if (! Str::contains($terms, '<')) {
            return nl2br(e($terms));
        }

        $sanitized = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\\1\s*>/is', '', $terms) ?? $terms;
        $sanitized = strip_tags($sanitized, '<p><br><strong><b><em><i><u><ul><ol><li><a><div><span>');
        $sanitized = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/<(p|div|li)[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/\1>/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(<(?:p|div|li)[^>]*>)(?:\s|&nbsp;|<br\s*\/?>)+/i', '$1', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br><br>', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s{2,}/', ' ', $sanitized) ?? $sanitized;

        return $sanitized;
    }

    public function totalStorageLimitMb(int $baseStorageLimitMb): int
    {
        return (int) max(1, $baseStorageLimitMb + $this->storageAddonMb());
    }

    public function activeSubscription()
    {
        return $this->hasOne(CompanySubscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->latestOfMany();
    }

    public function activeSubscriptions()
    {
        $today = now()->toDateString();

        return $this->hasMany(CompanySubscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($query) use ($today) {
                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    public function currentOrLatestSubscription(): ?CompanySubscription
    {
        return $this->activeSubscription ?? $this->latestSubscription;
    }

    public function hasCompletedOnboarding(): bool
    {
        if ($this->is_onboarded) {
            return true;
        }

        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($query) {
                $today = now()->toDateString();

                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($query) {
                $today = now()->toDateString();

                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->exists();
    }

    public function requiresOnboardingFlow(): bool
    {
        return ! $this->hasCompletedOnboarding();
    }

    public function hasRequiredProfileDetails(): bool
    {
        return filled(trim((string) $this->address))
            && filled(trim((string) $this->city))
            && filled(trim((string) $this->state));
    }

    public function normalizedLeadSources(?string $currentSource = null): array
    {
        $usedKeys = [];

        return collect($this->lead_source_master_list ?? [])
            ->map(function ($value, $index) use (&$usedKeys) {
                if (is_array($value)) {
                    $name = Str::squish((string) ($value['name'] ?? ''));
                    $keySource = Str::squish((string) ($value['key'] ?? ''));

                    return $name === '' ? null : [
                        'key' => $this->ensureUniqueLeadSourceKey(
                            $keySource !== '' ? $keySource : $name,
                            $usedKeys,
                            $index
                        ),
                        'name' => $name,
                        'show_referral_person_name' => (bool) ($value['show_referral_person_name'] ?? false),
                    ];
                }

                $name = Str::squish((string) $value);

                return $name === '' ? null : [
                    'key' => $this->ensureUniqueLeadSourceKey($name, $usedKeys, $index),
                    'name' => $name,
                    'show_referral_person_name' => false,
                ];
            })
            ->when(filled($currentSource), function (Collection $sources) use ($currentSource) {
                $sources->push([
                    'key' => null,
                    'name' => Str::squish((string) $currentSource),
                    'show_referral_person_name' => false,
                ]);
            })
            ->filter(fn ($source) => filled($source['name'] ?? null))
            ->unique(fn ($source) => Str::lower($source['name']))
            ->values()
            ->map(function (array $source, int $index) use (&$usedKeys) {
                if (blank($source['key'] ?? null)) {
                    $source['key'] = $this->ensureUniqueLeadSourceKey($source['name'], $usedKeys, $index);
                }

                $usedKeys[] = Str::lower(trim((string) $source['key']));

                return $source;
            })
            ->all();
    }

    public function leadSourceNames(?string $currentSource = null): array
    {
        return collect($this->normalizedLeadSources($currentSource))
            ->pluck('name')
            ->values()
            ->all();
    }

    private function ensureUniqueLeadSourceKey(string $keySource, array &$usedKeys, int $index): string
    {
        $baseKey = Str::of($keySource)->slug('_')->toString();

        if ($baseKey === '') {
            $baseKey = 'lead_source_' . ((int) $index + 1);
        }

        $candidate = $baseKey;
        $suffix = 2;
        $normalizedUsedKeys = array_map(
            fn (string $value) => Str::lower(trim($value)),
            array_filter($usedKeys, fn ($value) => filled($value))
        );

        while (in_array(Str::lower($candidate), $normalizedUsedKeys, true)) {
            $candidate = $baseKey . '_' . $suffix++;
        }

        $usedKeys[] = $candidate;

        return $candidate;
    }

    public function normalizedLeadCustomFields(): array
    {
        $usedKeys = [];

        return collect($this->lead_custom_fields ?? [])
            ->map(function ($value, $index) use (&$usedKeys) {
                if (! is_array($value)) {
                    return null;
                }

                $label = Str::squish((string) ($value['label'] ?? ''));
                $type = strtolower(Str::squish((string) ($value['type'] ?? 'text')));
                $required = (bool) ($value['required'] ?? false);
                $showInFilters = (bool) ($value['show_in_filters'] ?? false);
                $showInBoqQuotation = (bool) ($value['show_in_boq_quotation'] ?? false);
                $placeholder = Str::squish((string) ($value['placeholder'] ?? ''));
                $helpText = Str::squish((string) ($value['help_text'] ?? ''));
                $options = $this->normalizeLeadCustomFieldOptions($value['options'] ?? []);
                $keySource = Str::squish((string) ($value['key'] ?? ''));

                if ($label === '') {
                    return null;
                }

                $key = $this->ensureUniqueLeadCustomFieldKey(
                    $keySource !== '' ? $keySource : $label,
                    $usedKeys,
                    $index
                );

                return [
                    'key' => $key,
                    'label' => $label,
                    'type' => $this->normalizeLeadCustomFieldType($type),
                    'required' => $required,
                    'show_in_filters' => $showInFilters,
                    'show_in_boq_quotation' => $showInBoqQuotation,
                    'options' => $options,
                    'placeholder' => $placeholder !== '' ? $placeholder : null,
                    'help_text' => $helpText !== '' ? $helpText : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function leadCustomFieldDefinitions(): array
    {
        return $this->normalizedLeadCustomFields();
    }

    public function leadCustomFieldsForQuotation(): array
    {
        return collect($this->normalizedLeadCustomFields())
            ->filter(fn (array $field) => (bool) ($field['show_in_boq_quotation'] ?? false))
            ->values()
            ->all();
    }

    public function normalizedProjectCustomFields(): array
    {
        $usedKeys = [];

        return collect($this->project_custom_fields ?? [])
            ->map(function ($value, $index) use (&$usedKeys) {
                if (! is_array($value)) {
                    return null;
                }

                $label = Str::squish((string) ($value['label'] ?? ''));
                $type = strtolower(Str::squish((string) ($value['type'] ?? 'text')));
                $required = (bool) ($value['required'] ?? false);
                $showInFilters = (bool) ($value['show_in_filters'] ?? false);
                $placeholder = Str::squish((string) ($value['placeholder'] ?? ''));
                $helpText = Str::squish((string) ($value['help_text'] ?? ''));
                $options = $this->normalizeProjectCustomFieldOptions($value['options'] ?? []);
                $keySource = Str::squish((string) ($value['key'] ?? ''));

                if ($label === '') {
                    return null;
                }

                $key = $this->ensureUniqueProjectCustomFieldKey(
                    $keySource !== '' ? $keySource : $label,
                    $usedKeys,
                    $index
                );

                return [
                    'key' => $key,
                    'label' => $label,
                    'type' => $this->normalizeProjectCustomFieldType($type),
                    'required' => $required,
                    'show_in_filters' => $showInFilters,
                    'options' => $options,
                    'placeholder' => $placeholder !== '' ? $placeholder : null,
                    'help_text' => $helpText !== '' ? $helpText : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function projectCustomFieldDefinitions(): array
    {
        return $this->normalizedProjectCustomFields();
    }

    public function projectCustomFieldKeys(): array
    {
        return collect($this->normalizedProjectCustomFields())
            ->pluck('key')
            ->values()
            ->all();
    }

    public function projectCustomFieldDefinitionByKey(string $key): ?array
    {
        return collect($this->normalizedProjectCustomFields())
            ->first(fn (array $field) => Str::lower($field['key']) === Str::lower(trim($key)));
    }

    public function normalizeProjectCustomFieldType(string $type): string
    {
        return in_array($type, [
            'text',
            'textarea',
            'email',
            'number',
            'date',
            'dropdown',
            'checkbox',
            'radiobutton',
        ], true) ? $type : 'text';
    }

    public function projectCustomFieldTypes(): array
    {
        return [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'email' => 'Email',
            'number' => 'Number',
            'date' => 'Date',
            'dropdown' => 'Dropdown',
            'checkbox' => 'Checkbox',
            'radiobutton' => 'Radio Button',
        ];
    }

    public function leadCustomFieldKeys(): array
    {
        return collect($this->normalizedLeadCustomFields())
            ->pluck('key')
            ->values()
            ->all();
    }

    public function leadCustomFieldDefinitionByKey(string $key): ?array
    {
        return collect($this->normalizedLeadCustomFields())
            ->first(fn (array $field) => Str::lower($field['key']) === Str::lower(trim($key)));
    }

    public function normalizeLeadCustomFieldType(string $type): string
    {
        return in_array($type, [
            'text',
            'textarea',
            'email',
            'number',
            'date',
            'dropdown',
            'checkbox',
            'radiobutton',
        ], true) ? $type : 'text';
    }

    public function leadCustomFieldTypes(): array
    {
        return [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'email' => 'Email',
            'number' => 'Number',
            'date' => 'Date',
            'dropdown' => 'Dropdown',
            'checkbox' => 'Checkbox',
            'radiobutton' => 'Radio Button',
        ];
    }

    private function normalizeLeadCustomFieldOptions(mixed $options): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n|,/', $options);
        }

        return collect(is_array($options) ? $options : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => Str::lower($value))
            ->values()
            ->all();
    }

    private function normalizeProjectCustomFieldOptions(mixed $options): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n|,/', $options);
        }

        return collect(is_array($options) ? $options : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => Str::lower($value))
            ->values()
            ->all();
    }

    private function ensureUniqueProjectCustomFieldKey(string $keySource, array &$usedKeys, int $index): string
    {
        $baseKey = Str::of($keySource)->trim()->lower()->slug('_')->toString();
        $baseKey = $baseKey !== '' ? $baseKey : 'field_' . ($index + 1);
        $candidate = $baseKey;
        $suffix = 2;

        while (in_array($candidate, $usedKeys, true)) {
            $candidate = $baseKey . '_' . $suffix++;
        }

        $usedKeys[] = $candidate;

        return $candidate;
    }

    private function ensureUniqueLeadCustomFieldKey(string $keySource, array &$usedKeys, int $index): string
    {
        $baseKey = Str::of($keySource)->trim()->lower()->slug('_')->toString();
        $baseKey = $baseKey !== '' ? $baseKey : 'field_' . ($index + 1);
        $candidate = $baseKey;
        $suffix = 2;

        while (in_array($candidate, $usedKeys, true)) {
            $candidate = $baseKey . '_' . $suffix++;
        }

        $usedKeys[] = $candidate;

        return $candidate;
    }

    public function normalizedTaskMasterList(?string $currentTask = null): array
    {
        return collect($this->task_master_list ?? [])
            ->map(function ($value) {
                if (is_array($value)) {
                    $name = Str::squish((string) ($value['name'] ?? ''));

                    return $name === '' ? null : [
                        'name' => $name,
                    ];
                }

                $name = Str::squish((string) $value);

                return $name === '' ? null : [
                    'name' => $name,
                ];
            })
            ->when(filled($currentTask), function (Collection $tasks) use ($currentTask) {
                $tasks->push([
                    'name' => Str::squish((string) $currentTask),
                ]);
            })
            ->filter(fn ($task) => filled($task['name'] ?? null))
            ->unique(fn ($task) => Str::lower($task['name']))
            ->values()
            ->all();
    }

    public function taskMasterNames(?string $currentTask = null): array
    {
        return collect($this->normalizedTaskMasterList($currentTask))
            ->pluck('name')
            ->values()
            ->all();
    }

    public function leadSourceShowsReferralPersonName(?string $sourceName): bool
    {
        if (! filled($sourceName)) {
            return false;
        }

        return collect($this->normalizedLeadSources())
            ->contains(fn ($source) => Str::lower($source['name']) === Str::lower(Str::squish((string) $sourceName))
                && (bool) ($source['show_referral_person_name'] ?? false));
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? route('company.asset', ['type' => 'logo', 'v' => $this->updated_at?->timestamp ?? time()]) : null;
    }

    public function getPrintLogoUrlAttribute(): ?string
    {
        return $this->print_logo ? route('company.asset', ['type' => 'print-logo', 'v' => $this->updated_at?->timestamp ?? time()]) : null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon ? route('company.asset', ['type' => 'favicon', 'v' => $this->updated_at?->timestamp ?? time()]) : null;
    }

    public function getLetterheadUrlAttribute(): ?string
    {
        return $this->letterhead_file ? route('company.asset', ['type' => 'letterhead', 'v' => $this->updated_at?->timestamp ?? time()]) : null;
    }

    public function hasLetterhead(): bool
    {
        return filled($this->letterhead_file);
    }

    public function getEffectivePrintSettingsAttribute(): array
    {
        if (!$this->enable_print_settings) {
            return [];
        }

        $settings = is_array($this->print_settings) ? $this->print_settings : [];

        return array_merge([
            'accent_color' => '#4f46e5',
            'page_margin_top' => '20',
            'page_margin_right' => '20',
            'page_margin_bottom' => '20',
            'page_margin_left' => '20',
            'use_letterhead' => true,
            'show_quotation_logo' => true,
            'letterhead_title' => $this->name,
            'letterhead_subtitle' => 'Official Document',
            'field_positions' => [],
        ], $settings);
    }

    public function hasLetterheadFieldPositions(): bool
    {
        return filled(data_get($this->print_settings, 'field_positions'));
    }

    public function shouldShowPrintLogo(): bool
    {
        $settings = is_array($this->print_settings) ? $this->print_settings : [];

        return (bool) data_get($settings, 'show_quotation_logo', true);
    }

    public function shouldShowQuotationLogo(): bool
    {
        return $this->shouldShowPrintLogo();
    }

    public function shouldHidePrintHeader(): bool
    {
        return (bool) $this->enable_print_settings;
    }

    public function dailyStaffUpdateTimeLabel(): ?string
    {
        $time = trim((string) ($this->daily_staff_update_time ?? ''));

        if ($time === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($time)->format('h:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    public function usesManualProductEntry(): bool
    {
        return (bool) $this->manual_product_entry;
    }

    public function formatInvoiceNumbering(?int $sequence = null): ?string
    {
        return $this->formatDocumentNumberingFromParts(
            trim((string) $this->invoice_prefix),
            $this->invoice_year,
            $sequence ?? $this->invoice_sequence
        );
    }

    public function formatQuotationNumbering(?string $source = 'quotation', ?int $sequence = null): string
    {
        $prefix = trim((string) $this->quotation_prefix);
        $year = $this->quotation_year ? (string) $this->quotation_year : null;
        $sequenceValue = $sequence ?? $this->quotation_sequence;
        $sequence = $sequenceValue ? (string) $sequenceValue : null;

        $parts = array_values(array_filter([$prefix, $year, $sequence], fn ($value) => filled($value)));

        if (! empty($parts)) {
            return implode('-', $parts);
        }

        return $source === 'boq' ? 'BOQ' : 'Quotation';
    }

    public function usesQuotationNumbering(): bool
    {
        return filled($this->quotation_prefix) || filled($this->quotation_year) || filled($this->quotation_sequence);
    }

    public function quotationVersionExists(string $version): bool
    {
        $normalizedVersion = trim($version);

        if ($normalizedVersion === '' || ! $this->id) {
            return false;
        }

        return LeadQuotation::query()
            ->whereHas('lead', function ($query): void {
                $query->where('company_id', $this->id);
            })
            ->whereRaw('TRIM(version) = ?', [$normalizedVersion])
            ->exists();
    }

    public function nextAvailableQuotationSequence(?int $startSequence = null): int
    {
        $sequence = max(1, $startSequence ?? (int) ($this->quotation_sequence ?? 0));
        $attempts = 0;

        while ($attempts < 100000) {
            $candidateVersion = $this->formatQuotationNumbering('quotation', $sequence);

            if (! $this->quotationVersionExists($candidateVersion)) {
                return $sequence;
            }

            $sequence++;
            $attempts++;
        }

        return $sequence;
    }

    public function nextAvailableQuotationVersion(?int $startSequence = null): string
    {
        return $this->formatQuotationNumbering('quotation', $this->nextAvailableQuotationSequence($startSequence));
    }

    public function reserveNextQuotationVersion(?int $startSequence = null): array
    {
        return DB::transaction(function () use ($startSequence): array {
            $lockedCompany = static::query()
                ->lockForUpdate()
                ->findOrFail($this->id);

            $sequence = $lockedCompany->nextAvailableQuotationSequence($startSequence);
            $version = $lockedCompany->formatQuotationNumbering('quotation', $sequence);

            $lockedCompany->syncQuotationSequence($sequence + 1);

            return [
                'version' => $version,
                'sequence' => $sequence,
                'next_sequence' => $sequence + 1,
            ];
        });
    }

    public function syncQuotationSequence(int $nextSequence): void
    {
        if ($nextSequence <= 0 || $this->quotation_sequence >= $nextSequence) {
            return;
        }

        $this->forceFill([
            'quotation_sequence' => $nextSequence,
        ])->saveQuietly();
    }

    public function formatChallanNumber(int $sequence, ?string $prefix = null): string
    {
        $prefix = trim((string) ($prefix ?? $this->challan_prefix)) ?: 'CHL';

        return sprintf('%s-%04d', $prefix, max(1, $sequence));
    }

    public function challanNumberExists(string $challanNo): bool
    {
        return ProjectChallan::query()
            ->where('company_id', $this->id)
            ->where('challan_no', $challanNo)
            ->exists();
    }

    public function nextAvailableChallanSequence(?int $startSequence = null, ?string $prefix = null): int
    {
        $sequence = max(1, $startSequence ?? (int) ($this->challan_sequence ?? 0));
        $attempts = 0;

        while ($attempts < 100000 && $this->challanNumberExists($this->formatChallanNumber($sequence, $prefix))) {
            $sequence++;
            $attempts++;
        }

        return $sequence;
    }

    public function reserveNextChallanNumber(): string
    {
        return DB::transaction(function (): string {
            $lockedCompany = static::query()
                ->lockForUpdate()
                ->findOrFail($this->id);

            $sequence = $lockedCompany->nextAvailableChallanSequence();

            $lockedCompany->forceFill([
                'challan_sequence' => $sequence + 1,
            ])->saveQuietly();

            $this->challan_sequence = $sequence + 1;

            return $lockedCompany->formatChallanNumber($sequence);
        });
    }

    public function formatDocumentNumbering(string $documentType, ?int $sequence = null): ?string
    {
        return match ($documentType) {
            'invoice' => $this->formatInvoiceNumbering($sequence),
            'quotation' => $this->formatQuotationNumbering('quotation', $sequence),
            default => null,
        };
    }

    public function allocateDocumentNumber(string $documentType, ?int $startSequence = null): ?array
    {
        $sequenceField = match ($documentType) {
            'invoice' => 'invoice_sequence',
            'quotation' => 'quotation_sequence',
            default => null,
        };

        if ($sequenceField === null) {
            return null;
        }

        $sequence = max(1, $startSequence ?? (int) ($this->{$sequenceField} ?? 0));
        $attempts = 0;

        while ($attempts < 100000) {
            $documentNumber = $this->formatDocumentNumbering($documentType, $sequence);

            if ($documentNumber === null) {
                return null;
            }

            if (! TicketDocument::query()
                ->where('company_id', $this->getKey())
                ->where('document_number', $documentNumber)
                ->exists()) {
                return [
                    'number' => $documentNumber,
                    'sequence' => $sequence,
                ];
            }

            $sequence++;
            $attempts++;
        }

        return null;
    }

    public function nextAvailableDocumentNumber(string $documentType, ?int $startSequence = null): ?string
    {
        $allocation = $this->allocateDocumentNumber($documentType, $startSequence);

        return $allocation['number'] ?? null;
    }

    public function incrementDocumentNumberSequence(string $documentType): void
    {
        $sequenceField = match ($documentType) {
            'invoice' => 'invoice_sequence',
            'quotation' => 'quotation_sequence',
            default => null,
        };

        if ($sequenceField === null) {
            return;
        }

        $currentSequence = (int) ($this->{$sequenceField} ?? 0);
        if ($currentSequence <= 0) {
            return;
        }

        $this->forceFill([
            $sequenceField => $currentSequence + 1,
        ])->saveQuietly();
    }

    public function syncDocumentNumberSequence(string $documentType, int $nextSequence): void
    {
        $sequenceField = match ($documentType) {
            'invoice' => 'invoice_sequence',
            'quotation' => 'quotation_sequence',
            default => null,
        };

        if ($sequenceField === null || $nextSequence <= 0) {
            return;
        }

        $currentSequence = (int) ($this->{$sequenceField} ?? 0);

        if ($currentSequence >= $nextSequence) {
            return;
        }

        $this->forceFill([
            $sequenceField => $nextSequence,
        ])->saveQuietly();
    }

    private function formatDocumentNumberingFromParts(string $prefix, mixed $year, mixed $sequence): ?string
    {
        $prefix = trim($prefix);
        $year = (int) $year;
        $sequence = (int) $sequence;

        if ($prefix === '' || $year <= 0 || $sequence <= 0) {
            return null;
        }

        return $prefix . '/' . $year . '/' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function extractDocumentNumberPrefix(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('/^(.*?)(\d+)$/', $value, $matches)) {
            return '';
        }

        return $matches[1];
    }

    private function extractDocumentNumberSuffix(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('/^(.*?)(\d+)$/', $value, $matches)) {
            return '';
        }

        return $matches[2];
    }

    private function extractDocumentNumberPadding(string $value): int
    {
        $suffix = $this->extractDocumentNumberSuffix($value);

        return $suffix !== '' ? strlen($suffix) : 0;
    }

    public function quotationTemplates()
    {
        return $this->hasMany(QuotationTemplate::class);
    }
}
