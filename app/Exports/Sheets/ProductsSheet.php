<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Models\Product;

class ProductsSheet implements FromCollection, WithHeadings, WithTitle, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return Product::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID', 'Name', 'Category', 'SKU', 'Barcode',
            'Stock Limit', 'Selling Price (GH₵)', 'Units Per Box',
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->category->name ?? 'N/A',
            $product->sku ?? 'N/A',
            $product->barcode ?? 'N/A',
            $product->stock_limit ?? 'N/A',
            number_format($product->selling_price, 2),
            $product->units_per_box ?? 0,
        ];
    }

    public function title(): string
    {
        return 'Products';
    }
}
