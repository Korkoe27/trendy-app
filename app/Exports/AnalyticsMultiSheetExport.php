<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\AnalyticsSummarySheet;
use App\Exports\Sheets\ProductsSheet;
use App\Exports\Sheets\StocksSheet;
use App\Exports\Sheets\DailySalesSheet;

class AnalyticsMultiSheetExport implements WithMultipleSheets
{
    private $startDate;
    private $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function sheets(): array
    {
        return [
            new AnalyticsSummarySheet($this->startDate, $this->endDate),
            new ProductsSheet(),
            new StocksSheet($this->startDate, $this->endDate),
            new DailySalesSheet($this->startDate, $this->endDate),
        ];
    }
}
