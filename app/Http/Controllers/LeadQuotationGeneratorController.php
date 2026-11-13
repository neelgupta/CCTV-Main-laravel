<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Client;
use App\Models\BoqCategory;
use App\Models\BoqProduct;
use App\Models\CompanyUnit;
use App\Models\CompanyGstRate;
use App\Models\LeadMaterial;
use App\Models\LeadQuotation;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Company;
use App\Models\QuotationTemplate;
use App\Support\QuotationTaxCalculator;
use App\Services\LetterheadPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadQuotationGeneratorController extends Controller
{
    private function boqLockedBecauseLeadConverted(Lead $lead): bool
    {
        $lead->loadMissing('project');

        return $lead->project !== null;
    }

    private function convertedLeadBoqLockedResponse()
    {
        return redirect()
            ->back()
            ->with('error', 'This lead is already converted to a project. BOQ generation is locked.');
    }

    private function moveLeadToProposalSent(Lead $lead): void
    {
        $proposalSentStatus = LeadStatus::ensureProposalSent((int) $lead->company_id);

        if ((int) $lead->lead_status_id === (int) $proposalSentStatus->id) {
            return;
        }

        $lead->update(['lead_status_id' => $proposalSentStatus->id]);
    }

    private function nextSequentialVersionForLead(Lead $lead, ?Company $company, string $source): string
    {
        return $company?->nextAvailableQuotationVersion() ?? '1';
    }

    private function makePreviewQuotation(array $data, string $source = 'form'): LeadQuotation
    {
        $quotation = new LeadQuotation();
        $quotation->forceFill([
            'version' => $data['version'] ?? 'Preview',
            'amount' => $data['amount'] ?? 0,
            'status' => 'draft',
            'source' => $source,
            'notes' => $data['notes'] ?? null,
            'enable_letterhead' => (bool) ($data['enable_letterhead'] ?? false),
            'form_data' => $data['form_data'] ?? [],
        ]);
        $quotation->exists = false;
        $quotation->setRawAttributes(array_merge($quotation->getAttributes(), [
            'created_at' => now(),
            'updated_at' => now(),
        ]), true);

        return $quotation;
    }

    private function quotationUsesLetterhead(Lead $lead, LetterheadPdfService $letterheadPdfService): bool
    {
        return $letterheadPdfService->hasActiveLetterhead($lead->company ?? null);
    }

    private function quotationViewData(Lead $lead, LeadQuotation $quotation, ?Client $client, bool $isPdf, bool $useLetterhead = false): array
    {
        $lead->loadMissing('company');
        $pdfLayout = $this->resolvePdfLayout($lead->company, $useLetterhead, app(LetterheadPdfService::class));

        return [
            'lead' => $lead,
            'quotation' => $quotation,
            'company' => $lead->company,
            'client' => $client,
            'isPdf' => $isPdf,
            'useLetterhead' => $useLetterhead,
            'pdfPreviewUrl' => route('leads.quotation.download-pdf', ['lead' => $lead, 'quotation' => $quotation, 'inline' => 1]),
            'pdfMargins' => $pdfLayout['pdfMargins'],
            'pdfPageMargin' => $pdfLayout['pdfPageMargin'],
            'pdfPrintableWidthMm' => $pdfLayout['pdfPrintableWidthMm'],
            'pdfPrintableHeightMm' => $pdfLayout['pdfPrintableHeightMm'],
        ];
    }

    private function buildQuotationPdf(Lead $lead, LeadQuotation $quotation, ?Client $client, bool $useLetterhead)
    {
        $viewData = $this->quotationViewData($lead, $quotation, $client, true, $useLetterhead);
        $pdf = Pdf::loadView('leads.quotations.view-generic', $viewData);

        if ($useLetterhead) {
            $mmToPoints = static fn (float $mm): float => $mm * 72 / 25.4;
            $pdf->setPaper([
                0,
                0,
                $mmToPoints((float) $viewData['pdfPrintableWidthMm']),
                $mmToPoints((float) $viewData['pdfPrintableHeightMm']),
            ]);
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        return $pdf->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);
    }

    private function formattedVersionForCompany(?Company $company, string $source = 'quotation'): string
    {
        return $company?->formatQuotationNumbering($source) ?? ($source === 'boq' ? 'BOQ' : 'Quotation');
    }

    private function resolvePdfLayout(?Company $company, bool $useLetterhead, LetterheadPdfService $letterheadPdfService): array
    {
        $margins = $useLetterhead
            ? $letterheadPdfService->getMargins($letterheadPdfService->activeLetterhead($company))
            : [
                'top_margin' => 15,
                'bottom_margin' => 15,
                'left_margin' => 15,
                'right_margin' => 15,
            ];

        $top = (float) ($margins['top_margin'] ?? 15);
        $right = (float) ($margins['right_margin'] ?? 15);
        $bottom = (float) ($margins['bottom_margin'] ?? 15);
        $left = (float) ($margins['left_margin'] ?? 15);

        return [
            'pdfMargins' => [
                'top_margin' => $top,
                'right_margin' => $right,
                'bottom_margin' => $bottom,
                'left_margin' => $left,
            ],
            'pdfPageMargin' => $useLetterhead ? '0mm' : sprintf('%gmm %gmm %gmm %gmm', $top, $right, $bottom, $left),
            'pdfPrintableWidthMm' => max(0.0, 210.0 - $left - $right),
            'pdfPrintableHeightMm' => max(1.0, 297.0 - $top - $bottom),
        ];
    }

    private function resolveClientForLead(Lead $lead): ?Client
    {
        $companyId = (int) $lead->company_id;
        $email = filled($lead->email) ? strtolower(trim((string) $lead->email)) : null;
        $mobile = filled($lead->mobile) ? trim((string) $lead->mobile) : null;
        $name = trim((string) $lead->client_name);

        return Client::query()
            ->with(['state:id,name', 'city:id,name,state_id'])
            ->where('company_id', $companyId)
            ->where(function ($query) use ($email, $mobile, $name) {
                if ($email) {
                    $query->whereRaw('LOWER(email) = ?', [$email]);
                }

                if ($mobile) {
                    $query->orWhere('mobile', $mobile)
                        ->orWhere('phone', $mobile);
                }

                if ($name !== '') {
                    $query->orWhere('name', $name);
                }
            })
            ->first();
    }

    private function withClientTaxContext(Lead $lead, array $formData, ?Client $client): array
    {
        return QuotationTaxCalculator::enrichClientTaxContext($formData, $lead, $client);
    }

    private function getLeadMaterialsForJs(Lead $lead): array
    {
        $products = BoqProduct::query()
            ->where('company_id', $lead->company_id)
            ->with('category:id,name')
            ->get(['id', 'category_id', 'name', 'description', 'unit', 'price', 'gst']);

        return LeadMaterial::query()
            ->where('lead_id', $lead->id)
            ->orderBy('created_at')
            ->get(['product_id', 'material_name', 'qty', 'unit', 'rate', 'gst', 'note', 'total'])
            ->map(function ($m) use ($products) {
                $materialName = trim(strtolower((string) $m->material_name));
                $materialUnit = trim(strtolower((string) ($m->unit ?? '')));
                $materialRate = (float) $m->rate;

                $product = $m->product_id
                    ? $products->firstWhere('id', (int) $m->product_id)
                    : null;

                $product ??= $products->first(function ($candidate) use ($materialName, $materialUnit, $materialRate) {
                    $name = trim(strtolower((string) ($candidate->name ?? '')));
                    $description = trim(strtolower((string) ($candidate->description ?? '')));

                    if ($name !== $materialName && ($description === '' || $description !== $materialName)) {
                        return false;
                    }

                    $candidateUnit = trim(strtolower((string) ($candidate->unit ?? '')));
                    $candidatePrice = (float) ($candidate->price ?? 0);

                    return $materialUnit === '' || $candidateUnit === '' || $candidateUnit === $materialUnit || abs($candidatePrice - $materialRate) < 0.01;
                });

                return [
                    'material_name' => (string) $m->material_name,
                    'qty'           => (float) $m->qty,
                    'unit'          => (string) ($m->unit ?? ''),
                    'rate'          => $materialRate,
                    'gst'           => (float) $m->gst,
                    'note'          => (string) ($m->note ?? ''),
                    'total'         => (float) $m->total,
                    'product_id'    => $product ? (string) $product->id : '',
                    'category_id'   => $product ? (string) $product->category_id : '',
                    'category_name' => $product ? (string) ($product->category?->name ?? '') : '',
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeBoqFormData(array $formData): array
    {
        $items = collect($formData['items'] ?? [])->map(function ($item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0);
            $baseTotal = (float) ($item['base_total'] ?? ($qty * $price));
            $gst = (float) ($item['gst'] ?? 0);
            $gstAmount = (float) ($item['gst_amount'] ?? (($baseTotal * $gst) / 100));
            $total = (float) ($item['total'] ?? $item['amount'] ?? ($baseTotal + $gstAmount));

            $item['qty'] = (float) $qty;
            $item['price'] = $price;
            $item['rate'] = $price;
            $item['unit_price'] = $price;
            $item['base_total'] = $baseTotal;
            $item['gst'] = $gst;
            $item['gst_amount'] = $gstAmount;
            $item['total'] = $total;
            $item['amount'] = $total;

            return $item;
        })->values()->all();

        $formData['items'] = $items;

        return $formData;
    }

    private function normalizeQuotationItemsForEdit(array $formData, array $leadMaterials, $boqProducts): array
    {
        $items = collect($formData['items'] ?? []);
        if ($items->isEmpty()) {
            return $formData;
        }

        $products = collect($boqProducts);
        $materials = collect($leadMaterials);

        $normalizedItems = $items->map(function ($item) use ($products, $materials) {
            $unit = trim((string) ($item['unit'] ?? ''));
            if ($unit !== '') {
                $item['unit'] = $unit;
                return $item;
            }

            $itemName = trim(strtolower((string) ($item['name'] ?? $item['description'] ?? '')));
            $brandModel = trim(strtolower((string) ($item['brand_model'] ?? '')));

            if ($itemName !== '') {
                $matchedMaterial = $materials->first(function ($material) use ($itemName) {
                    $materialName = trim(strtolower((string) ($material['material_name'] ?? '')));
                    return $materialName !== '' && $materialName === $itemName;
                });

                if ($matchedMaterial && filled($matchedMaterial['unit'] ?? null)) {
                    $item['unit'] = trim((string) $matchedMaterial['unit']);
                    return $item;
                }
            }

            $matchedProduct = $products->first(function ($product) use ($itemName, $brandModel) {
                $name = trim(strtolower((string) ($product->name ?? '')));
                $description = trim(strtolower((string) ($product->description ?? '')));

                if ($itemName === '' || ($name !== $itemName && $description !== $itemName)) {
                    return false;
                }

                if ($brandModel === '') {
                    return true;
                }

                $productText = trim(strtolower(implode(' ', array_filter([
                    (string) ($product->name ?? ''),
                    (string) ($product->description ?? ''),
                ]))));

                return str_contains($productText, $brandModel);
            });

            if ($matchedProduct && filled($matchedProduct->unit ?? null)) {
                $item['unit'] = trim((string) $matchedProduct->unit);
            }

            return $item;
        })->values()->all();

        $formData['items'] = $normalizedItems;
        return $formData;
    }

    public function create(Lead $lead, ?LeadQuotation $quotation = null)
    {
        $lead->load('company');
        $company = $lead->company;
        $client = $this->resolveClientForLead($lead);

        if (!$company || !$company->enable_quotation_generator) {
            return redirect()->route('leads.show', $lead)->with('error', 'Quotation generator is not enabled for your company.');
        }

        $viewName = 'leads.quotations.generate-cctv';

        $currentVersion = $this->nextSequentialVersionForLead($lead, $company, 'form');
        $boqCategories = BoqCategory::query()->where('company_id', $company->id)->orderBy('name')->get();
        $boqProducts = BoqProduct::query()->where('company_id', $company->id)->with('category:id,name')->orderBy('name')->get();
        $units = $company->units()->get();
        $gstRates = CompanyGstRate::query()->where('company_id', $company->id)->orderBy('rate')->get(['rate']);
        $leadMaterials = $this->getLeadMaterialsForJs($lead);
        $quotationCustomFields = $company->leadCustomFieldsForQuotation();
        $customFieldValues = $this->resolveCustomFieldValuesForQuotation($lead, $quotation);
        $quotationTemplates = QuotationTemplate::query()->where('company_id', $company->id)->latest()->get();

        if ($quotation) {
            $formData = is_array($quotation->form_data ?? null) ? $quotation->form_data : [];
            $quotation->form_data = $this->normalizeQuotationItemsForEdit($formData, $leadMaterials, $boqProducts);
        }

        return view($viewName, compact('lead', 'company', 'client', 'currentVersion', 'boqCategories', 'boqProducts', 'units', 'quotation', 'leadMaterials', 'gstRates', 'quotationCustomFields', 'customFieldValues', 'quotationTemplates'));
    }

    private function resolveCustomFieldValuesForQuotation(Lead $lead, ?LeadQuotation $quotation): array
    {
        $formData = is_array($quotation?->form_data ?? null) ? $quotation->form_data : [];
        $savedCustomFields = is_array($formData['custom_fields'] ?? null) ? $formData['custom_fields'] : [];

        if ($savedCustomFields !== []) {
            return $savedCustomFields;
        }

        return is_array($lead->custom_fields ?? null) ? $lead->custom_fields : [];
    }

    public function createBoq(Lead $lead, ?LeadQuotation $quotation = null)
    {
        $lead->load('company');
        $company = $lead->company;
        $client = $this->resolveClientForLead($lead);

        if (!$company || !($company->enable_boq ?? true)) {
            return redirect()->route('leads.show', $lead)->with('error', 'BOQ generator is not enabled for your company.');
        }

        if ($quotation === null && $this->boqLockedBecauseLeadConverted($lead)) {
            return redirect()->route('leads.show', ['lead' => $lead, 'tab' => 'boq'])
                ->with('error', 'This lead is already converted to a project. BOQ generation is locked.');
        }

        $boqCategories = BoqCategory::query()->where('company_id', $company->id)->with(['products' => function ($query) use ($company) {
            $query->where('company_id', $company->id)->orderBy('name');
        }])->orderBy('name')->get();
        $boqProducts = BoqProduct::query()->where('company_id', $company->id)->with('category:id,name')->orderBy('name')->get();
        $units = $company->units()->get();
        $gstRates = CompanyGstRate::query()->where('company_id', $company->id)->orderBy('rate')->get(['rate']);
        $isEdit = $quotation !== null;
        $version = $this->nextSequentialVersionForLead($lead, $company, 'boq');
        $boqFormData = $quotation?->form_data ?? [];
        $boqItems = $this->normalizeBoqItemsForEdit($boqFormData['items'] ?? [], $boqProducts, $boqCategories);
        $leadMaterials = $this->getLeadMaterialsForJs($lead);
        $quotationCustomFields = $company->leadCustomFieldsForQuotation();
        $customFieldValues = $this->resolveCustomFieldValuesForQuotation($lead, $quotation);
        $quotationTemplates = QuotationTemplate::query()->where('company_id', $company->id)->latest()->get();

        return view('leads.boq-generate', compact('lead', 'company', 'client', 'boqCategories', 'boqProducts', 'units', 'quotation', 'boqItems', 'boqFormData', 'isEdit', 'version', 'leadMaterials', 'gstRates', 'quotationCustomFields', 'customFieldValues', 'quotationTemplates'));
    }

    private function normalizeBoqItemsForEdit(array $items, $boqProducts, $boqCategories): array
    {
        $products = collect($boqProducts);
        $categories = collect($boqCategories);

        return collect($items)->map(function ($item, $index) use ($products, $categories) {
            $normalized = [
                '_rowId' => $index + 1,
                'product_id' => isset($item['product_id']) ? (string) $item['product_id'] : '',
                'category_id' => isset($item['category_id']) ? (string) $item['category_id'] : '',
                'category_name' => (string) ($item['category_name'] ?? ''),
                'description' => (string) ($item['description'] ?? $item['name'] ?? $item['title'] ?? $item['item_name'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'qty' => (float) ($item['qty'] ?? 0),
                'price' => (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0),
                'gst' => (float) ($item['gst'] ?? 0),
                'base_total' => (float) ($item['base_total'] ?? 0),
                'gst_amount' => (float) ($item['gst_amount'] ?? 0),
                'total' => (float) ($item['total'] ?? $item['amount'] ?? 0),
            ];

            $needle = trim(strtolower((string) $normalized['description']));
            $product = null;

            if ($normalized['product_id'] !== '') {
                $product = $products->firstWhere('id', (int) $normalized['product_id']);
            }

            if (!$product && $needle !== '') {
                $product = $products->first(function ($candidate) use ($needle) {
                    $name = trim(strtolower((string) ($candidate->name ?? '')));
                    $description = trim(strtolower((string) ($candidate->description ?? '')));

                    return $name === $needle || ($description !== '' && $description === $needle);
                });
            }

            if (!$product && $normalized['category_name'] !== '') {
                $category = $categories->first(function ($candidate) use ($normalized) {
                    return trim(strtolower((string) $candidate->name)) === trim(strtolower((string) $normalized['category_name']));
                });

                if ($category) {
                    $normalized['category_id'] = (string) $category->id;
                }
            }

            if ($product) {
                $normalized['product_id'] = (string) $product->id;
                $normalized['category_id'] = (string) ($product->category_id ?? $normalized['category_id']);
                $normalized['category_name'] = (string) ($product->category?->name ?? $normalized['category_name']);
                if (trim($normalized['description']) === '') {
                    $normalized['description'] = (string) ($product->description ?: $product->name);
                }
                $normalized['unit'] = (string) ($product->unit ?: $normalized['unit']);
                if ($normalized['price'] <= 0) {
                    $normalized['price'] = (float) ($product->price ?? $normalized['price']);
                }
                if ($normalized['gst'] <= 0) {
                    $normalized['gst'] = (float) ($product->gst ?? $normalized['gst']);
                }
            }

            if ($normalized['category_name'] === '' && $normalized['category_id'] !== '') {
                $normalized['category_name'] = (string) optional($categories->firstWhere('id', (int) $normalized['category_id']))->name;
            }

            return $normalized;
        })->values()->all();
    }

    public function preview(Request $request, Lead $lead, LetterheadPdfService $letterheadPdfService)
    {
        $lead->load('company');
        $company = $lead->company;
        $client = $this->resolveClientForLead($lead);

        if (!$company || !$company->enable_quotation_generator) {
            return back()->with('error', 'Quotation generator is not enabled for your company.');
        }

        $data = $this->validateQuotationFormPayload($request, $company);

        $data['form_data'] = $this->withClientTaxContext($lead, $data['form_data'], $client);
        $data['form_data'] = $this->normalizeBoqFormData($data['form_data']);

        $quotation = $this->makePreviewQuotation($data, 'form');
        $useLetterhead = $this->quotationUsesLetterhead($lead, $letterheadPdfService);
        $pdf = $this->buildQuotationPdf($lead, $quotation, $client, $useLetterhead);
        $binary = $pdf->output();

        if ($useLetterhead) {
            $binary = $letterheadPdfService->applyLetterheadBackground($binary, $company, null, false, true);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="quotation-preview.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function previewBoq(Request $request, Lead $lead)
    {
        $lead->load('company');
        $company = $lead->company;
        $client = $this->resolveClientForLead($lead);

        if (!$company || !($company->enable_boq ?? true)) {
            return back()->with('error', 'BOQ generator is not enabled.');
        }

        if ($this->boqLockedBecauseLeadConverted($lead)) {
            return $this->convertedLeadBoqLockedResponse();
        }

        $data = $this->validateBoqPayload($request, $company);
        $data['form_data'] = $this->withClientTaxContext($lead, $data['form_data'], $client);
        $data['form_data'] = $this->normalizeBoqFormData($data['form_data']);

        $quotation = $this->makePreviewQuotation($data, 'boq');

        return view('leads.boq-view', compact('lead', 'quotation', 'client'));
    }

    public function store(Request $request, Lead $lead, ?LeadQuotation $quotation = null)
    {
        $lead->load('company');
        $company = $lead->company;
        $client = $this->resolveClientForLead($lead);

        if (!$company || !($company->enable_quotation_generator ?? true)) {
            return back()->with('error', 'Quotation generator is not enabled.');
        }

        $data = $this->validateQuotationFormPayload($request, $company);

        $data['form_data'] = $this->withClientTaxContext($lead, $data['form_data'], $client);
        $data['form_data'] = $this->normalizeBoqFormData($data['form_data']);

        $quotationModel = $quotation && (int) $quotation->lead_id === (int) $lead->id ? $quotation : null;
        $isUpdate = (bool) $quotationModel;
        $quotationModel = $quotationModel ?: $lead->quotations()->make();
        $version = $isUpdate ? (string) $quotationModel->version : ($company?->reserveNextQuotationVersion()['version'] ?? $this->nextSequentialVersionForLead($lead, $company, 'form'));
        $existingFormData = is_array($quotationModel->form_data ?? null) ? $quotationModel->form_data : [];
        $submittedFormData = $data['form_data'] ?? [];
        $quotationModel->fill([
            'file_path' => '',
            'source' => 'form',
            'version' => $version,
            'amount' => $data['amount'],
            'status' => $quotationModel->exists ? $quotationModel->status : 'sent',
            'enable_letterhead' => (bool) ($data['enable_letterhead'] ?? false),
            'form_data' => $isUpdate ? array_replace_recursive($existingFormData, $submittedFormData) : $submittedFormData,
            'notes' => $data['notes'] ?? null,
        ]);
        $quotationModel->lead_id = $lead->id;
        $quotationModel->save();
        $this->moveLeadToProposalSent($lead);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'quotation',
            'description' => "Generated dynamic quotation version {$version} for amount ₹{$data['amount']}",
        ]);

        return redirect()->route('leads.show', $lead)->with('success', 'Quotation generated successfully.');
    }

    public function updateQuotation(Request $request, Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id || strtolower((string) $quotation->source) === 'boq') {
            abort(404);
        }

        return $this->store($request, $lead, $quotation);
    }

    public function cloneQuotation(Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $lead->load('company');
        $company = $lead->company;
        $isBoq = strtolower((string) $quotation->source) === 'boq';

        if ($this->boqLockedBecauseLeadConverted($lead)) {
            return $isBoq
                ? $this->convertedLeadBoqLockedResponse()
                : back()->with('error', 'This lead is already converted to a project. Quotation cloning is locked.');
        }

        $version = $company?->reserveNextQuotationVersion()['version'] ?? $this->nextSequentialVersionForLead($lead, $company, $quotation->source);

        $clone = $quotation->replicate(['status', 'version', 'created_at', 'updated_at']);
        $clone->version = $version;
        $clone->status = 'sent';
        $clone->lead_id = $lead->id;
        $clone->save();

        $this->moveLeadToProposalSent($lead);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'quotation',
            'description' => ($isBoq ? 'Cloned BOQ' : 'Cloned quotation') . " version {$quotation->version} as version {$version}",
        ]);

        return redirect()->route('leads.show', $lead)->with('success', 'Quotation cloned successfully.');
    }

    public function storeBoq(Request $request, Lead $lead, ?LeadQuotation $quotation = null)
    {
        $lead->load('company');
        $company = $lead->company;
        $client = $this->resolveClientForLead($lead);

        if (!$company || !($company->enable_boq ?? true)) {
            return back()->with('error', 'BOQ generator is not enabled.');
        }

        if ($quotation === null && $this->boqLockedBecauseLeadConverted($lead)) {
            return $this->convertedLeadBoqLockedResponse();
        }

        $data = $this->validateBoqPayload($request, $company);
        $data['form_data'] = $this->withClientTaxContext($lead, $data['form_data'], $client);
        $data['form_data'] = $this->normalizeBoqFormData($data['form_data']);

        $quotationModel = $quotation && (int) $quotation->lead_id === (int) $lead->id ? $quotation : null;
        $isUpdate = (bool) $quotationModel;
        $quotationModel = $quotationModel ?: $lead->quotations()->make();
        $version = $isUpdate ? $quotationModel->version : ($company?->reserveNextQuotationVersion()['version'] ?? $this->nextSequentialVersionForLead($lead, $company, 'boq'));
        $existingFormData = is_array($quotationModel->form_data ?? null) ? $quotationModel->form_data : [];
        $submittedFormData = $data['form_data'] ?? [];
        $quotationModel->fill([
            'file_path' => '',
            'source' => 'boq',
            'version' => $version,
            'amount' => $data['amount'],
            'status' => $quotationModel->exists ? $quotationModel->status : 'sent',
            'form_data' => $isUpdate ? array_replace_recursive($existingFormData, $submittedFormData) : $submittedFormData,
            'notes' => $data['notes'] ?? null,
        ]);
        $quotationModel->lead_id = $lead->id;
        $quotationModel->save();
        $this->moveLeadToProposalSent($lead);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'quotation',
            'description' => "Generated BOQ version {$version} for amount ₹{$data['amount']}",
        ]);

        return redirect()->route('leads.show', $lead)->with('success', 'BOQ generated successfully.');
    }

    private function customFieldValidationRules(?Company $company): array
    {
        $rules = [];

        foreach ($company?->leadCustomFieldsForQuotation() ?? [] as $field) {
            $key = $field['key'];
            $fieldRules = [];
            $fieldRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';

            $fieldRules[] = match ($field['type']) {
                'number' => 'numeric',
                'date' => 'date',
                'email' => 'email',
                'checkbox' => 'boolean',
                default => 'string',
            };

            if (in_array($field['type'], ['dropdown', 'radiobutton'], true) && !empty($field['options'])) {
                $fieldRules[] = 'in:' . implode(',', $field['options']);
            }

            $rules["form_data.custom_fields.{$key}"] = $fieldRules;
        }

        return $rules;
    }

    private function validateBoqPayload(Request $request, ?Company $company = null): array
    {
        $data = $request->validate(array_merge([
            'version' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'form_data' => 'required|array',
            'form_data.discount' => ['nullable', 'numeric', 'min:0'],
            'form_data.pricing' => ['nullable', 'array'],
            'form_data.pricing.discount' => ['nullable', 'numeric', 'min:0'],
            'form_data.subject' => ['nullable', 'string', 'max:150'],
            'form_data.date' => ['nullable', 'string'],
            'form_data.boq_type' => ['nullable', 'string'],
            'form_data.custom_fields' => ['nullable', 'array'],
            'form_data.items' => 'required|array|min:1',
            'form_data.items.*.product_id' => ['nullable', 'string'],
            'form_data.items.*.category_id' => ['nullable', 'string'],
            'form_data.items.*.category_name' => ['nullable', 'string'],
            'form_data.items.*.description' => ['nullable', 'string'],
            'form_data.items.*.unit' => ['nullable', 'string'],
            'form_data.items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'form_data.items.*.price' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.gst' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'form_data.items.*.total' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => 'nullable|string',
        ], $this->customFieldValidationRules($company)));

        return $data;
    }

    private function validateQuotationFormPayload(Request $request, ?Company $company = null): array
    {
        return $request->validate(array_merge([
            'version' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'form_data' => 'required|array',
            'form_data.discount' => ['nullable', 'numeric', 'min:0'],
            'form_data.date' => ['nullable', 'string'],
            'form_data.valid_until' => ['nullable', 'string'],
            'form_data.project' => ['nullable', 'array'],
            'form_data.project.subject' => ['nullable', 'string', 'max:150'],
            'form_data.client' => ['nullable', 'array'],
            'form_data.client.name' => ['nullable', 'string'],
            'form_data.client.billing_address' => ['nullable', 'string'],
            'form_data.client.site_address' => ['nullable', 'string'],
            'form_data.client.contact' => ['nullable', 'string'],
            'form_data.terms' => ['nullable', 'array'],
            'form_data.terms.conditions' => ['nullable', 'string'],
            'form_data.pricing' => ['nullable', 'array'],
            'form_data.pricing.discount' => ['nullable', 'numeric', 'min:0'],
            'form_data.custom_fields' => ['nullable', 'array'],
            'form_data.items' => ['required', 'array', 'min:1'],
            'form_data.items.*.name' => ['nullable', 'string'],
            'form_data.items.*.description' => ['nullable', 'string'],
            'form_data.items.*.brand_model' => ['nullable', 'string'],
            'form_data.items.*.unit' => ['nullable', 'string'],
            'form_data.items.*.qty' => ['required', 'numeric', 'min:0'],
            'form_data.items.*.price' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.gst' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'form_data.items.*.base_total' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.gst_amount' => ['nullable', 'numeric', 'min:0'],
            'form_data.items.*.total' => ['nullable', 'numeric', 'min:0'],
            'notes' => 'nullable|string',
            'enable_letterhead' => 'nullable|boolean',
        ], $this->customFieldValidationRules($company)));
    }

    public function updateBoq(Request $request, Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id || strtolower((string) $quotation->source) !== 'boq') {
            abort(404);
        }

        return $this->storeBoq($request, $lead, null);
    }

    public function viewBoq(Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404);
        }

        if (strtolower((string) $quotation->source) !== 'boq') {
            abort(404);
        }

        $lead->load('company');
        $client = $this->resolveClientForLead($lead);

        return view('leads.boq-view', compact('lead', 'quotation', 'client'));
    }

    public function editBoq(Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id || strtolower((string) $quotation->source) !== 'boq') {
            abort(404);
        }

        return $this->createBoq($lead, $quotation);
    }

    public function editQuotation(Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id || strtolower((string) $quotation->source) === 'boq') {
            abort(404);
        }

        return $this->create($lead, $quotation);
    }

    public function printBoq(Lead $lead, LeadQuotation $quotation)
    {
        return $this->viewBoq($lead, $quotation);
    }

    public function show(Lead $lead, LeadQuotation $quotation, LetterheadPdfService $letterheadPdfService)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $lead->load('company');
        $client = $this->resolveClientForLead($lead);
        return view('leads.quotations.view-generic', $this->quotationViewData($lead, $quotation, $client, false, $this->quotationUsesLetterhead($lead, $letterheadPdfService)));
    }

    public function downloadQuotation(Request $request, Lead $lead, LeadQuotation $quotation, LetterheadPdfService $letterheadPdfService)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $lead->load('company');
        $client = $this->resolveClientForLead($lead);
        $useLetterhead = $this->quotationUsesLetterhead($lead, $letterheadPdfService);
        $pdf = $this->buildQuotationPdf($lead, $quotation, $client, $useLetterhead);

        $fileName = 'quotation-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($quotation->version ?? $lead->id)) . '.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';
        $binary = $pdf->output();

        if ($useLetterhead) {
            $binary = $letterheadPdfService->applyLetterheadBackground($binary, $lead->company ?? null, null, false, true);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $fileName . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function downloadBoq(Lead $lead, LeadQuotation $quotation, LetterheadPdfService $letterheadPdfService)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id || strtolower((string) $quotation->source) !== 'boq') {
            abort(404);
        }

        $lead->load('company');
        $client = $this->resolveClientForLead($lead);
        $useLetterhead = $letterheadPdfService->hasActiveLetterhead($lead->company ?? null);
        $pdfLayout = $this->resolvePdfLayout($lead->company, $useLetterhead, $letterheadPdfService);

        $pdf = Pdf::loadView('leads.boq-view', array_merge(compact('lead', 'quotation', 'client'), [
            'isPdf' => true,
            'useLetterhead' => $useLetterhead,
            'pdfMargins' => $pdfLayout['pdfMargins'],
            'pdfPageMargin' => $pdfLayout['pdfPageMargin'],
        ]))->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);

        $fileName = 'boq-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($quotation->version ?? $lead->id)) . '.pdf';

        return $letterheadPdfService->download($pdf, $fileName, $lead->company ?? null);
    }
}
