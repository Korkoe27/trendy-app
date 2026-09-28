<?php

namespace App\Livewire\Pages;

use App\Models\Categories;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
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
        $ledgerMovements = StockMovement::where('product_id', $productId)
            ->orderBy('occurred_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Safety net: a product whose history hasn't been backfilled into the ledger yet
        // (see stock-movements:backfill) would otherwise show as empty. Fall back to the
        // legacy snapshot-diffing read for that product only, until it's backfilled.
        if ($ledgerMovements->isEmpty() && Stock::where('product_id', $productId)->exists()) {
            $this->buildMovementsFromStockSnapshots($productId);

            return;
        }

        $referenceStockIds = $ledgerMovements->pluck('reference_id')->filter()->unique();

        $stocksById = Stock::with('supplier')
            ->whereIn('id', $referenceStockIds)
            ->get()
            ->keyBy('id');

        $movements = [];
        $runningTotal = 0;
        $totalPurchased = 0;
        $totalSold = 0;
        $totalDamaged = 0;
        $firstPurchaseDate = null;
        $lastMovementDate = null;

        foreach ($ledgerMovements as $movement) {
            $metadata = $movement->metadata ?? [];
            $relatedStock = $stocksById->get($movement->reference_id);

            $opening = $runningTotal;
            $change = (float) $movement->quantity_delta;
            $closing = $opening + $change;
            $runningTotal = $closing;

            $unitsSold = isset($metadata['units_sold']) ? (float) $metadata['units_sold'] : null;
            $damaged = isset($metadata['damaged_units']) ? (float) $metadata['damaged_units'] : null;
            $credit = isset($metadata['credit_units']) ? (float) $metadata['credit_units'] : null;

            if ($movement->type === 'purchase') {
                $totalPurchased += max(0, $change);
                $firstPurchaseDate ??= $movement->occurred_at->format('Y-m-d');
            }

            if ($unitsSold !== null) {
                $totalSold += $unitsSold;
                $totalDamaged += $damaged ?? 0;
            }

            $date = $movement->occurred_at->format('Y-m-d');

            $movements[] = [
                'id' => $movement->id,
                'date' => $date,
                'date_full' => $movement->occurred_at->format('M j, Y'),
                'time' => $movement->occurred_at->format('g:i A'),
                'type' => $movement->type,
                'opening' => $opening,
                'closing' => $closing,
                'change' => $change,
                'units_sold' => $unitsSold,
                'damaged' => $damaged,
                'credit' => $credit,
                'supplier' => $relatedStock?->supplier?->name,
                'notes' => $relatedStock?->notes,
            ];

            $lastMovementDate = $date;
        }

        $this->productMovements = $movements;

        $latestStock = Stock::where('product_id', $productId)->latest('created_at')->first();

        $this->productSummary = [
            'current_stock' => $runningTotal,
            'total_purchased' => $totalPurchased,
            'total_sold' => $totalSold,
            'total_damaged' => $totalDamaged,
            'count' => count($movements),
            'first_purchase_date' => $firstPurchaseDate,
            'last_movement_date' => $lastMovementDate,
            'current_cost_price' => $latestStock?->cost_price,
        ];
    }

    /**
     * Legacy read path: derives movements by diffing stocks snapshots. Only used as a
     * fallback for products that haven't been backfilled into the stock_movements ledger.
     */
    private function buildMovementsFromStockSnapshots($productId)
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
            $movementDate = $stock->occurred_at ?? $stock->created_at;
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
                $firstPurchaseDate ??= $movementDate->format('Y-m-d');
            }

            if ($unitsSold !== null) {
                $totalSold += $unitsSold;
                $totalDamaged += $damaged ?? 0;
            }

            $date = $movementDate->format('Y-m-d');

            $movements[] = [
                'id' => $stock->id,
                'date' => $date,
                'date_full' => $movementDate->format('M j, Y'),
                'time' => $movementDate->format('g:i A'),
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
            $lastMovementDate = $date;
        }

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
