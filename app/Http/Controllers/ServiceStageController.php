<?php

namespace App\Http\Controllers;

use App\Models\ServiceStage;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceStageController extends Controller
{
    public function index()
    {
        return redirect()->route('settings.company.index', ['tab' => 'service-settings']);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('service.stages.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|string|size:7',
            'description' => 'nullable|string|max:255',
            'mapped_status' => 'required|string|in:' . implode(',', array_keys(Ticket::statusOptions())),
        ]);

        $companyId = (int) $user->company_id;
        ServiceStage::ensureDefaults($companyId);

        $requestedKey = Str::slug((string) $validated['name'], '_');
        if (in_array($requestedKey, ServiceStage::FIXED_STAGE_KEYS, true)) {
            return back()->with('error', 'New, Open, and Close are fixed system stages and already exist.');
        }

        $baseKey = $requestedKey !== '' ? $requestedKey : 'service_stage';
        $key = $baseKey;
        $suffix = 2;

        while (ServiceStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->exists()) {
            $key = $baseKey . '_' . $suffix++;
        }

        $closeStage = ServiceStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'close')
            ->first();

        $insertOrder = $closeStage
            ? $closeStage->order
            : (ServiceStage::withoutGlobalScopes()->where('company_id', $companyId)->max('order') ?? 1) + 1;

        if ($closeStage) {
            ServiceStage::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('order', '>=', $insertOrder)
                ->increment('order');
        }

        ServiceStage::create([
            'company_id' => $companyId,
            'key' => $key,
            'name' => trim((string) $validated['name']),
            'color' => $validated['color'],
            'description' => filled($validated['description'] ?? null)
                ? trim((string) $validated['description'])
                : null,
            'mapped_status' => $validated['mapped_status'],
            'order' => $insertOrder,
            'is_default' => false,
        ]);

        return back()->with('success', 'Service stage added.');
    }

    public function update(Request $request, ServiceStage $stage)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless((int) $stage->company_id === (int) $user->company_id, 403);
        abort_unless($user->hasPermission('service.stages.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|string|size:7',
            'description' => 'nullable|string|max:255',
            'mapped_status' => 'required|string|in:' . implode(',', array_keys(Ticket::statusOptions())),
        ]);

        if ($stage->isFixedStage()) {
            return back()->with('error', 'New, Open, and Close are fixed system stages and cannot be modified.');
        }

        $stage->update([
            'name' => trim((string) $validated['name']),
            'color' => $validated['color'],
            'description' => filled($validated['description'] ?? null)
                ? trim((string) $validated['description'])
                : null,
            'mapped_status' => $validated['mapped_status'],
        ]);

        return back()->with('success', 'Service stage updated.');
    }

    public function destroy(ServiceStage $stage)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless((int) $stage->company_id === (int) $user->company_id, 403);
        abort_unless($user->hasPermission('service.stages.manage'), 403);

        if ($stage->isFixedStage()) {
            return back()->with('error', 'New, Open, and Close are fixed system stages and cannot be deleted.');
        }

        $companyId = (int) $user->company_id;
        ServiceStage::ensureDefaults($companyId);

        $stageCount = ServiceStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->count();

        if ($stageCount <= 1) {
            return back()->with('error', 'At least one service stage is required.');
        }

        $ticketCount = Ticket::query()
            ->where('company_id', $companyId)
            ->where('board_stage', $stage->key)
            ->count();

        if ($ticketCount > 0) {
            return back()->with('error', "This stage has {$ticketCount} support ticket(s). Move them to another stage before deleting.");
        }

        $stage->delete();

        return back()->with('success', 'Service stage deleted.');
    }

    public function reorder(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('service.stages.manage'), 403);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:service_stages,id',
        ]);

        $companyId = (int) $user->company_id;
        ServiceStage::ensureDefaults($companyId);

        $stages = ServiceStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get()
            ->keyBy('id');

        $requestedOrder = collect($request->input('order', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => isset($stages[$id]))
            ->unique()
            ->values();

        // Stages the client didn't include keep their existing relative order,
        // appended after the ones the client did send.
        $order = $requestedOrder
            ->merge($stages->keys()->diff($requestedOrder))
            ->values();

        $position = 0;
        foreach ($order as $id) {
            ServiceStage::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('id', $id)
                ->update(['order' => $position++]);
        }

        ServiceStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'new')
            ->update(['order' => 0]);

        ServiceStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', 'close')
            ->update(['order' => $position]);

        return response()->json(['success' => true]);
    }
}
