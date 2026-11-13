<?php

namespace App\Http\Resources;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class StaffProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nameParts = $this->splitName((string) ($this->name ?? $this->user?->name ?? ''));

        return [
            'id' => $this->id,
            'employee_code' => $this->employee_id,
            
            'full_name' => $nameParts['full_name'],
            'email' => $this->user?->email,
            'phone' => $this->contact_number,
            'designation' => $this->role?->name,
            'department' => $this->department,
            'role' => $this->user?->normalizedRole(),
            'company' => [
                'id' => $this->user?->company?->id,
                'name' => $this->user?->company?->name,
            ],
            'profile_image' => $this->profileImageUrl(),
            'joining_date' => $this->join_date ? (string) $this->join_date : null,
            'status' => $this->user?->is_active ? 'active' : 'inactive',
            'address' => [
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
                'zipcode' => $this->zipcode,
            ],
        ];
    }

    private function profileImageUrl(): ?string
    {
        if (empty($this->profile_photo)) {
            return null;
        }

        $rawPath = str_replace('\\', '/', (string) $this->profile_photo);
        $path = ltrim($rawPath, '/');

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $media = MediaFile::query()
            ->where('company_id', $this->company_id)
            ->where('path', $path)
            ->first();

        $disk = (string) ($media?->disk ?: (env('AWS_ACCESS_KEY_ID') ? 's3' : 'public'));

        return route('api.staff.profile-image.public', [
            'company_id' => $this->company_id,
            'disk' => $disk,
            'path' => $path,
            'name' => basename($path),
        ]);
    }

    private function splitName(string $name): array
    {
        $name = trim(Str::squish($name));

        if ($name === '') {
            return [
                'first_name' => null,
                'last_name' => null,
                'full_name' => null,
            ];
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $parts[0] ?? null;
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: $name,
        ];
    }
}
