<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\City;
use App\Models\State;
use App\Services\ClientRelationSyncService;
use App\Services\ClientSyncService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyClientStoreController extends Controller
{
    public function update(Request $request, int $clientId, ClientSyncService $sync, ClientRelationSyncService $relationSync): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can update clients.');

        $client = Client::query()
            ->where('company_id', (int) $user->company_id)
            ->findOrFail($clientId);

        $companyId = (int) $user->company_id;
        $stateId = (int) $request->input('state_id', $client->state_id);

        $cities = City::query()
            ->where('state_id', $stateId)
            ->pluck('id')
            ->all();

        $states = State::query()->pluck('id')->all();

        $validated = $this->validateJson($request, [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:191'],
            'email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'gst_number' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9A-Z]{15}$/'],
            'pan_number' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'state_id' => ['sometimes', 'required', 'integer', Rule::in($states)],
            'city_id' => ['sometimes', 'required', 'integer', Rule::in($cities)],
            'address' => ['sometimes', 'nullable', 'string', 'min:5', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
        ], [
            'name.required' => 'Client name is required.',
            'name.min' => 'Client name must be at least 2 characters.',
            'name.max' => 'Client name may not be greater than 191 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email may not be greater than 191 characters.',
            'phone.max' => 'Phone number may not be greater than 20 characters.',
            'gst_number.max' => 'GST number may not be greater than 20 characters.',
            'gst_number.regex' => 'Please enter a valid GST number.',
            'pan_number.max' => 'PAN number may not be greater than 20 characters.',
            'pan_number.regex' => 'Please enter a valid PAN number.',
            'state_id.required' => 'State is required.',
            'state_id.in' => 'Selected state is not available.',
            'city_id.required' => 'City is required.',
            'city_id.in' => 'Selected city is not available for the chosen state.',
            'address.min' => 'Address must be at least 5 characters.',
            'address.max' => 'Address may not be greater than 1000 characters.',
            'latitude.numeric' => 'Latitude must be a valid number.',
            'longitude.numeric' => 'Longitude must be a valid number.',
        ]);

        $payload = [
            'name' => array_key_exists('name', $validated) ? trim((string) $validated['name']) : $client->name,
            'email' => array_key_exists('email', $validated)
                ? (filled($validated['email'] ?? null) ? strtolower(trim((string) $validated['email'])) : null)
                : $client->email,
            'phone' => array_key_exists('phone', $validated)
                ? (filled($validated['phone'] ?? null) ? $this->normalizePhone($validated['phone']) : null)
                : $this->normalizePhone($client->phone ?: $client->mobile),
            'gst_number' => array_key_exists('gst_number', $validated) ? strtoupper(trim((string) ($validated['gst_number'] ?? ''))) : $client->gst_number,
            'pan_number' => array_key_exists('pan_number', $validated) ? strtoupper(trim((string) ($validated['pan_number'] ?? ''))) : $client->pan_number,
            'state_id' => array_key_exists('state_id', $validated) ? (int) $validated['state_id'] : $client->state_id,
            'city_id' => array_key_exists('city_id', $validated) ? (int) $validated['city_id'] : $client->city_id,
            'address' => array_key_exists('address', $validated)
                ? (filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null)
                : $client->address,
            'latitude' => array_key_exists('latitude', $validated) ? (filled($validated['latitude'] ?? null) ? (float) $validated['latitude'] : null) : $client->latitude,
            'longitude' => array_key_exists('longitude', $validated) ? (filled($validated['longitude'] ?? null) ? (float) $validated['longitude'] : null) : $client->longitude,
        ];

        $duplicateMessages = $this->duplicateClientMessages($companyId, $payload['email'] ?? null, $payload['phone'] ?? null, (int) $client->id);
        if ($duplicateMessages) {
            throw ValidationException::withMessages($duplicateMessages);
        }

        $previous = $relationSync->snapshotClient($client);

        try {
            $client->fill([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'mobile' => $payload['phone'],
                'phone' => $payload['phone'],
                'gst_number' => $payload['gst_number'] ?: null,
                'pan_number' => $payload['pan_number'] ?: null,
                'state_id' => $payload['state_id'],
                'city_id' => $payload['city_id'],
                'address' => $payload['address'],
                'latitude' => $payload['latitude'],
                'longitude' => $payload['longitude'],
            ]);
            $client->save();
            $relationSync->syncClientRecord($client->fresh(), $previous);
        } catch (\Illuminate\Database\QueryException $exception) {
            if ($this->isEmailConflictException($exception)) {
                throw ValidationException::withMessages([
                    'email' => 'This email exists. Contact admin to get more details.',
                ]);
            }

            throw $exception;
        }

        try {
            $sync->sendPasswordSetup($client);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'Client updated successfully.',
            'data' => [
                'client' => $client->fresh(['user:id,name,email', 'state:id,name', 'city:id,name,state_id']),
            ],
        ]);
    }

    public function store(Request $request, ClientSyncService $sync): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can create clients.');

        $companyId = (int) $user->company_id;

        $states = State::query()->pluck('id')->all();
        $cities = City::query()
            ->when($request->filled('state_id'), fn ($query) => $query->where('state_id', $request->integer('state_id')))
            ->pluck('id')
            ->all();

        $validated = $this->validateJson($request, [
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'email' => ['nullable', 'email:rfc,dns', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gst_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Z]{15}$/'],
            'pan_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'state_id' => ['required', 'integer', Rule::in($states)],
            'city_id' => ['required', 'integer', Rule::in($cities)],
            'address' => ['nullable', 'string', 'min:5', 'max:1000'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ], [
            'name.required' => 'Client name is required.',
            'name.min' => 'Client name must be at least 2 characters.',
            'name.max' => 'Client name may not be greater than 191 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email may not be greater than 191 characters.',
            'phone.max' => 'Phone number may not be greater than 20 characters.',
            'gst_number.max' => 'GST number may not be greater than 20 characters.',
            'gst_number.regex' => 'Please enter a valid GST number.',
            'pan_number.max' => 'PAN number may not be greater than 20 characters.',
            'pan_number.regex' => 'Please enter a valid PAN number.',
            'state_id.required' => 'State is required.',
            'state_id.in' => 'Selected state is not available.',
            'city_id.required' => 'City is required.',
            'city_id.in' => 'Selected city is not available for the chosen state.',
            'address.min' => 'Address must be at least 5 characters.',
            'address.max' => 'Address may not be greater than 1000 characters.',
            'latitude.numeric' => 'Latitude must be a valid number.',
            'longitude.numeric' => 'Longitude must be a valid number.',
        ]);

        $validated['name'] = trim((string) $validated['name']);
        $validated['email'] = filled($validated['email'] ?? null)
            ? strtolower(trim((string) $validated['email']))
            : null;
        $validated['phone'] = filled($validated['phone'] ?? null)
            ? $this->normalizePhone($validated['phone'])
            : null;
        $validated['gst_number'] = strtoupper(trim((string) ($validated['gst_number'] ?? '')));
        $validated['pan_number'] = strtoupper(trim((string) ($validated['pan_number'] ?? '')));
        $validated['state_id'] = (int) $validated['state_id'];
        $validated['city_id'] = (int) $validated['city_id'];
        $validated['address'] = filled($validated['address'] ?? null)
            ? trim((string) $validated['address'])
            : null;
        $validated['latitude'] = filled($validated['latitude'] ?? null) ? (float) $validated['latitude'] : null;
        $validated['longitude'] = filled($validated['longitude'] ?? null) ? (float) $validated['longitude'] : null;

        $duplicateMessages = $this->duplicateClientMessages($companyId, $validated['email'] ?? null, $validated['phone'] ?? null);
        if ($duplicateMessages) {
            throw ValidationException::withMessages($duplicateMessages);
        }

        try {
            $client = $sync->upsert(array_merge($validated, [
                'company_id' => $companyId,
                'mobile' => $validated['phone'] ?? null,
                'is_direct_client' => true,
            ]));
        } catch (\Illuminate\Database\QueryException $exception) {
            if ($this->isEmailConflictException($exception)) {
                throw ValidationException::withMessages([
                    'email' => 'This email exists. Contact admin to get more details.',
                ]);
            }

            throw $exception;
        }

        try {
            $sync->sendPasswordSetup($client);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'Client created successfully.',
            'data' => [
                'client' => $client->fresh(['user:id,name,email', 'state:id,name', 'city:id,name,state_id']),
            ],
        ], 201);
    }

    private function duplicateClientMessages(int $companyId, ?string $email, ?string $phone, ?int $ignoreClientId = null): array
    {
        $messages = [];

        if ($email) {
            $company = \App\Models\Company::query()->find($companyId);

            if ($company && $company->isInternalStaffOrAdminEmail($email)) {
                $messages['email'] = 'This email belongs to a company administrator or staff member and cannot be assigned to a client.';
            } else {
                $emailQuery = Client::query()
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(email) = ?', [$email]);

                if ($ignoreClientId !== null) {
                    $emailQuery->where('id', '!=', $ignoreClientId);
                }

                $emailExists = $emailQuery->exists();

                if ($emailExists) {
                    $messages['email'] = 'This email already exists. Open the Clients list to review matching records.';
                }
            }
        }

        if ($phone) {
            $phoneExists = Client::query()
                ->where('company_id', $companyId)
                ->when($ignoreClientId, fn ($query) => $query->whereKeyNot($ignoreClientId))
                ->get(['id', 'mobile', 'phone'])
                ->contains(function (Client $client) use ($phone) {
                    return $this->normalizePhone((string) $client->mobile) === $phone
                        || $this->normalizePhone((string) $client->phone) === $phone;
                });

            if ($phoneExists) {
                $messages['phone'] = 'This phone number already exists. Open the Clients list to review matching records.';
            }
        }

        return $messages;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim((string) $value));

        return filled($digits) ? $digits : null;
    }

    private function isEmailConflictException(\Illuminate\Database\QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'email')
            && (str_contains($message, 'duplicate') || str_contains($message, 'already been taken') || str_contains($message, 'unique'));
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
