<?php

namespace App\Mail;

use App\Models\AmcVisit;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AmcVisitAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AmcVisit $visit,
        public Staff $technician
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AMC Visit Assigned - ' . ($this->visit->amc?->contract_no ?: 'AMC')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.amc-visit-assigned',
        );
    }
}
