<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Company;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QrComplaintAcknowledgementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Client $client,
        public ?Company $company = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complaint Registered - ' . ($this->ticket->ticket_number ?: 'Ticket')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.qr-complaint-acknowledgement',
        );
    }
}
