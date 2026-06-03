<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Facades\DB;

class DailySalesSheet implements FromCollection, WithHeadings, WithTitle, WithMapping, ShouldAutoSize
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
        return DB::table('daily_sales')
            ->join('products', 'daily_sales.product_id', '=', 'products.id')
            ->whereBetween('daily_sales.sales_date', [$this->startDate, $this->endDate])
            ->select(
                'daily_sales.sales_date',
                'products.name as product_name',
                'daily_sales.opening_stock',
                'daily_sales.closing_stock',
                'daily_sales.opening_boxes',
                'daily_sales.closing_boxes',
                'daily_sales.damaged_units',
                'daily_sales.loss_amount',
                'daily_sales.credit_units',
                'daily_sales.credit_amount',
                'daily_sales.unit_profit',
                'daily_sales.total_amount',
                DB::raw('(daily_sales.opening_stock - daily_sales.closing_stock - COALESCE(daily_sales.damaged_units, 0) - COALESCE(daily_sales.credit_units, 0)) as units_sold')
            )
            ->orderBy('daily_sales.sales_date')
            ->orderBy('products.name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date', 'Product Name', 'Opening Stock', 'Closing Stock',
            'Opening Boxes', 'Closing Boxes', 'Damaged Units', 'Loss Amount (GH₵)',
            'Credit Units', 'Credit Amount (GH₵)', 'Unit Profit (GH₵)', 'Total Amount (GH₵)',
            'Units Sold',
        ];
    }

    public function map($row): array
    {
        return [
            $row->sales_date,
            $row->product_name,
            $row->opening_stock ?? 0,
            $row->closing_stock ?? 0,
            $row->opening_boxes ?? 0,
            $row->closing_boxes ?? 0,
            $row->damaged_units ?? 0,
            number_format($row->loss_amount ?? 0, 2),
            $row->credit_units ?? 0,
            number_format($row->credit_amount ?? 0, 2),
            number_format($row->unit_profit ?? 0, 2),
            number_format($row->total_amount, 2),
            $row->units_sold ?? 0,
        ];
    }

    public function title(): string
    {
        return 'Inventory';
    }
}
