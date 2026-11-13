<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\DailyStaffUpdateReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyStaffUpdateReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public DailyStaffUpdateReport $report
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daily Staff Update Report - ' . optional($this->report->report_date)->format('d M Y')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-staff-update-report',
        );
    }
}
