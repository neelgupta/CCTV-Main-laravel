<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StaffExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $staffs)
    {
    }

    public function collection(): Collection
    {
        return $this->staffs->map(function ($staff) {
            return [
                'name' => $staff->name,
                'email' => $staff->user?->email,
                'password' => '',
                'phone' => $staff->contact_number,
                'employee_id' => $staff->employee_id,
                'role' => $staff->user?->roleRecord?->name ?? $staff->user?->role,
                'department' => $staff->department,
                'join_date' => optional($staff->join_date)->format('Y-m-d'),
                'created_at' => optional($staff->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($staff->updated_at)->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'name',
            'email',
            'password',
            'phone',
            'employee_id',
            'role',
            'department',
            'join_date',
            'created_at',
            'updated_at',
        ];
    }
}
