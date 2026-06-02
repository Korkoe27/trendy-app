<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Facades\DB;

class StocksSheet implements FromCollection, WithHeadings, WithTitle, WithMapping, ShouldAutoSize
{
    private $startDate;
    private $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        return DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->leftJoin('suppliers', 'stocks.supplier_id', '=', 'suppliers.id')
            ->whereBetween('stocks.restock_date', [$this->startDate, $this->endDate])
            ->select(
                'stocks.id',
                'products.name as product_name',
                'stocks.free_units',
                'stocks.total_units',
                'suppliers.name as supplier_name',
                'stocks.total_cost',
                'stocks.cost_price',
                'stocks.cost_margin',
                'stocks.notes',
                'stocks.restock_date'
            )
            ->orderBy('stocks.restock_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID', 'Product Name', 'Free Units', 'Total Units', 'Supplier',
            'Total Cost (GH₵)', 'Cost Price (GH₵)', 'Cost Margin (GH₵)',
            'Notes', 'Restock Date',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->product_name,
            $row->free_units ?? 0,
            $row->total_units,
            $row->supplier_name ?? 'N/A',
            number_format($row->total_cost, 2),
            number_format($row->cost_price, 2),
            number_format($row->cost_margin, 2),
            $row->notes ?? '',
            $row->restock_date,
        ];
    }

    public function title(): string
    {
        return 'Stocks';
    }
}
