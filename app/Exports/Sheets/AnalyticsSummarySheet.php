<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Facades\DB;

class AnalyticsSummarySheet implements FromCollection, WithHeadings, WithTitle, WithMapping, ShouldAutoSize
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
        return DB::table('daily_sales_summaries')
            ->whereBetween('sales_date', [$this->startDate, $this->endDate])
            ->select(
                'sales_date',
                DB::raw('SUM(drinks_total) as drink_sales'),
                DB::raw('SUM(total_profit) as gross_profit'),
                DB::raw('SUM(items_sold) as units_sold'),
                DB::raw('SUM(food_total) as food_sales'),
                DB::raw('SUM(snooker) as snooker_sales'),
                DB::raw('SUM(total_cash) as cash'),
                DB::raw('SUM(total_momo) as momo'),
                DB::raw('SUM(total_hubtel) as hubtel')
            )
            ->groupBy('sales_date')
            ->orderBy('sales_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date', 'Drink Sales (GH₵)', 'Gross Profit (GH₵)', 'Units Sold',
            'Food Sales (GH₵)', 'Snooker Sales (GH₵)', 'Cash (GH₵)',
            'Momo (GH₵)', 'Hubtel (GH₵)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->sales_date,
            number_format($row->drink_sales, 2),
            number_format($row->gross_profit, 2),
            $row->units_sold,
            number_format($row->food_sales, 2),
            number_format($row->snooker_sales, 2),
            number_format($row->cash, 2),
            number_format($row->momo, 2),
            number_format($row->hubtel, 2),
        ];
    }

    public function title(): string
    {
        return 'Analytics';
    }
}
