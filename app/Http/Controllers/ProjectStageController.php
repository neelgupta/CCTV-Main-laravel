<?php

namespace App\Http\Controllers;

use App\Models\ProjectStage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectStageController extends Controller
{
    public function index()
    {
        $companyId = (int) auth()->user()->company_id;
        ProjectStage::ensureDefaults($companyId);
        return redirect()->to(route('settings.company.index') . '#project-pipeline');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|string|max:7',
        ]);

        $companyId = (int) auth()->user()->company_id;
        ProjectStage::ensureDefaults($companyId);
        if (in_array(Str::slug((string) $request->name), ['new-project', 'drawing', 'in-progress', 'completed'], true)) {
            return back()->with('error', 'This is a required system stage. Drawing is handled as New Project, so it cannot be created separately.');
        }

        $maxOrder = ProjectStage::where('company_id', $companyId)->max('order') ?? 0;

        ProjectStage::create([
            'company_id' => $companyId,
            'name' => $request->string('name')->toString(),
            'color' => $request->string('color')->toString(),
            'key' => $this->makeUniqueKey($request->string('name')->toString(), $companyId),
            'order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Project stage added.');
    }

    public function update(Request $request, ProjectStage $stage)
    {
        abort_unless($stage->company_id === auth()->user()->company_id, 403);

        $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'required|string|max:7',
        ]);

        if ($stage->isSystemStage()) {
            return back()->with('error', 'This is a required system stage and cannot be modified.');
        }

        $stage->update([
            'name' => $request->string('name')->toString(),
            'color' => $request->string('color')->toString(),
        ]);

        return back()->with('success', 'Project stage updated.');
    }

    public function destroy(ProjectStage $stage)
    {
        abort_unless($stage->company_id === auth()->user()->company_id, 403);

        if ($stage->isSystemStage()) {
            return back()->with('error', 'This is a required system stage and cannot be deleted.');
        }

        if ($stage->projects()->exists()) {
            return back()->with('error', 'Cannot delete project stage because it has projects.');
        }

        $stage->delete();

        return back()->with('success', 'Project stage deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:project_stages,id',
        ]);

        $companyId = (int) auth()->user()->company_id;
        ProjectStage::ensureDefaults($companyId);

        $stages = ProjectStage::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get()
            ->keyBy('id');

        $requestedOrder = collect($request->order)
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
            ProjectStage::where('id', $id)
                ->where('company_id', $companyId)
                ->update(['order' => $position++]);
        }

        ProjectStage::where('company_id', $companyId)
            ->whereRaw('LOWER(`key`) = ?', ['new-project'])
            ->update(['order' => 0]);

        ProjectStage::where('company_id', $companyId)
            ->whereRaw('LOWER(`key`) = ?', ['completed'])
            ->update(['order' => $position]);

        return response()->json(['success' => true]);
    }

    private function makeUniqueKey(string $name, int $companyId): string
    {
        $base = Str::slug($name) ?: 'stage';
        $key = $base;
        $suffix = 2;

        while (ProjectStage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->exists()) {
            $key = $base . '-' . $suffix++;
        }

        return $key;
    }
}
