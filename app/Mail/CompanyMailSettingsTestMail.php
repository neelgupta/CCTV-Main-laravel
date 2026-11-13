<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyMailSettingsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SMTP Test Email - ' . ($this->company->name ?: 'Company')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company-mail-settings-test',
        );
    }
}
