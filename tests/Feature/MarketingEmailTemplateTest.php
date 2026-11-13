<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSetupAccess;
use App\Http\Middleware\EnsureSubscriptionWriteAccess;
use App\Http\Middleware\LogUserActivity;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\MarketingEmailSenderConfig;
use App\Models\MarketingEmailSenderGroup;
use App\Models\MarketingEmailTemplate;
use App\Models\MarketingEmailTemplateCategory;
use App\Models\MediaFile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MarketingEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureSetupAccess::class,
            EnsureSubscriptionWriteAccess::class,
            LogUserActivity::class,
            EnsureEmailIsVerified::class,
        ]);
    }

    public function test_company_admin_can_view_email_template_workspace(): void
    {
        [$company, $admin] = $this->companyAdmin();

        $category = MarketingEmailTemplateCategory::create([
            'company_id' => $company->id,
            'name' => 'Invitation',
        ]);

        $template = MarketingEmailTemplate::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'name' => 'Speaker Invite',
            'category' => $category->name,
            'subject' => 'Invitation to join {{conference_name}}',
            'preheader' => 'Warm invite',
            'status' => MarketingEmailTemplate::STATUS_ACTIVE,
            'blocks' => $this->sampleBlocks(),
            'html_content' => '<html><body>Preview</body></html>',
        ]);

        $response = $this->actingAs($admin)->get(route('marketing.email-template'));

        $response->assertOk();
        $response->assertSee('Email Templates');
        $response->assertSee('New Email Template');
        $response->assertSee('Template Assets');
        $response->assertSee('Template Details');
        $response->assertSee('Live Email Preview');
        $response->assertSee('Text Block');
        $response->assertSee('Button Group');
        $response->assertSee('Topics Extra');
        $response->assertSee('Sender Config');
        $response->assertDontSee('Send & Variables');
        $response->assertDontSee('API Configuration');
        $response->assertSee('Speaker Invite');
        $response->assertDontSee('Edit Email Template');
    }

    public function test_company_admin_can_open_dedicated_add_and_edit_pages(): void
    {
        [$company, $admin] = $this->companyAdmin();

        $category = MarketingEmailTemplateCategory::create([
            'company_id' => $company->id,
            'name' => 'Invitation',
        ]);

        $template = MarketingEmailTemplate::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'name' => 'Dedicated Edit Template',
            'category' => $category->name,
            'subject' => 'Invitation to join {{conference_name}}',
            'preheader' => 'Warm invite',
            'status' => MarketingEmailTemplate::STATUS_ACTIVE,
            'blocks' => $this->sampleBlocks(),
            'html_content' => '<html><body>Preview</body></html>',
        ]);

        $addResponse = $this->actingAs($admin)->get(route('marketing.email-template.add'));
        $addResponse->assertOk();
        $addResponse->assertSee('Create Email Template');
        $addResponse->assertSee('Back To List');
        $addResponse->assertSee('Custom Button');
        $addResponse->assertDontSee('Conference Invite Template');
        $addResponse->assertDontSee('Warm invite with quick action buttons and speaker details.');
        $addResponse->assertDontSee('Accept Invitation');
        $addResponse->assertDontSee('Configure WATI');
        $addResponse->assertDontSee('templates available for your marketing workflows.');

        $editResponse = $this->actingAs($admin)->get(route('marketing.email-template.edit', $template));
        $editResponse->assertOk();
        $editResponse->assertSee('Edit Email Template');
        $editResponse->assertSee('Dedicated Edit Template');
        $editResponse->assertSee('Back To List');
    }

    public function test_company_admin_can_create_update_and_delete_template(): void
    {
        [$company, $admin] = $this->companyAdmin();

        $category = MarketingEmailTemplateCategory::create([
            'company_id' => $company->id,
            'name' => 'Marketing / Invitation',
        ]);

        $group = MarketingEmailSenderGroup::create([
            'company_id' => $company->id,
            'name' => 'Organizing Team',
            'sender_name' => 'Org Team',
            'sender_email' => 'org@example.com',
        ]);

        $createResponse = $this->submitForm($admin, route('marketing.email-template.store'), [
            'name' => 'Invite Draft',
            'category_id' => $category->id,
            'subject' => 'Subject line',
            'preheader' => 'Preheader',
            'status' => MarketingEmailTemplate::STATUS_DRAFT,
            'sender_group_id' => $group->id,
            'blocks_json' => json_encode($this->sampleBlocks(), JSON_THROW_ON_ERROR),
        ]);

        $template = MarketingEmailTemplate::query()->where('name', 'Invite Draft')->first();

        $createResponse->assertRedirect(route('marketing.email-template.edit', $template));
        $this->assertNotNull($template);
        $this->assertSame($group->id, $template->sender_group_id);
        $this->assertStringContainsString('Accept Invitation', (string) $template->html_content);

        $updatedBlocks = $this->sampleBlocks();
        $updatedBlocks[0]['content'] = 'Updated content';

        $updateResponse = $this->submitForm($admin, route('marketing.email-template.update', ['marketingEmailTemplate' => $template->id]), [
            '_method' => 'PATCH',
            'name' => 'Invite Final',
            'category_id' => $category->id,
            'subject' => 'Updated subject',
            'preheader' => 'Updated preheader',
            'status' => MarketingEmailTemplate::STATUS_ACTIVE,
            'sender_group_id' => $group->id,
            'blocks_json' => json_encode($updatedBlocks, JSON_THROW_ON_ERROR),
        ]);

        $updateResponse->assertRedirect(route('marketing.email-template.edit', $template));
        $template->refresh();
        $this->assertSame('Invite Final', $template->name);
        $this->assertSame(MarketingEmailTemplate::STATUS_ACTIVE, $template->status);
        $this->assertStringContainsString('Updated content', (string) $template->html_content);

        $deleteResponse = $this->submitForm($admin, route('marketing.email-template.destroy', ['marketingEmailTemplate' => $template->id]), [
            '_method' => 'DELETE',
        ]);
        $deleteResponse->assertRedirect(route('marketing.email-template'));
        $this->assertDatabaseMissing('marketing_email_templates', ['id' => $template->id]);
    }

    public function test_company_admin_can_manage_categories_sender_config_and_sender_groups(): void
    {
        [$company, $admin] = $this->companyAdmin();

        $createCategory = $this->submitForm($admin, route('marketing.email-template.categories.store'), [
            'name' => 'Follow Up',
        ]);
        $createCategory->assertRedirect();

        $category = MarketingEmailTemplateCategory::query()->where('company_id', $company->id)->firstOrFail();

        $updateCategory = $this->submitForm($admin, route('marketing.email-template.categories.update', ['marketingEmailTemplateCategory' => $category->id]), [
            '_method' => 'PATCH',
            'name' => 'Follow Up Updated',
        ]);
        $updateCategory->assertRedirect();

        $senderConfigResponse = $this->submitForm($admin, route('marketing.email-template.sender-config.save'), [
            'sender_email' => 'mailer@example.com',
            'display_name' => 'AICOG 2027',
            'reply_to_email' => 'reply@example.com',
            'cc_email' => 'cc@example.com',
            'bcc_email' => 'bcc@example.com',
        ]);
        $senderConfigResponse->assertRedirect();

        $config = MarketingEmailSenderConfig::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('AICOG 2027', $config->display_name);

        $groupCreate = $this->submitForm($admin, route('marketing.email-template.sender-groups.store'), [
            'name' => 'Scientific Committee',
            'sender_name' => 'Scientific',
            'sender_email' => 'scientific@example.com',
            'cc_email' => 'cc@example.com',
            'bcc_email' => 'bcc@example.com',
        ]);
        $groupCreate->assertRedirect();

        $group = MarketingEmailSenderGroup::query()->where('company_id', $company->id)->firstOrFail();

        $groupUpdate = $this->submitForm($admin, route('marketing.email-template.sender-groups.update', ['marketingEmailSenderGroup' => $group->id]), [
            '_method' => 'PATCH',
            'name' => 'Scientific Committee Updated',
            'sender_name' => 'Scientific Team',
            'sender_email' => 'scientific-team@example.com',
            'cc_email' => 'cc2@example.com',
            'bcc_email' => 'bcc2@example.com',
        ]);
        $groupUpdate->assertRedirect();

        $group->refresh();
        $this->assertSame('Scientific Committee Updated', $group->name);

        $groupDelete = $this->submitForm($admin, route('marketing.email-template.sender-groups.destroy', ['marketingEmailSenderGroup' => $group->id]), [
            '_method' => 'DELETE',
        ]);
        $groupDelete->assertRedirect();
        $this->assertDatabaseMissing('marketing_email_sender_groups', ['id' => $group->id]);

        $categoryDelete = $this->submitForm($admin, route('marketing.email-template.categories.destroy', ['marketingEmailTemplateCategory' => $category->id]), [
            '_method' => 'DELETE',
        ]);
        $categoryDelete->assertRedirect();
        $this->assertDatabaseMissing('marketing_email_template_categories', ['id' => $category->id]);
    }

    public function test_company_admin_can_open_preview_management_and_whatsapp_pages(): void
    {
        [$company, $admin] = $this->companyAdmin();

        $category = MarketingEmailTemplateCategory::create([
            'company_id' => $company->id,
            'name' => 'Invitation',
        ]);

        $template = MarketingEmailTemplate::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'name' => 'Preview Template',
            'category' => $category->name,
            'subject' => 'Preview subject',
            'preheader' => 'Preview preheader',
            'status' => MarketingEmailTemplate::STATUS_ACTIVE,
            'blocks' => $this->sampleBlocks(),
            'html_content' => '<html><body>Preview body</body></html>',
        ]);

        $this->actingAs($admin)
            ->get(route('marketing.email-template.preview', $template))
            ->assertOk()
            ->assertSee('Preview Template')
            ->assertSee('Preview subject');

        $this->actingAs($admin)
            ->get(route('marketing.email-template.categories.page'))
            ->assertOk()
            ->assertSee('Email Template Categories');

        $this->actingAs($admin)
            ->get(route('marketing.email-template.sender-config.page'))
            ->assertOk()
            ->assertSee('Sender Config');

        $this->actingAs($admin)
            ->get(route('marketing.whatsapp'))
            ->assertOk()
            ->assertSee('Send & Variables')
            ->assertSee('API Configuration')
            ->assertSee('WhatsApp Business API')
            ->assertSee('WATI WhatsApp Not Configured');
    }

    public function test_company_admin_can_upload_attachment_for_files_tab(): void
    {
        Storage::fake('public');
        [, $admin] = $this->companyAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->post(route('marketing.email-template.files.store'), [
            '_token' => 'test-token',
            'attachment' => UploadedFile::fake()->create('charater.png', 25, 'image/png'),
        ]);

        $response->assertRedirect();
        $file = MediaFile::query()->latest('id')->first();
        $this->assertNotNull($file);
        $this->assertSame('marketing_email_templates', $file->module);
        $this->assertFalse((bool) $file->gallery_visible);
    }

    public function test_company_admin_can_delete_attachment_from_files_tab(): void
    {
        Storage::fake('public');
        [$company, $admin] = $this->companyAdmin();

        $uploadResponse = $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->post(route('marketing.email-template.files.store'), [
                '_token' => 'test-token',
                'attachment' => UploadedFile::fake()->create('charater.png', 25, 'image/png'),
            ]);

        $uploadResponse->assertRedirect();

        $file = MediaFile::query()
            ->where('company_id', $company->id)
            ->where('module', 'marketing_email_templates')
            ->latest('id')
            ->firstOrFail();

        $deleteResponse = $this->submitForm(
            $admin,
            route('marketing.email-template.files.destroy', $file),
            ['_method' => 'DELETE']
        );

        $deleteResponse->assertRedirect();
        $this->assertSoftDeleted('media_files', ['id' => $file->id]);
    }

    public function test_company_admin_cannot_update_other_company_template(): void
    {
        [$company, $admin] = $this->companyAdmin();
        [$otherCompany, $otherAdmin] = $this->companyAdmin('Second Co', 'second@example.com', 'second-admin@example.com');

        $otherCategory = MarketingEmailTemplateCategory::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Category',
        ]);

        $template = MarketingEmailTemplate::create([
            'company_id' => $otherCompany->id,
            'category_id' => $otherCategory->id,
            'created_by' => $otherAdmin->id,
            'updated_by' => $otherAdmin->id,
            'name' => 'Other Company Template',
            'category' => $otherCategory->name,
            'subject' => 'Subject',
            'preheader' => 'Preheader',
            'status' => MarketingEmailTemplate::STATUS_DRAFT,
            'blocks' => $this->sampleBlocks(),
            'html_content' => '<html></html>',
        ]);

        $response = $this->submitForm($admin, route('marketing.email-template.update', ['marketingEmailTemplate' => $template->id]), [
            '_method' => 'PATCH',
            'name' => 'Attempted Update',
            'category_id' => $otherCategory->id,
            'subject' => 'Blocked',
            'preheader' => 'Blocked',
            'status' => MarketingEmailTemplate::STATUS_DRAFT,
            'blocks_json' => json_encode($this->sampleBlocks(), JSON_THROW_ON_ERROR),
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyAdmin(
        string $companyName = 'Northstar Systems',
        string $companyEmail = 'northstar@example.com',
        string $adminEmail = 'admin@northstar.example.com'
    ): array {
        static $sequence = 1;
        $current = $sequence++;

        $company = Company::create([
            'name' => $companyName,
            'company_code' => strtoupper(substr($companyName, 0, 2)) . '-' . str_pad((string) $current, 4, '0', STR_PAD_LEFT),
            'email' => str_replace('@', '+' . $current . '@', $companyEmail),
            'is_active' => true,
            'is_onboarded' => true,
            'onboarding_step' => 4,
            'address' => '42 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => $companyName . ' Admin',
            'email' => str_replace('@', '+' . $current . '@', $adminEmail),
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth ' . $current,
            'slug' => 'growth-' . $current,
            'price' => 0,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 5,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ]);

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-MKT-' . $current,
            'license_count' => 1,
            'license_keys' => ['LIC-MKT-' . $current],
        ]);

        return [$company, $admin];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sampleBlocks(): array
    {
        return [
            [
                'id' => 'text-1',
                'type' => 'text',
                'label' => 'Intro',
                'summary' => 'Invite copy',
                'content' => 'Dear Dr. John, Greetings from the committee.',
            ],
            [
                'id' => 'button-1',
                'type' => 'button',
                'label' => 'CTA',
                'summary' => 'Response action',
                'text' => 'Accept Invitation',
                'url' => 'https://example.com/accept',
                'bg' => '#0f766e',
                'color' => '#ffffff',
            ],
        ];
    }

    private function submitForm(User $user, string $url, array $payload): TestResponse
    {
        return $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post($url, array_merge(['_token' => 'test-token'], $payload));
    }
}
