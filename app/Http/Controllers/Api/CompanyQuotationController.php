<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoqProduct;
use App\Models\Client;
use App\Models\Company;
use App\Models\TicketDocument;
use App\Models\TicketQuote;
use App\Models\User;
use App\Services\TicketDocumentLinkService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyQuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can access quotations.');

        $companyId = (int) $user->company_id;
        $perPage = max(1, min(100, (int) $request->integer('per_page', 15)));

        $query = TicketDocument::query()
            ->where('company_id', $companyId)
            ->where('document_type', 'quotation')
            ->with([
                'client:id,company_id,name,email,mobile,phone,address,gst_number',
                'creator:id,name',
            ])
            ->when(filled($request->query('search')), function ($builder) use ($request) {
                $search = trim((string) $request->query('search'));
                $builder->where(function ($inner) use ($search) {
                    $inner->where('document_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when(filled($request->query('client_id')), fn ($builder) => $builder->where('client_id', (int) $request->query('client_id')))
            ->when(filled($request->query('start_date')), fn ($builder) => $builder->whereDate('created_at', '>=', $request->query('start_date')))
            ->when(filled($request->query('end_date')), fn ($builder) => $builder->whereDate('created_at', '<=', $request->query('end_date')))
            ->orderByDesc('id');

        $quotations = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'message' => 'Quotation list fetched successfully.',
            'data' => [
                'quotations' => $quotations->through(fn (TicketDocument $doc) => $this->formatQuotation($doc)),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can create quotations.');

        $companyId = (int) $user->company_id;
        $this->normalizeRequestItems($request);

        $activeProductIds = BoqProduct::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $validated = $this->validateJson($request, [
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::in($activeProductIds),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
        ], [
            'client_id.integer' => 'Client ID must be a valid integer.',
            'client_id.exists' => 'Selected client is not available for this company.',
            'subject.max' => 'Subject may not be greater than 255 characters.',
            'items.required' => 'Quotation must contain at least one item from the product list.',
            'items.array' => 'Items must be provided as a list.',
            'items.min' => 'Quotation must contain at least one item from the product list.',
            'items.*.product_id.required' => 'Each quotation item must have a product selected from the product list.',
            'items.*.product_id.integer' => 'Product ID must be an integer.',
            'items.*.product_id.in' => 'Selected product is not available or inactive in your company product list. Quotations can only be added from the product list.',
            'items.*.qty.required' => 'Quantity is required for each item.',
            'items.*.qty.integer' => 'Quantity must be an integer.',
            'items.*.qty.min' => 'Quantity must be at least 1.',
            'items.*.price.numeric' => 'Price must be a valid number.',
            'items.*.price.min' => 'Price cannot be negative.',
            'items.*.gst.numeric' => 'GST must be a valid number.',
            'items.*.gst.min' => 'GST cannot be negative.',
            'items.*.note.max' => 'Item note may not be greater than 1000 characters.',
            'discount.numeric' => 'Discount must be a number.',
            'discount.min' => 'Discount cannot be negative.',
            'terms_conditions.max' => 'Terms and conditions may not be greater than 5000 characters.',
        ]);

        $clientId = filled($validated['client_id'] ?? null) ? (int) $validated['client_id'] : null;
        $client = $clientId ? Client::query()->where('company_id', $companyId)->find($clientId) : null;
        $calculatedItems = $this->buildCalculatedItems($validated['items'], $companyId);
        $summary = $this->buildSummary($calculatedItems, (float) ($validated['discount'] ?? 0));

        $savedQuotation = DB::transaction(function () use ($validated, $calculatedItems, $summary, $client, $user, $companyId): TicketDocument {
            $company = Company::query()->lockForUpdate()->findOrFail($companyId);
            $allocatedDocument = $company->allocateDocumentNumber('quotation');
            $documentNumber = $allocatedDocument['number'] ?? null;

            if (! $documentNumber) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Please configure a valid Quotation Number in Company Settings.',
                    'errors' => [
                        'document_number' => ['Please configure a valid Quotation Number in Company Settings.'],
                    ],
                ], 422));
            }

            $quotation = TicketDocument::query()->create([
                'ticket_id' => null,
                'client_id' => $client?->id,
                'company_id' => $companyId,
                'created_by' => $user->id,
                'document_type' => 'quotation',
                'invoice_or_quote' => 2,
                'document_number' => $documentNumber,
                'subject' => filled($validated['subject'] ?? null) ? trim((string) $validated['subject']) : null,
                'items' => $calculatedItems,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'gst_amount' => $summary['gst_amount'],
                'total' => $summary['total'],
                'payment_status' => null,
                'payment_method' => null,
                'has_additional_notes' => false,
                'additional_notes' => null,
                'terms_conditions' => filled($validated['terms_conditions'] ?? null) ? trim((string) $validated['terms_conditions']) : null,
            ]);

            $company->syncDocumentNumberSequence('quotation', ((int) $allocatedDocument['sequence']) + 1);

            app(TicketDocumentLinkService::class)->linkStandaloneQuotation($quotation->fresh(), $client, $user);

            return $quotation->fresh(['client', 'creator']);
        });

        return response()->json([
            'message' => 'Quotation created successfully.',
            'data' => [
                'quotation' => $this->formatQuotation($savedQuotation),
            ],
        ], 201);
    }

    public function show(Request $request, int $quotationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can access quotations.');

        $quotation = TicketDocument::query()
            ->where('company_id', (int) $user->company_id)
            ->where('document_type', 'quotation')
            ->with(['client:id,company_id,name,email,mobile,phone,address,gst_number', 'creator:id,name'])
            ->findOrFail($quotationId);

        return response()->json([
            'message' => 'Quotation details fetched successfully.',
            'data' => [
                'quotation' => $this->formatQuotation($quotation),
            ],
        ]);
    }

    public function update(Request $request, int $quotationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can update quotations.');

        $companyId = (int) $user->company_id;
        $quotation = TicketDocument::query()
            ->where('company_id', $companyId)
            ->where('document_type', 'quotation')
            ->findOrFail($quotationId);

        $this->normalizeRequestItems($request);

        $activeProductIds = BoqProduct::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $validated = $this->validateJson($request, [
            'client_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::in($activeProductIds),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
        ], [
            'client_id.integer' => 'Client ID must be a valid integer.',
            'client_id.exists' => 'Selected client is not available for this company.',
            'subject.max' => 'Subject may not be greater than 255 characters.',
            'items.required' => 'Quotation must contain at least one item from the product list.',
            'items.array' => 'Items must be provided as a list.',
            'items.min' => 'Quotation must contain at least one item from the product list.',
            'items.*.product_id.required' => 'Each quotation item must have a product selected from the product list.',
            'items.*.product_id.integer' => 'Product ID must be an integer.',
            'items.*.product_id.in' => 'Selected product is not available or inactive in your company product list. Quotations can only be added from the product list.',
            'items.*.qty.required' => 'Quantity is required for each item.',
            'items.*.qty.integer' => 'Quantity must be an integer.',
            'items.*.qty.min' => 'Quantity must be at least 1.',
            'items.*.price.numeric' => 'Price must be a valid number.',
            'items.*.price.min' => 'Price cannot be negative.',
            'items.*.gst.numeric' => 'GST must be a valid number.',
            'items.*.gst.min' => 'GST cannot be negative.',
            'items.*.note.max' => 'Item note may not be greater than 1000 characters.',
            'discount.numeric' => 'Discount must be a number.',
            'discount.min' => 'Discount cannot be negative.',
            'terms_conditions.max' => 'Terms and conditions may not be greater than 5000 characters.',
        ]);

        $clientId = array_key_exists('client_id', $validated)
            ? (filled($validated['client_id']) ? (int) $validated['client_id'] : null)
            : $quotation->client_id;
        $client = $clientId ? Client::query()->where('company_id', $companyId)->find($clientId) : null;

        $rawItems = array_key_exists('items', $validated) ? $validated['items'] : ($quotation->items ?? []);
        $discount = array_key_exists('discount', $validated) ? (float) ($validated['discount'] ?? 0) : (float) ($quotation->discount ?? 0);
        $calculatedItems = $this->buildCalculatedItems($rawItems, $companyId);
        $summary = $this->buildSummary($calculatedItems, $discount);

        $updatedQuotation = DB::transaction(function () use ($quotation, $validated, $calculatedItems, $summary, $client, $user): TicketDocument {
            $quotation->forceFill([
                'client_id' => $client?->id,
                'subject' => array_key_exists('subject', $validated)
                    ? (filled($validated['subject']) ? trim((string) $validated['subject']) : null)
                    : $quotation->subject,
                'items' => $calculatedItems,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'gst_amount' => $summary['gst_amount'],
                'total' => $summary['total'],
                'terms_conditions' => array_key_exists('terms_conditions', $validated)
                    ? (filled($validated['terms_conditions']) ? trim((string) $validated['terms_conditions']) : null)
                    : $quotation->terms_conditions,
            ])->save();

            app(TicketDocumentLinkService::class)->linkStandaloneQuotation($quotation->fresh(), $client, $user);

            return $quotation->fresh(['client', 'creator']);
        });

        return response()->json([
            'message' => 'Quotation updated successfully.',
            'data' => [
                'quotation' => $this->formatQuotation($updatedQuotation),
            ],
        ]);
    }

    public function destroy(Request $request, int $quotationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can delete quotations.');

        $companyId = (int) $user->company_id;
        $quotation = TicketDocument::query()
            ->where('company_id', $companyId)
            ->where('document_type', 'quotation')
            ->findOrFail($quotationId);

        DB::transaction(function () use ($quotation) {
            TicketQuote::query()->where('quote_id', $quotation->id)->delete();
            $quotation->delete();
        });

        return response()->json([
            'message' => 'Quotation deleted successfully.',
        ]);
    }

    private function buildCalculatedItems(array $rawItems, int $companyId): array
    {
        $productIds = collect($rawItems)->pluck('product_id')->unique()->all();
        $products = BoqProduct::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $calculatedItems = [];

        foreach ($rawItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $products->get($productId);

            if (! $product) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Selected product is not available in the company product list.',
                    'errors' => [
                        'items' => ['Selected product is not available in the company product list.'],
                    ],
                ], 422));
            }

            $qty = max(1, (int) ($item['qty'] ?? 1));
            $price = array_key_exists('price', $item) && $item['price'] !== null && $item['price'] !== ''
                ? round((float) $item['price'], 2)
                : round((float) ($product->price ?? 0), 2);

            $baseTotal = round($qty * $price, 2);

            $gstRate = array_key_exists('gst', $item) && $item['gst'] !== null && $item['gst'] !== ''
                ? round((float) $item['gst'], 2)
                : round((float) ($product->gst ?? 0), 2);

            $gstAmount = round(($baseTotal * $gstRate) / 100, 2);

            $calculatedItems[] = [
                'product_id' => (int) $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'model' => $product->model,
                'qty' => $qty,
                'price' => $price,
                'base_total' => $baseTotal,
                'unit' => (string) ($product->unit ?? ''),
                'gst' => $gstRate,
                'gst_amount' => $gstAmount,
                'total' => round($baseTotal + $gstAmount, 2),
                'note' => filled($item['note'] ?? null) ? trim((string) $item['note']) : null,
                'description' => $product->description,
            ];
        }

        return $calculatedItems;
    }

    private function buildSummary(array $items, float $discount): array
    {
        $subtotal = round(collect($items)->sum(fn (array $item) => (float) ($item['base_total'] ?? 0)), 2);
        $gstAmount = round(collect($items)->sum(fn (array $item) => (float) ($item['gst_amount'] ?? 0)), 2);
        $discount = min(round($discount, 2), $subtotal + $gstAmount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gst_amount' => $gstAmount,
            'total' => max(0, round($subtotal - $discount + $gstAmount, 2)),
        ];
    }

    private function formatQuotation(TicketDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'document_number' => $doc->document_number,
            'subject' => $doc->subject,
            'subtotal' => (float) $doc->subtotal,
            'discount' => (float) $doc->discount,
            'gst_amount' => (float) $doc->gst_amount,
            'total' => (float) $doc->total,
            'items_count' => is_array($doc->items) ? count($doc->items) : 0,
            'items' => $doc->items ?? [],
            'terms_conditions' => $doc->terms_conditions,
            'client' => $doc->client ? [
                'id' => $doc->client->id,
                'name' => $doc->client->name,
                'email' => $doc->client->email,
                'mobile' => $doc->client->mobile ?? $doc->client->phone,
                'address' => $doc->client->address,
                'gst_number' => $doc->client->gst_number,
            ] : null,
            'creator' => $doc->creator ? [
                'id' => $doc->creator->id,
                'name' => $doc->creator->name,
            ] : null,
            'created_at' => $doc->created_at?->toISOString(),
            'updated_at' => $doc->updated_at?->toISOString(),
        ];
    }

    private function parseArrayInput(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '[]') {
                return [];
            }

            // Check if it is a JSON encoded array or object e.g. "[10,13]" or '["10","13"]'
            if ((str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) || (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return is_array($decoded) ? $decoded : [$decoded];
                }

                $inner = trim($trimmed, '[]{}');
                if ($inner !== '') {
                    return array_map(function ($part) {
                        return trim($part, " \t\n\r\0\x0B'\"");
                    }, explode(',', $inner));
                }
            }

            // If comma-separated e.g. "10,13" or "10, 13"
            if (str_contains($trimmed, ',')) {
                return array_map(function ($part) {
                    return trim($part, " \t\n\r\0\x0B'\"");
                }, explode(',', $trimmed));
            }

            // Single value string
            return [trim($trimmed, " \t\n\r\0\x0B'\"")];
        }

        return [$value];
    }

    private function normalizeRequestItems(Request $request): void
    {
        // 1. Unwrap client_id if passed as "[1]" or "1" or [1]
        if ($request->has('client_id')) {
            $rawClientId = $request->input('client_id');
            if (is_string($rawClientId)) {
                $trimmed = trim($rawClientId, " \t\n\r\0\x0B[]'\"");
                if ($trimmed !== '' && strtolower($trimmed) !== 'null') {
                    $request->merge(['client_id' => is_numeric($trimmed) ? (int) $trimmed : $trimmed]);
                } else {
                    $request->merge(['client_id' => null]);
                }
            } elseif (is_array($rawClientId)) {
                $first = reset($rawClientId);
                $request->merge(['client_id' => ($first !== false && $first !== null && $first !== '') ? (is_numeric($first) ? (int) $first : $first) : null]);
            }
        }

        // 2. Unwrap discount if passed as "[10]" or string
        if ($request->has('discount')) {
            $rawDiscount = $request->input('discount');
            if (is_string($rawDiscount)) {
                $trimmed = trim($rawDiscount, " \t\n\r\0\x0B[]'\"");
                if ($trimmed !== '' && strtolower($trimmed) !== 'null') {
                    $request->merge(['discount' => is_numeric($trimmed) ? (float) $trimmed : $trimmed]);
                } else {
                    $request->merge(['discount' => 0]);
                }
            } elseif (is_array($rawDiscount)) {
                $first = reset($rawDiscount);
                $request->merge(['discount' => ($first !== false && $first !== null && $first !== '') ? (is_numeric($first) ? (float) $first : $first) : 0]);
            }
        }

        // 3. Decode items if passed as a JSON string
        if ($request->has('items') && is_string($request->input('items'))) {
            $decodedItems = json_decode($request->input('items'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedItems)) {
                $request->merge(['items' => $decodedItems]);
            }
        }

        // 4. If product_id is passed as text/array/json string like [10,13]
        if ($request->has('product_id') && (! $request->has('items') || ! is_array($request->input('items')) || empty($request->input('items')))) {
            $productIds = $this->parseArrayInput($request->input('product_id'));
            $qtys = $this->parseArrayInput($request->input('qty'));
            $prices = $this->parseArrayInput($request->input('price'));
            $gsts = $this->parseArrayInput($request->input('gst'));
            $notes = $this->parseArrayInput($request->input('note'));

            $normalizedItems = [];

            foreach ($productIds as $idx => $pid) {
                if ($pid !== null && $pid !== '') {
                    $qtyVal = $qtys[$idx] ?? (count($qtys) === 1 ? ($qtys[0] ?? 1) : 1);
                    $priceVal = $prices[$idx] ?? (count($prices) === 1 ? ($prices[0] ?? null) : null);
                    $gstVal = $gsts[$idx] ?? (count($gsts) === 1 ? ($gsts[0] ?? null) : null);
                    $noteVal = $notes[$idx] ?? (count($notes) === 1 ? ($notes[0] ?? null) : null);

                    $normalizedItems[] = [
                        'product_id' => is_numeric($pid) ? (int) $pid : $pid,
                        'qty' => is_numeric($qtyVal) ? (int) $qtyVal : $qtyVal,
                        'price' => ($priceVal !== null && $priceVal !== '') ? (is_numeric($priceVal) ? (float) $priceVal : $priceVal) : null,
                        'gst' => ($gstVal !== null && $gstVal !== '') ? (is_numeric($gstVal) ? (float) $gstVal : $gstVal) : null,
                        'note' => ($noteVal !== null && $noteVal !== '') ? trim((string) $noteVal) : null,
                    ];
                }
            }

            if (! empty($normalizedItems)) {
                $request->merge(['items' => $normalizedItems]);
            }
        }

        // 5. Clean up items array if present
        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            $cleaned = [];
            foreach ($items as $item) {
                if (is_array($item) && ! empty($item)) {
                    $cleaned[] = [
                        'product_id' => isset($item['product_id']) && is_numeric($item['product_id']) ? (int) $item['product_id'] : ($item['product_id'] ?? null),
                        'qty' => isset($item['qty']) && is_numeric($item['qty']) ? (int) $item['qty'] : ($item['qty'] ?? 1),
                        'price' => isset($item['price']) && $item['price'] !== null && $item['price'] !== '' && is_numeric($item['price']) ? (float) $item['price'] : ($item['price'] ?? null),
                        'gst' => isset($item['gst']) && $item['gst'] !== null && $item['gst'] !== '' && is_numeric($item['gst']) ? (float) $item['gst'] : ($item['gst'] ?? null),
                        'note' => isset($item['note']) && filled($item['note']) ? trim((string) $item['note']) : null,
                    ];
                }
            }
            if (! empty($cleaned)) {
                $request->merge(['items' => $cleaned]);
            }
        }
    }

    private function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => collect($validator->errors())->flatten()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}

