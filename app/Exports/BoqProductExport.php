<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BoqProductExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $products)
    {
    }

    public function collection(): Collection
    {
        return $this->products->map(function ($product) {
            return [
                'category' => $product->category?->name,
                'name' => $product->name,
                'brand' => $product->brand,
                'model' => $product->model,
                'unit' => $product->unit,
                'price' => $product->price,
                'gst' => $product->gst,
                'description' => $product->description,
                'is_active' => $product->is_active ? 'yes' : 'no',
                'created_at' => optional($product->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($product->updated_at)->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'category',
            'name',
            'brand',
            'model',
            'unit',
            'price',
            'gst',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }
}
