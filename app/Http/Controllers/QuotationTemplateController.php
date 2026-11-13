<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadQuotation;
use App\Models\QuotationTemplate;
use App\Models\TicketDocument;
use Illuminate\Http\Request;

class QuotationTemplateController extends Controller
{
    /**
     * Display a listing of quotation templates in Quotation Settings or as JSON.
     */
    public function index(Request $request)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if($companyId <= 0, 403);

        $templates = QuotationTemplate::query()
            ->where('company_id', $companyId)
            ->with('creator:id,name')
            ->latest()
            ->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'templates' => $templates,
            ]);
        }

        return redirect()->route('settings.company.index', ['tab' => 'quotation-settings']);
    }

    /**
     * Get a specific template as JSON.
     */
    public function show(QuotationTemplate $quotationTemplate)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if((int) $quotationTemplate->company_id !== $companyId, 403);

        return response()->json([
            'success' => true,
            'template' => $quotationTemplate,
        ]);
    }

    /**
     * Store quotation template directly from request payload (e.g. from quotation generator page).
     */
    public function store(Request $request)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if($companyId <= 0, 403);

        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
        ]);

        $rawItems = $request->input('items', []);
        $items = [];

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? $item['material_name'] ?? $item['description'] ?? ''));
            if ($name === '') {
                continue;
            }

            $qty = max(0.01, (float) ($item['qty'] ?? 1));
            $price = max(0, (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0));
            $gst = max(0, min(100, (float) ($item['gst'] ?? 0)));
            $baseTotal = round($qty * $price, 2);
            $gstAmount = round(($baseTotal * $gst) / 100, 2);
            $total = round($baseTotal + $gstAmount, 2);

            $items[] = [
                'product_id' => isset($item['product_id']) ? (string) $item['product_id'] : '',
                'category_id' => isset($item['category_id']) ? (string) $item['category_id'] : '',
                'category_name' => (string) ($item['category_name'] ?? ''),
                'name' => $name,
                'description' => (string) ($item['description'] ?? ''),
                'brand_model' => (string) ($item['brand_model'] ?? $item['brand'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'qty' => $qty,
                'price' => $price,
                'gst' => $gst,
                'base_total' => $baseTotal,
                'gst_amount' => $gstAmount,
                'total' => $total,
            ];
        }

        if (empty($items)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid materials/items found to save in this template.',
                ], 422);
            }
            return back()->with('error', 'No valid materials/items found to save in this template.');
        }

        $template = QuotationTemplate::create([
            'company_id' => $companyId,
            'created_by' => auth()->id(),
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description')) ?: null,
            'items' => $items,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quotation template saved successfully.',
                'template' => $template,
            ]);
        }

        return redirect()->route('settings.company.index', ['tab' => 'quotation-settings'])
            ->with('success', 'Quotation template saved successfully.');
    }

    /**
     * Save materials from an existing quotation or from payload as a template.
     */
    public function storeFromQuotation(Request $request, Lead $lead, ?LeadQuotation $quotation = null)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if($companyId <= 0, 403);

        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
        ]);

        $items = [];

        if ($request->has('items') && is_array($request->input('items')) && count($request->input('items')) > 0) {
            $rawItems = $request->input('items');
        } elseif ($quotation) {
            abort_if((int) $quotation->lead?->company_id !== $companyId && (int) $lead->company_id !== $companyId, 403);
            $formData = is_array($quotation->form_data) ? $quotation->form_data : [];
            $rawItems = $formData['items'] ?? [];
        } else {
            $rawItems = [];
        }

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? $item['material_name'] ?? $item['description'] ?? ''));
            if ($name === '') {
                continue;
            }

            $qty = max(0.01, (float) ($item['qty'] ?? 1));
            $price = max(0, (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0));
            $gst = max(0, min(100, (float) ($item['gst'] ?? 0)));
            $baseTotal = round($qty * $price, 2);
            $gstAmount = round(($baseTotal * $gst) / 100, 2);
            $total = round($baseTotal + $gstAmount, 2);

            $items[] = [
                'product_id' => isset($item['product_id']) ? (string) $item['product_id'] : '',
                'category_id' => isset($item['category_id']) ? (string) $item['category_id'] : '',
                'category_name' => (string) ($item['category_name'] ?? ''),
                'name' => $name,
                'description' => (string) ($item['description'] ?? ''),
                'brand_model' => (string) ($item['brand_model'] ?? $item['brand'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'qty' => $qty,
                'price' => $price,
                'gst' => $gst,
                'base_total' => $baseTotal,
                'gst_amount' => $gstAmount,
                'total' => $total,
            ];
        }

        if (empty($items)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No materials/items found to save in this template.',
                ], 422);
            }
            return back()->with('error', 'No materials/items found to save in this template.');
        }

        $template = QuotationTemplate::create([
            'company_id' => $companyId,
            'created_by' => auth()->id(),
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description')) ?: null,
            'items' => $items,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quotation template saved successfully.',
                'template' => $template,
            ]);
        }

        return redirect()->route('settings.company.index', ['tab' => 'quotation-settings'])
            ->with('success', 'Quotation template saved successfully.');
    }

    /**
     * Save items from an existing client quotation (TicketDocument) as a template.
     */
    public function storeFromBillingDocument(Request $request, TicketDocument $document)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if($companyId <= 0, 403);
        abort_if((int) $document->company_id !== $companyId, 403);

        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:1000',
        ]);

        $rawItems = is_array($document->items) ? $document->items : [];
        $items = [];

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? $item['description'] ?? ''));
            if ($name === '') {
                continue;
            }

            $qty = max(0.01, (float) ($item['qty'] ?? 1));
            $price = max(0, (float) ($item['price'] ?? 0));
            $gst = max(0, min(100, (float) ($item['gst'] ?? 0)));
            $baseTotal = round($qty * $price, 2);
            $gstAmount = round(($baseTotal * $gst) / 100, 2);
            $total = round($baseTotal + $gstAmount, 2);

            $items[] = [
                'product_id' => isset($item['product_id']) ? (string) $item['product_id'] : '',
                'category_id' => isset($item['category_id']) ? (string) $item['category_id'] : '',
                'category_name' => (string) ($item['category_name'] ?? ''),
                'name' => $name,
                'description' => (string) ($item['description'] ?? ''),
                'brand_model' => (string) ($item['brand_model'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'qty' => $qty,
                'price' => $price,
                'gst' => $gst,
                'base_total' => $baseTotal,
                'gst_amount' => $gstAmount,
                'total' => $total,
            ];
        }

        if (empty($items)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid items found in this quotation to save as template.',
                ], 422);
            }
            return back()->with('error', 'No valid items found in this quotation to save as template.');
        }

        $template = QuotationTemplate::create([
            'company_id' => $companyId,
            'created_by' => auth()->id(),
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description')) ?: null,
            'items' => $items,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quotation template saved successfully.',
                'template' => $template,
            ]);
        }

        return back()->with('success', 'Quotation template saved successfully.');
    }

    /**
     * Update an existing quotation template.
     */
    public function update(Request $request, QuotationTemplate $quotationTemplate)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if((int) $quotationTemplate->company_id !== $companyId, 403);

        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:1000',
        ]);

        $quotationTemplate->update([
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description')) ?: null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully.',
                'template' => $quotationTemplate,
            ]);
        }

        return redirect()->route('settings.company.index', ['tab' => 'quotation-settings'])
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Delete a quotation template.
     */
    public function destroy(Request $request, QuotationTemplate $quotationTemplate)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if((int) $quotationTemplate->company_id !== $companyId, 403);

        $quotationTemplate->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quotation template deleted successfully.',
            ]);
        }

        return redirect()->route('settings.company.index', ['tab' => 'quotation-settings'])
            ->with('success', 'Quotation template deleted successfully.');
    }
}
