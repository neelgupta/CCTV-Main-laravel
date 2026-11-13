<?php

namespace App\Http\Controllers;

use App\Mail\QrComplaintAcknowledgementMail;
use App\Models\Amc;
use App\Models\Client;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\DashboardNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerQrComplaintController extends Controller
{
    public function show(string $token): View|Response
    {
        $client = $this->resolveClient($token);

        if (! $client) {
            return $this->renderError('Invalid QR Code', 'Invalid QR Code', 404);
        }

        if (! $this->clientIsActive($client)) {
            return $this->renderError('Customer Inactive', 'Customer account is inactive.', 403);
        }

        $client->loadMissing(['company:id,name,logo,print_logo', 'state:id,name', 'city:id,name']);
        $amc = $this->resolveLatestAmc($client);
        $amcRemainingVisits = $amc ? $this->resolveRemainingAmcVisits($amc) : null;
        $publicLink = route('customer.support.qr.show', ['token' => $client->qr_token]);

        return view('customer-support.complaint', [
            'client' => $client,
            'company' => $client->company,
            'amc' => $amc,
            'amcRemainingVisits' => $amcRemainingVisits,
            'publicLink' => $publicLink,
            'qrToken' => $client->qr_token,
        ]);
    }

    public function store(Request $request, string $token): View|Response
    {
        $client = $this->resolveClient($token);

        if (! $client) {
            return $this->renderError('Invalid QR Code', 'Invalid QR Code', 404);
        }

        if (! $this->clientIsActive($client)) {
            return $this->renderError('Customer Inactive', 'Customer account is inactive.', 403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'string', Rule::in(['normal', 'high', 'urgent'])],
            'category' => ['required', 'string', Rule::in($this->complaintCategoryValues())],
            'preferred_visit_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_visit_time' => ['nullable', 'date_format:H:i'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,mp4,mov,avi,mkv'],
        ], [
            'subject.required' => 'Complaint subject is required.',
            'description.required' => 'Complaint description is required.',
            'priority.required' => 'Please select a priority.',
            'category.required' => 'Please select a category.',
            'preferred_visit_date.date' => 'Please select a valid preferred visit date.',
            'preferred_visit_time.date_format' => 'Please select a valid preferred visit time.',
            'attachment.max' => 'Attachment size must not exceed 10 MB.',
            'attachment.mimes' => 'Allowed file types: photo, PDF, or video.',
        ]);

        $priority = $this->normalizePriority((string) $validated['priority']);
        $ticketNumber = Ticket::generateTicketNumber((int) $client->company_id);
        $storedAttachment = null;

        if ($request->hasFile('attachment')) {
            $storedAttachment = $this->storeAttachment(
                $request->file('attachment'),
                (int) $client->company_id,
                $ticketNumber
            );
        }

        try {
            $ticket = DB::transaction(function () use ($client, $validated, $priority, $ticketNumber, $storedAttachment): Ticket {
                $ticket = Ticket::create([
                    'company_id' => (int) $client->company_id,
                    'user_id' => null,
                    'assigned_to' => null,
                    'project_id' => null,
                    'ticket_number' => $ticketNumber,
                    'customer_contact_number' => $client->mobile ?: $client->phone,
                    'customer_name' => $client->name,
                    'customer_email' => $client->email,
                    'customer_address' => $client->address,
                    'type' => 'support',
                    'referenced_by' => null,
                    'subject' => trim((string) $validated['subject']),
                    'priority' => $priority,
                    'status' => 'open',
                    'board_stage' => Ticket::stageForStatus('open', null, null, 0, 'new', (int) $client->company_id),
                    'visit_date' => $validated['preferred_visit_date'] ?? null,
                    'preferred_visit_time' => $validated['preferred_visit_time'] ?? null,
                    'description' => trim((string) $validated['description']),
                    'source_type' => 'qr_complaint',
                    'source_id' => null,
                    'issue_category' => $this->normalizeComplaintCategory((string) $validated['category']),
                    'repeat_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($storedAttachment) {
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'company_id' => (int) $client->company_id,
                        'created_by' => null,
                        'file_path' => $storedAttachment['file_path'],
                        'original_name' => $storedAttachment['original_name'],
                        'mime_type' => $storedAttachment['mime_type'],
                        'file_size' => $storedAttachment['file_size'],
                    ]);
                }

                return $ticket;
            });
        } catch (\Throwable $throwable) {
            if ($storedAttachment && Storage::disk('public')->exists($storedAttachment['file_path'])) {
                Storage::disk('public')->delete($storedAttachment['file_path']);
            }

            throw $throwable;
        }

        app(DashboardNotificationService::class)->notifyQrComplaintCreated($ticket);

        $freshClient = $client->fresh(['company', 'state', 'city']);

        if (filled($client->email)) {
            try {
                Mail::to($client->email)->send(new QrComplaintAcknowledgementMail(
                    $ticket->fresh(),
                    $freshClient,
                    $freshClient?->company
                ));
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }

        return view('customer-support.success', [
            'client' => $freshClient,
            'company' => $freshClient?->company,
            'ticket' => $ticket->fresh(),
            'estimatedResponse' => 'Within 24 working hours',
        ]);
    }

    private function resolveClient(string $token): ?Client
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        // The QR token alone identifies the client. Skip the company scope so the link
        // also works when the browser is logged in to a different company.
        return Client::withoutGlobalScope('company')
            ->with(['company:id,name,logo,print_logo', 'state:id,name', 'city:id,name'])
            ->where('qr_token', $token)
            ->first();
    }

    private function clientIsActive(Client $client): bool
    {
        return strtolower(trim((string) $client->status)) === 'active';
    }

    private function resolveLatestAmc(Client $client): ?Amc
    {
        return Amc::withoutGlobalScope('company')
            ->where('company_id', (int) $client->company_id)
            ->where(function ($query) use ($client) {
                $query->where('client_id', (int) $client->id);

                if (filled($client->name)) {
                    $query->orWhere('customer_name', $client->name);
                }

                if (filled($client->email)) {
                    $query->orWhereRaw('LOWER(email) = ?', [strtolower(trim((string) $client->email))]);
                }

                if (filled($client->mobile)) {
                    $query->orWhere('mobile_number', $client->mobile);
                }

                if (filled($client->phone)) {
                    $query->orWhere('mobile_number', $client->phone);
                }
            })
            ->latest('created_at')
            ->first();
    }

    private function resolveRemainingAmcVisits(Amc $amc): int
    {
        if ($amc->remaining_visits !== null) {
            return max(0, (int) $amc->remaining_visits);
        }

        if (! $amc->generatesScheduledVisits()) {
            return max(0, (int) $amc->total_visits);
        }

        $scheduledVisitCount = $amc->visits()
            ->whereNotNull('visit_date')
            ->count();

        return max(0, (int) $amc->total_visits - $scheduledVisitCount);
    }

    private function normalizePriority(string $priority): string
    {
        return match (strtolower(trim($priority))) {
            'urgent' => 'urgent',
            'high' => 'high',
            default => 'medium',
        };
    }

    /**
     * Keep the complaint portal aligned with the values used by the form
     * while still accepting legacy typos like "compain".
     */
    private function complaintCategoryValues(): array
    {
        return [
            'support',
            'complain',
            'compain',
            'amc',
            'installation',
            'repair',
            'service',
            'other',
        ];
    }

    private function normalizeComplaintCategory(string $category): string
    {
        return match (strtolower(trim($category))) {
            'compain', 'complaint' => 'complain',
            default => strtolower(trim($category)),
        };
    }

    private function storeAttachment($file, int $companyId, string $ticketNumber): array
    {
        $directory = 'company_' . $companyId . '/qr-complaints/' . $ticketNumber;
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeName = 'attachment-' . now()->format('YmdHis') . '-' . uniqid() . ($extension ? '.' . $extension : '');
        $path = $file->storeAs($directory, $safeName, 'public');

        return [
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    private function renderError(string $title, string $message, int $status): Response
    {
        return response()->view('customer-support.error', [
            'title' => $title,
            'message' => $message,
        ], $status);
    }
}
