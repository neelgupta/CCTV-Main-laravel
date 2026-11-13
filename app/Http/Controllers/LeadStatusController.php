<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadStatusController extends Controller
{
    public function index()
    {
        $companyId = (int) auth()->user()->company_id;
        LeadStatus::ensureNewLead($companyId);
        LeadStatus::ensureProposalSent($companyId);
        LeadStatus::ensureWon($companyId);
        LeadStatus::ensureLoss($companyId);
        LeadStatus::normalizeSystemStages($companyId);
        $statuses = LeadStatus::orderBy('order')->get();
        return view('settings.statuses.index', compact('statuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|string|max:7',
        ]);

        $companyId = (int) auth()->user()->company_id;
        LeadStatus::ensureNewLead($companyId);
        LeadStatus::ensureProposalSent($companyId);
        LeadStatus::ensureWon($companyId);
        LeadStatus::ensureLoss($companyId);
        LeadStatus::normalizeSystemStages($companyId);

        $requestedName = Str::lower(trim((string) $request->name));
        if (in_array($requestedName, ['won', 'new lead', 'fresh lead', 'proposal sent', 'loss'], true)) {
            return back()->with('error', 'New Lead, Proposal Sent, Loss, and Won are required system stages and already exist.');
        }

        $maxOrder = LeadStatus::where('company_id', $companyId)->max('order') ?? 0;

        LeadStatus::create([
            'company_id' => $companyId,
            'name' => $request->name,
            'color' => $request->color,
            'order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Status added.');
    }

    public function update(Request $request, LeadStatus $status)
    {
        abort_unless($status->company_id === auth()->user()->company_id, 403);

        $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|string|max:7',
        ]);

        if ($status->isSystemStage()) {
            return back()->with('error', 'This is a required system stage and cannot be modified.');
        }

        $status->update([
            'name' => $request->name,
            'color' => $request->color,
        ]);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(LeadStatus $status)
    {
        abort_unless($status->company_id === auth()->user()->company_id, 403);

        if ($status->isSystemStage()) {
            return back()->with('error', 'This is a required system stage and cannot be deleted.');
        }

        if ($status->leads()->exists()) {
            return back()->with('error', 'Cannot delete status because it has leads.');
        }

        $status->delete();

        return back()->with('success', 'Status deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:lead_statuses,id',
        ]);

        $companyId = (int) auth()->user()->company_id;
        LeadStatus::ensureNewLead($companyId);
        LeadStatus::ensureProposalSent($companyId);
        LeadStatus::ensureWon($companyId);
        LeadStatus::ensureLoss($companyId);
        LeadStatus::normalizeSystemStages($companyId);

        $statuses = LeadStatus::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        $requestedOrder = collect($request->order)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => isset($statuses[$id]))
            ->unique()
            ->values();

        // Statuses the client didn't include (e.g. a stale/partial payload) keep their
        // existing relative order, appended after the ones the client did send.
        $order = $requestedOrder
            ->merge(
                $statuses->keys()->diff($requestedOrder)->sort()
            )
            ->values();

        // New Lead is always the pipeline entry point, regardless of drop position.
        $newLead = $statuses->first(fn (LeadStatus $status) => in_array(strtolower((string) $status->name), ['new lead', 'fresh lead'], true));
        if ($newLead) {
            $order = $order->reject(fn ($id) => $id === $newLead->id)->prepend($newLead->id);
        }

        $position = 0;
        foreach ($order as $id) {
            LeadStatus::where('id', $id)
                ->where('company_id', $companyId)
                ->update(['order' => $position++]);
        }

        return response()->json(['success' => true]);
    }
}
