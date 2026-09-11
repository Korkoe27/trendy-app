<?php

namespace App\Livewire\Pages;

use App\Models\Categories;
use App\Models\Product;
use App\Models\Stock;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class StockMovements extends Component
{
    use WithoutUrlPagination, WithPagination;

    public $searchTerm = '';

    public $selectedCategory = 'all';

    public $showTrendModal = false;

    public $selectedProduct = null;

    public $productMovements = [];

    public $productSummary = [];

    protected $queryString = ['searchTerm', 'selectedCategory'];

    public function render()
    {
        $categories = Categories::orderBy('name')->get();

        $products = Product::with('category', 'currentStock')
            ->when($this->searchTerm !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->searchTerm.'%')
                        ->orWhere('sku', 'like', '%'.$this->searchTerm.'%')
                        ->orWhere('barcode', 'like', '%'.$this->searchTerm.'%');
                });
            })
            ->when($this->selectedCategory !== 'all', function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('name', $this->selectedCategory);
                });
            })
            ->paginate(12);

        return view('livewire.pages.stock-movements', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function openTrendModal($productId)
    {
        $this->selectedProduct = Product::with('category')->find($productId);

        if (! $this->selectedProduct) {
            return;
        }

        $this->buildMovements($productId);

        $this->showTrendModal = true;
    }

    public function closeTrendModal()
    {
        $this->showTrendModal = false;
        $this->selectedProduct = null;
        $this->productMovements = [];
        $this->productSummary = [];
    }

    private function buildMovements($productId)
    {
        $stocks = Stock::with('supplier')
            ->where('product_id', $productId)
            ->orderBy('created_at', 'asc')
            ->get();

        $movements = [];
        $previousTotal = 0;
        $totalPurchased = 0;
        $totalSold = 0;
        $totalDamaged = 0;
        $firstPurchaseDate = null;
        $lastMovementDate = null;

        foreach ($stocks as $stock) {
            $metadata = is_string($stock->metadata) ? json_decode($stock->metadata, true) : ($stock->metadata ?? []);
            $metadata = is_array($metadata) ? $metadata : [];
            $source = $metadata['source'] ?? null;

            $type = match ($source) {
                'daily_inventory' => 'inventory',
                'inventory_edit' => 'edit',
                default => 'purchase',
            };

            $opening = $previousTotal;
            $closing = (float) $stock->total_units;
            $change = $closing - $opening;

            $unitsSold = null;
            $damaged = null;
            $credit = null;

            if ($source === 'daily_inventory' && isset($metadata['units_sold'])) {
                $unitsSold = (float) $metadata['units_sold'];
                $damaged = isset($metadata['damaged_units']) ? (float) $metadata['damaged_units'] : 0;
                $credit = isset($metadata['credit_units']) ? (float) $metadata['credit_units'] : 0;
            }

            if ($type === 'purchase') {
                $totalPurchased += max(0, $change);
                $firstPurchaseDate ??= $stock->created_at->format('Y-m-d');
            }

            if ($unitsSold !== null) {
                $totalSold += $unitsSold;
                $totalDamaged += $damaged ?? 0;
            }

            $date = $stock->created_at->format('Y-m-d');

            $movements[] = [
                'id' => $stock->id,
                'date' => $date,
                'date_full' => $stock->created_at->format('M j, Y'),
                'time' => $stock->created_at->format('g:i A'),
                'type' => $type,
                'opening' => $opening,
                'closing' => $closing,
                'change' => $change,
                'units_sold' => $unitsSold,
                'damaged' => $damaged,
                'credit' => $credit,
                'supplier' => $stock->supplier?->name,
                'notes' => $stock->notes,
            ];

            $previousTotal = $closing;
            $lastMovementDate = $lastMovementDate === null ? $date : $lastMovementDate;
        }

        $lastMovementDate = $stocks->isNotEmpty()
            ? $stocks->last()->created_at->format('Y-m-d')
            : null;

        $this->productMovements = $movements;

        $this->productSummary = [
            'current_stock' => $previousTotal,
            'total_purchased' => $totalPurchased,
            'total_sold' => $totalSold,
            'total_damaged' => $totalDamaged,
            'count' => count($movements),
            'first_purchase_date' => $firstPurchaseDate,
            'last_movement_date' => $lastMovementDate,
            'current_cost_price' => $stocks->isNotEmpty() ? $stocks->last()->cost_price : null,
        ];
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }
}
