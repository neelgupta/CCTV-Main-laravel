<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\State;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $statusFilter = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $stateFilter = $request->query('state');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Inquiry::query()->latest();

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (!empty($stateFilter)) {
            $query->where('state', $stateFilter);
        }

        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $inquiries = $query->paginate(20)->withQueryString();

        // Summary metrics
        $counts = [
            'total' => Inquiry::count(),
            'new' => Inquiry::where('status', Inquiry::STATUS_NEW)->count(),
            'contacted' => Inquiry::where('status', Inquiry::STATUS_CONTACTED)->count(),
            'demo_scheduled' => Inquiry::where('status', Inquiry::STATUS_DEMO_SCHEDULED)->count(),
            'converted' => Inquiry::where('status', Inquiry::STATUS_CONVERTED)->count(),
            'closed' => Inquiry::where('status', Inquiry::STATUS_CLOSED)->count(),
        ];

        $statusOptions = Inquiry::statusOptions();
        $states = Inquiry::query()
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'counts' => $counts,
                'inquiries' => $inquiries,
            ]);
        }

        return view('super-admin.inquiries', compact(
            'inquiries',
            'counts',
            'statusOptions',
            'states',
            'statusFilter',
            'search',
            'stateFilter',
            'dateFrom',
            'dateTo'
        ));
    }

    public function updateStatus(Request $request, Inquiry $inquiry)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(Inquiry::statusOptions()))],
        ]);

        $inquiry->update([
            'status' => $request->input('status'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.',
                'status' => $inquiry->status,
                'status_label' => $inquiry->status_label,
                'badge_classes' => $inquiry->status_badge_classes,
            ]);
        }

        return back()->with('success', 'Inquiry status updated successfully.');
    }

    public function updateNotes(Request $request, Inquiry $inquiry)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $inquiry->update([
            'notes' => $request->input('notes'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Notes saved successfully.',
                'notes' => $inquiry->notes,
            ]);
        }

        return back()->with('success', 'Inquiry notes saved successfully.');
    }

    public function destroy(Inquiry $inquiry)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $inquiry->delete();

        return back()->with('success', 'Inquiry deleted successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $statusFilter = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $stateFilter = $request->query('state');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Inquiry::query()->latest();

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (!empty($stateFilter)) {
            $query->where('state', $stateFilter);
        }

        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $filename = 'cctvpro_inquiries_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Name',
                'Mobile Number',
                'Email',
                'Company Name',
                'State',
                'City',
                'Status',
                'Admin Notes',
                'Source',
                'IP Address',
                'Received At'
            ]);

            $query->chunk(200, function ($inquiries) use ($handle) {
                foreach ($inquiries as $inquiry) {
                    fputcsv($handle, [
                        $inquiry->id,
                        $inquiry->name,
                        $inquiry->phone,
                        $inquiry->email ?? '',
                        $inquiry->company_name ?? '',
                        $inquiry->state ?? '',
                        $inquiry->city ?? '',
                        $inquiry->status_label,
                        $inquiry->notes ?? '',
                        $inquiry->source ?? '',
                        $inquiry->ip_address ?? '',
                        $inquiry->created_at ? $inquiry->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function syncToLead(Request $request, Inquiry $inquiry, \App\Services\InquiryLeadSyncService $syncService)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $lead = $syncService->createLeadFromInquiry($inquiry);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => (bool) $lead,
                'message' => $lead ? 'Inquiry successfully synced to company lead.' : 'Failed to sync lead. Target company not found.',
                'lead_id' => $lead?->id,
            ]);
        }

        return back()->with(
            $lead ? 'success' : 'error',
            $lead ? 'Inquiry successfully synced to company lead.' : 'Failed to sync lead. Target company not found.'
        );
    }

    public function syncAll(Request $request, \App\Services\InquiryLeadSyncService $syncService)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $result = $syncService->syncAllInquiries();

        $message = "Sync complete: {$result['created']} new lead(s) created in " . ($result['company'] ?? 'company') . " ({$result['skipped']} skipped/existing).";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return back()->with('success', $message);
    }
}

