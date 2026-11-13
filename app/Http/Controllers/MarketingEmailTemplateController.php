<?php

namespace App\Http\Controllers;

use App\Models\CompanyIntegration;
use App\Models\MarketingEmailSenderConfig;
use App\Models\MarketingEmailSenderGroup;
use App\Models\MarketingEmailTemplate;
use App\Models\MarketingEmailTemplateCategory;
use App\Models\MediaFile;
use App\Services\MarketingEmailTemplateRenderer;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarketingEmailTemplateController extends Controller
{
    public function index(Request $request, ?MarketingEmailTemplate $marketingEmailTemplate = null): View
    {
        $companyId = $this->companyId($request);
        $isBuilderPage = $request->routeIs('marketing.email-template.add', 'marketing.email-template.edit');

        $templates = MarketingEmailTemplate::query()
            ->where('company_id', $companyId)
            ->with(['categoryRecord:id,name', 'senderGroup:id,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $selectedTemplate = null;

        if ($marketingEmailTemplate) {
            $this->ensureTemplateAccess($marketingEmailTemplate, $companyId);
            $selectedTemplate = $marketingEmailTemplate->loadMissing(['categoryRecord:id,name', 'senderGroup:id,name']);
        }

        $categories = MarketingEmailTemplateCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $categoriesTable = MarketingEmailTemplateCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(10, ['*'], 'categories_page')
            ->withQueryString();

        $senderConfig = $this->senderConfig($companyId);
        $senderGroups = MarketingEmailSenderGroup::query()
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        $senderGroupsTable = MarketingEmailSenderGroup::query()
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(10, ['*'], 'sender_groups_page')
            ->withQueryString();

        $attachments = $this->attachments($companyId);

        return view('marketing.email-template', [
            'templates' => $templates,
            'categories' => $categories,
            'categoriesTable' => $categoriesTable,
            'senderConfig' => $senderConfig,
            'senderGroups' => $senderGroups,
            'senderGroupsTable' => $senderGroupsTable,
            'isBuilderPage' => $isBuilderPage,
            'builderConfig' => [
                'showBuilder' => $isBuilderPage || $request->boolean('create'),
                'initialTemplate' => $this->templatePayload($selectedTemplate),
                'templates' => $templates->getCollection()->map(fn (MarketingEmailTemplate $template) => $this->templatePayload($template))->values()->all(),
                'categories' => $categories->map(fn (MarketingEmailTemplateCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values()->all(),
                'quickTemplates' => $this->quickTemplates(),
                'libraryBlocks' => $this->libraryBlocks(),
                'mediaFiles' => $attachments,
                'senderConfig' => [
                    'sender_email' => $senderConfig->sender_email,
                    'display_name' => $senderConfig->display_name,
                    'reply_to_email' => $senderConfig->reply_to_email,
                    'cc_email' => $senderConfig->cc_email,
                    'bcc_email' => $senderConfig->bcc_email,
                ],
                'senderGroups' => $senderGroups->map(fn (MarketingEmailSenderGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sender_name' => $group->sender_name,
                    'sender_email' => $group->sender_email,
                    'cc_email' => $group->cc_email,
                    'bcc_email' => $group->bcc_email,
                ])->values()->all(),
                'formActions' => [
                    'store' => route('marketing.email-template.store'),
                    'updateBase' => route('marketing.email-template.update', ['marketingEmailTemplate' => '__ID__']),
                    'uploadFile' => route('marketing.email-template.files.store'),
                ],
            ],
        ]);
    }

    public function whatsappPage(Request $request): View
    {
        $companyId = $this->companyId($request);

        return view('marketing.whatsapp', [
            'watiConfig' => $this->watiConfig($companyId, $request->query('tab', 'send')),
        ]);
    }

    public function preview(Request $request, MarketingEmailTemplate $marketingEmailTemplate): View
    {
        $companyId = $this->companyId($request);
        $this->ensureTemplateAccess($marketingEmailTemplate, $companyId);

        return view('marketing.email-template-preview', [
            'template' => $marketingEmailTemplate,
        ]);
    }

    public function categoriesPage(Request $request): View
    {
        $companyId = $this->companyId($request);

        return view('marketing.email-template-categories', [
            'categories' => MarketingEmailTemplateCategory::query()
                ->where('company_id', $companyId)
                ->withCount('templates')
                ->orderBy('name')
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function senderConfigPage(Request $request): View
    {
        $companyId = $this->companyId($request);

        return view('marketing.email-template-sender-config', [
            'senderConfig' => $this->senderConfig($companyId),
            'senderGroups' => MarketingEmailSenderGroup::query()
                ->where('company_id', $companyId)
                ->latest()
                ->paginate(12, ['*'], 'sender_groups_page')
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, MarketingEmailTemplateRenderer $renderer): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();
        $data = $this->validatedData($request, $companyId);
        $data['company_id'] = $companyId;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['html_content'] = $renderer->render($data['blocks'], $data['preheader'] ?? null);

        $template = MarketingEmailTemplate::create($data);
        $this->saveTemplateSenderConfig($request, $companyId);

        return redirect()
            ->route('marketing.email-template.edit', $template)
            ->with('success', 'Email template created successfully.');
    }

    public function update(Request $request, MarketingEmailTemplate $marketingEmailTemplate, MarketingEmailTemplateRenderer $renderer): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $this->ensureTemplateAccess($marketingEmailTemplate, $companyId);

        $data = $this->validatedData($request, $companyId);
        $data['updated_by'] = $request->user()->id;
        $data['html_content'] = $renderer->render($data['blocks'], $data['preheader'] ?? null);

        $marketingEmailTemplate->update($data);
        $this->saveTemplateSenderConfig($request, $companyId);

        return redirect()
            ->route('marketing.email-template.edit', $marketingEmailTemplate)
            ->with('success', 'Email template updated successfully.');
    }

    public function destroy(Request $request, MarketingEmailTemplate $marketingEmailTemplate): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $this->ensureTemplateAccess($marketingEmailTemplate, $companyId);

        $marketingEmailTemplate->delete();

        return redirect()
            ->route('marketing.email-template')
            ->with('success', 'Email template deleted successfully.');
    }

    public function uploadFile(Request $request): RedirectResponse|JsonResponse
    {
        $companyId = $this->companyId($request);

        $request->validate([
            'attachment' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt|max:10240',
        ]);

        $path = app(MediaUploadService::class)->store(
            $request->file('attachment'),
            'company_' . $companyId . '/marketing-email-templates',
            'public'
        );

        MediaFile::query()
            ->where('company_id', $companyId)
            ->where('path', $path)
            ->latest('id')
            ->limit(1)
            ->update([
                'module' => 'marketing_email_templates',
                'gallery_visible' => false,
            ]);

        $file = MediaFile::query()
            ->where('company_id', $companyId)
            ->where('path', $path)
            ->latest('id')
            ->first();

        if ($request->expectsJson()) {
            $dimensions = null;
            if ($file && str_starts_with((string) $file->mime_type, 'image/')) {
                $absolutePath = Storage::disk((string) ($file->disk ?: 'public'))->path((string) $file->path);
                if (is_file($absolutePath)) {
                    $size = @getimagesize($absolutePath);
                    if (is_array($size)) {
                        $dimensions = [
                            'width' => (int) ($size[0] ?? 0),
                            'height' => (int) ($size[1] ?? 0),
                        ];
                    }
                }
            }

            return response()->json([
                'message' => 'Attachment uploaded successfully.',
                'file' => $file ? [
                    'id' => $file->id,
                    'name' => $file->original_name ?: basename((string) $file->path),
                    'meta' => trim(implode(' • ', array_filter([
                        $file->mime_type ? strtoupper((string) $file->mime_type) : null,
                        $file->size ? number_format(((int) $file->size) / 1024, 1) . ' KB' : null,
                    ]))),
                    'url' => route('settings.gallery.open', $file),
                    'delete_url' => route('marketing.email-template.files.destroy', $file),
                    'dimensions' => $dimensions,
                ] : null,
            ]);
        }

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    public function destroyFile(Request $request, MediaFile $mediaFile): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_if((int) $mediaFile->company_id !== $companyId, 403);
        abort_if((string) $mediaFile->module !== 'marketing_email_templates', 404);

        app(MediaUploadService::class)->delete(
            (string) $mediaFile->path,
            (string) ($mediaFile->disk ?: 'public'),
            $companyId
        );

        return back()->with('success', 'Attachment deleted successfully.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('marketing_email_template_categories', 'name')->where('company_id', $companyId),
            ],
        ]);

        MarketingEmailTemplateCategory::create([
            'company_id' => $companyId,
            'name' => $data['name'],
        ]);

        return back()
            ->with('success', 'Email template category added successfully.');
    }

    public function updateCategory(Request $request, MarketingEmailTemplateCategory $marketingEmailTemplateCategory): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_if($marketingEmailTemplateCategory->company_id !== $companyId, 403);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('marketing_email_template_categories', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($marketingEmailTemplateCategory->id),
            ],
        ]);

        $marketingEmailTemplateCategory->update($data);

        MarketingEmailTemplate::query()
            ->where('category_id', $marketingEmailTemplateCategory->id)
            ->update(['category' => $data['name']]);

        return back()
            ->with('success', 'Email template category updated successfully.');
    }

    public function destroyCategory(Request $request, MarketingEmailTemplateCategory $marketingEmailTemplateCategory): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_if($marketingEmailTemplateCategory->company_id !== $companyId, 403);

        MarketingEmailTemplate::query()
            ->where('category_id', $marketingEmailTemplateCategory->id)
            ->update([
                'category_id' => null,
                'category' => $marketingEmailTemplateCategory->name,
            ]);

        $marketingEmailTemplateCategory->delete();

        return back()
            ->with('success', 'Email template category deleted successfully.');
    }

    public function saveSenderConfig(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'sender_email' => 'nullable|email|max:191',
            'display_name' => 'nullable|string|max:191',
            'reply_to_email' => 'nullable|email|max:191',
            'cc_email' => 'nullable|string|max:500',
            'bcc_email' => 'nullable|string|max:500',
        ]);

        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_EMAIL_SMTP)
            ->first();

        MarketingEmailSenderConfig::updateOrCreate(
            ['company_id' => $companyId],
            array_merge($data, [
                'is_connected' => (bool) ($integration?->is_enabled && $integration?->isConnected()),
                'connected_at' => $integration?->last_tested_at,
            ])
        );

        return back()
            ->with('success', 'Sender configuration saved successfully.');
    }

    public function storeSenderGroup(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $this->validateSenderGroup($request);

        MarketingEmailSenderGroup::create(array_merge($data, [
            'company_id' => $companyId,
        ]));

        return back()
            ->with('success', 'Sender group added successfully.');
    }

    public function updateSenderGroup(Request $request, MarketingEmailSenderGroup $marketingEmailSenderGroup): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_if($marketingEmailSenderGroup->company_id !== $companyId, 403);

        $marketingEmailSenderGroup->update($this->validateSenderGroup($request));

        return back()
            ->with('success', 'Sender group updated successfully.');
    }

    public function destroySenderGroup(Request $request, MarketingEmailSenderGroup $marketingEmailSenderGroup): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_if($marketingEmailSenderGroup->company_id !== $companyId, 403);

        MarketingEmailTemplate::query()
            ->where('sender_group_id', $marketingEmailSenderGroup->id)
            ->update(['sender_group_id' => null]);

        $marketingEmailSenderGroup->delete();

        return back()
            ->with('success', 'Sender group deleted successfully.');
    }

    public function saveWatiConfig(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'active_tab' => 'nullable|in:send,api',
            'provider' => 'nullable|string|max:50',
            'api_url' => 'nullable|string|max:500',
            'access_token' => 'nullable|string|max:2000',
            'account_sid' => 'nullable|string|max:255',
            'auth_token' => 'nullable|string|max:2000',
            'from_number' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:2000',
            'app_name' => 'nullable|string|max:255',
            'source_number' => 'nullable|string|max:255',
            'interakt_api_key' => 'nullable|string|max:2000',
            'base_url' => 'nullable|string|max:500',
            'waba_id' => 'nullable|string|max:255',
            'jwt_token' => 'nullable|string|max:4000',
            'phone_number_id' => 'nullable|string|max:255',
            'webhook_url' => 'nullable|string|max:500',
            'generic_auth_token' => 'nullable|string|max:2000',
        ]);

        $provider = $data['provider'] ?? 'WATI';
        $isConfigured = match ($provider) {
            'WATI' => filled($data['api_url'] ?? null) && filled($data['access_token'] ?? null),
            'Twilio' => filled($data['account_sid'] ?? null) && filled($data['auth_token'] ?? null) && filled($data['from_number'] ?? null),
            'Gupshup' => filled($data['api_key'] ?? null) && filled($data['app_name'] ?? null) && filled($data['source_number'] ?? null),
            'Interakt' => filled($data['interakt_api_key'] ?? null),
            'WebGlowPro' => filled($data['base_url'] ?? null) && filled($data['waba_id'] ?? null) && filled($data['jwt_token'] ?? null) && filled($data['phone_number_id'] ?? null),
            default => filled($data['webhook_url'] ?? null) && filled($data['generic_auth_token'] ?? null),
        };

        CompanyIntegration::updateOrCreate(
            [
                'company_id' => $companyId,
                'type' => CompanyIntegration::TYPE_WHATSAPP_WATI,
            ],
            [
                'connection_mode' => CompanyIntegration::MODE_MANUAL,
                'is_enabled' => $isConfigured,
                'credentials' => [
                    'provider' => $provider,
                    'api_url' => $data['api_url'] ?? null,
                    'access_token' => $data['access_token'] ?? null,
                    'account_sid' => $data['account_sid'] ?? null,
                    'auth_token' => $data['auth_token'] ?? null,
                    'from_number' => $data['from_number'] ?? null,
                    'api_key' => $data['api_key'] ?? null,
                    'app_name' => $data['app_name'] ?? null,
                    'source_number' => $data['source_number'] ?? null,
                    'interakt_api_key' => $data['interakt_api_key'] ?? null,
                    'base_url' => $data['base_url'] ?? null,
                    'waba_id' => $data['waba_id'] ?? null,
                    'jwt_token' => $data['jwt_token'] ?? null,
                    'phone_number_id' => $data['phone_number_id'] ?? null,
                    'webhook_url' => $data['webhook_url'] ?? null,
                    'generic_auth_token' => $data['generic_auth_token'] ?? null,
                ],
                'test_status' => $isConfigured ? 'success' : null,
                'last_tested_at' => $isConfigured ? now() : null,
            ]
        );

        return redirect()
            ->route('marketing.whatsapp', ['tab' => $data['active_tab'] ?? 'send'])
            ->with('success', 'WATI configuration saved successfully.');
    }

    private function companyId(Request $request): int
    {
        $user = $request->user() ?? auth()->user();
        $companyId = (int) $user?->company_id;
        abort_if($companyId <= 0, 403);

        return $companyId;
    }

    private function ensureTemplateAccess(MarketingEmailTemplate $template, int $companyId): void
    {
        abort_if((int) $template->company_id !== $companyId, 403);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function libraryBlocks(): array
    {
        return [
            ['type' => 'text', 'label' => 'Text Block', 'description' => 'Rich text for greetings, body copy, and session details.'],
            ['type' => 'image', 'label' => 'Image', 'description' => 'Hero banner or logo-driven block with hosted image URL.'],
            ['type' => 'button_group', 'label' => 'Button Group', 'description' => 'Two side-by-side actions such as Accept and Maybe Later.'],
            ['type' => 'custom_button', 'label' => 'Custom Button', 'description' => 'Single CTA with editable text, link, and colors.'],
            ['type' => 'topics', 'label' => 'Topics', 'description' => 'Highlight key topics or agenda items in a clean list.'],
            ['type' => 'topics_extra', 'label' => 'Topics Extra', 'description' => 'Extended topic list with supporting notes or session details.'],
            ['type' => 'divider', 'label' => 'Divider', 'description' => 'Soft separator between sections.'],
            ['type' => 'spacer', 'label' => 'Spacer', 'description' => 'Adds breathing room between blocks.'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function quickTemplates(): array
    {
        return [
            ['key' => 'invitation', 'name' => 'Invitation Template', 'description' => 'Greeting, body copy, CTA buttons, and closing.'],
            ['key' => 'session-details', 'name' => 'Session Details', 'description' => 'Structured section for day, time, hall, and topic.'],
            ['key' => 'signature', 'name' => 'Signature Block', 'description' => 'Warm closing with committee or team name.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachments(int $companyId): array
    {
        return MediaFile::query()
            ->where('company_id', $companyId)
            ->where('module', 'marketing_email_templates')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (MediaFile $media) {
                return [
                    'id' => $media->id,
                    'name' => $media->original_name ?: basename((string) $media->path),
                    'meta' => trim(implode(' • ', array_filter([
                        $media->mime_type ? strtoupper((string) $media->mime_type) : null,
                        $media->size ? number_format(((int) $media->size) / 1024, 1) . ' KB' : null,
                    ]))),
                    'url' => route('settings.gallery.open', $media),
                    'delete_url' => route('marketing.email-template.files.destroy', $media),
                ];
            })
            ->values()
            ->all();
    }

    private function senderConfig(int $companyId): MarketingEmailSenderConfig
    {
        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_EMAIL_SMTP)
            ->first();

        $senderEmail = $integration?->credentials['from_email'] ?? $integration?->credentials['username'] ?? null;
        $displayName = $integration?->credentials['from_name'] ?? null;

        return MarketingEmailSenderConfig::firstOrCreate(
            ['company_id' => $companyId],
            [
                'sender_email' => $senderEmail,
                'display_name' => $displayName,
                'reply_to_email' => $senderEmail,
                'is_connected' => (bool) ($integration?->is_enabled && $integration?->isConnected()),
                'connected_at' => $integration?->last_tested_at,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function watiConfig(int $companyId, string $activeTab = 'send'): array
    {
        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_WHATSAPP_WATI)
            ->first();

        $credentials = Arr::wrap($integration?->credentials);

        return [
            'provider' => $credentials['provider'] ?? 'WATI',
            'api_url' => $credentials['api_url'] ?? '',
            'access_token' => $credentials['access_token'] ?? '',
            'account_sid' => $credentials['account_sid'] ?? '',
            'auth_token' => $credentials['auth_token'] ?? '',
            'from_number' => $credentials['from_number'] ?? '',
            'api_key' => $credentials['api_key'] ?? '',
            'app_name' => $credentials['app_name'] ?? '',
            'source_number' => $credentials['source_number'] ?? '',
            'interakt_api_key' => $credentials['interakt_api_key'] ?? '',
            'base_url' => $credentials['base_url'] ?? 'https://app.webglowpro.com',
            'waba_id' => $credentials['waba_id'] ?? '',
            'jwt_token' => $credentials['jwt_token'] ?? '',
            'phone_number_id' => $credentials['phone_number_id'] ?? '',
            'webhook_url' => $credentials['webhook_url'] ?? '',
            'generic_auth_token' => $credentials['generic_auth_token'] ?? '',
            'is_configured' => (bool) ($integration?->is_enabled),
            'active_tab' => $activeTab,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(?MarketingEmailTemplate $template): array
    {
        if (! $template) {
            return [
                'id' => null,
                'name' => 'Invite Topic - No Extra Topic Change',
                'category' => 'Uncategorized',
                'category_id' => null,
                'subject' => 'Email subject... Use {{variables}}',
                'preheader' => '',
                'status' => MarketingEmailTemplate::STATUS_ACTIVE,
                'sender_group_id' => null,
                'blocks' => [],
                'html_content' => '',
            ];
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'category' => $template->category,
            'category_id' => $template->category_id,
            'subject' => $template->subject ?? '',
            'preheader' => $template->preheader ?? '',
            'status' => $template->status,
            'sender_group_id' => $template->sender_group_id,
            'blocks' => $template->blocks ?? [],
            'html_content' => $template->html_content ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, int $companyId): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => [
                'nullable',
                Rule::exists('marketing_email_template_categories', 'id')->where('company_id', $companyId),
            ],
            'subject' => 'nullable|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'status' => 'required|in:' . MarketingEmailTemplate::STATUS_DRAFT . ',' . MarketingEmailTemplate::STATUS_ACTIVE,
            'sender_group_id' => [
                'nullable',
                Rule::exists('marketing_email_sender_groups', 'id')->where('company_id', $companyId),
            ],
            'blocks_json' => 'required|string',
        ]);

        $blocks = json_decode((string) $validated['blocks_json'], true);
        if (! is_array($blocks) || $blocks === []) {
            throw ValidationException::withMessages([
                'blocks_json' => 'Please add at least one block to the email template.',
            ]);
        }

        validator(['blocks' => $blocks], [
            'blocks' => 'required|array|min:1',
            'blocks.*.id' => 'required|string|max:255',
            'blocks.*.type' => 'required|string|in:text,image,button,button_group,custom_button,topics,topics_extra,divider,spacer',
            'blocks.*.label' => 'nullable|string|max:255',
            'blocks.*.summary' => 'nullable|string|max:1000',
            'blocks.*.content' => 'nullable|string',
            'blocks.*.url' => 'nullable|string|max:2000',
            'blocks.*.alt' => 'nullable|string|max:255',
            'blocks.*.text' => 'nullable|string|max:255',
            'blocks.*.title' => 'nullable|string|max:255',
            'blocks.*.description' => 'nullable|string|max:2000',
            'blocks.*.items' => 'nullable|array|max:12',
            'blocks.*.items.*' => 'nullable|string|max:255',
            'blocks.*.buttons' => 'nullable|array|max:4',
            'blocks.*.buttons.*.text' => 'nullable|string|max:255',
            'blocks.*.buttons.*.url' => 'nullable|string|max:2000',
            'blocks.*.buttons.*.bg' => 'nullable|string|max:30',
            'blocks.*.buttons.*.color' => 'nullable|string|max:30',
            'blocks.*.bg' => 'nullable|string|max:30',
            'blocks.*.color' => 'nullable|string|max:30',
            'blocks.*.weight' => 'nullable|integer|min:1|max:6',
            'blocks.*.height' => 'nullable|integer|min:4|max:480',
        ])->validate();

        $category = null;
        if (! empty($validated['category_id'])) {
            $category = MarketingEmailTemplateCategory::query()
                ->where('company_id', $companyId)
                ->find($validated['category_id']);
        }

        return [
            'name' => $validated['name'],
            'category_id' => $category?->id,
            'category' => $category?->name ?? 'Uncategorized',
            'subject' => $validated['subject'] ?? null,
            'preheader' => $validated['preheader'] ?? null,
            'status' => $validated['status'],
            'sender_group_id' => $validated['sender_group_id'] ?? null,
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSenderGroup(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'sender_name' => 'nullable|string|max:191',
            'sender_email' => 'nullable|email|max:191',
            'cc_email' => 'nullable|string|max:500',
            'bcc_email' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function saveTemplateSenderConfig(Request $request, int $companyId): void
    {
        $data = $request->validate([
            'reply_to_email' => 'nullable|email|max:191',
            'cc_email' => 'nullable|string|max:500',
            'bcc_email' => 'nullable|string|max:500',
        ]);

        $senderConfig = $this->senderConfig($companyId);
        $senderConfig->update([
            'reply_to_email' => $data['reply_to_email'] ?? null,
            'cc_email' => $data['cc_email'] ?? null,
            'bcc_email' => $data['bcc_email'] ?? null,
        ]);
    }
}
