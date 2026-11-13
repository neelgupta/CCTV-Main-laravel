<?php

namespace App\Http\Controllers;

use App\Models\LeadCategory;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class CompanyCategoryController extends Controller
{
    private function companyId(): ?int
    {
        $user = auth()->user();
        return $user?->company_id ?: $user?->roleRecord?->company_id;
    }

    public function index()
    {
        $companyId = $this->companyId();
        $categories = LeadCategory::query()
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get();
        return view('settings.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('lead_categories', 'name')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
        ]);

        LeadCategory::create([
            'name' => trim($request->name),
            'company_id' => $companyId,
        ]);

        return back()->with('success', 'Category added successfully.');
    }

    public function update(Request $request, LeadCategory $category)
    {
        $companyId = $this->companyId();
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('lead_categories', 'name')
                    ->where(function ($query) use ($companyId) {
                        return $query->where('company_id', $companyId);
                    })
                    ->ignore($category->id),
            ],
        ]);

        if ($category->company_id !== $companyId) {
            abort(403, 'Unauthorized action.');
        }

        $category->update([
            'name' => trim($request->name),
        ]);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(LeadCategory $category)
    {
        // Ensure the user can only delete their own categories
        if ($category->company_id !== $this->companyId()) {
            abort(403, 'Unauthorized action.');
        }

        $category->delete();
        return back()->with('success', 'Category deleted successfully.');
    }
}
