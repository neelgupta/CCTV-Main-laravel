<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyMailSetting;
use Illuminate\Mail\MailManager;
use RuntimeException;

class CompanyMailService
{
    public function __construct(
        private readonly MailManager $mailManager,
    ) {
    }

    public function configure(Company $company): CompanyMailSetting
    {
        $company->loadMissing('mailSetting');

        $mailSetting = $company->mailSetting;

        if (! $mailSetting instanceof CompanyMailSetting || ! $mailSetting->is_active) {
            throw new RuntimeException('No active company mail settings found.');
        }

        return $this->configureSetting($mailSetting);
    }

    public function configureSetting(CompanyMailSetting $mailSetting): CompanyMailSetting
    {
        if (! $mailSetting->is_active) {
            throw new RuntimeException('No active company mail settings found.');
        }

        $encryption = $mailSetting->encryption;
        if ($encryption === 'none') {
            $encryption = null;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $mailSetting->host,
            'mail.mailers.smtp.port' => (int) $mailSetting->port,
            'mail.mailers.smtp.username' => $mailSetting->username,
            'mail.mailers.smtp.password' => $mailSetting->password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $mailSetting->from_email,
            'mail.from.name' => $mailSetting->from_name,
        ]);

        $this->mailManager->purge('smtp');
        $this->mailManager->forgetMailers();

        return $mailSetting;
    }
}
