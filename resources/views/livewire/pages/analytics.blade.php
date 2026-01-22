<div>
    {{-- Loading Overlay --}}
    @if(!$metricsLoaded)
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 shadow-2xl">
            <div class="flex flex-col items-center space-y-4">
                <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-lg font-semibold text-gray-700">Loading Analytics...</p>
            </div>
        </div>
    </div>
    @endif

    <div class="space-y-6">
        {{-- Header Section with Enhanced Filters --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                {{-- Title --}}
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Sales Analytics Dashboard</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                    </p>
                </div>

                {{-- Filter Controls --}}
                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    {{-- Quick Timeframe Buttons --}}
                    <div class="flex flex-wrap gap-2">
                        <button wire:click="$set('timeframe', '7d')" 
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 
                                       {{ $timeframe === '7d' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            7 Days
                        </button>
                        <button wire:click="$set('timeframe', '30d')" 
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 
                                       {{ $timeframe === '30d' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            30 Days
                        </button>
                        <button wire:click="$set('timeframe', '90d')" 
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 
                                       {{ $timeframe === '90d' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            90 Days
                        </button>
                        <button wire:click="$set('timeframe', 'month')" 
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 
                                       {{ $timeframe === 'month' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            This Month
                        </button>
                        <button wire:click="$set('timeframe', 'year')" 
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 
                                       {{ $timeframe === 'year' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            This Year
                        </button>
                    </div>

                    {{-- Custom Date Range Toggle --}}
                    <button wire:click="$set('timeframe', 'custom')" 
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center gap-2
                                   {{ $timeframe === 'custom' ? 'bg-purple-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Custom Range
                    </button>

                    {{-- Specific Month Toggle --}}
                    <button wire:click="$set('timeframe', 'specific_month')" 
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center gap-2
                                   {{ $timeframe === 'specific_month' ? 'bg-purple-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Select Month
                    </button>
                </div>
            </div>

            {{-- Custom Date Range Picker --}}
            @if($timeframe === 'custom')
            <div class="mt-6 p-4 bg-purple-50 rounded-lg border-2 border-purple-200">
                <div class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" 
                               wire:model.live="startDate"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" 
                               wire:model.live="endDate"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <button wire:click="loadMetrics" 
                            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium whitespace-nowrap">
                        Apply Filter
                    </button>
                </div>
            </div>
            @endif

            {{-- Specific Month Picker --}}
            @if($timeframe === 'specific_month')
            <div class="mt-6 p-4 bg-purple-50 rounded-lg border-2 border-purple-200">
                <div class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Month</label>
                        <select wire:model.live="selectedMonth"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Choose a month...</option>
                            @foreach($availableMonths as $month)
                                <option value="{{ $month }}">{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="loadMetrics" 
                            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium whitespace-nowrap">
                        Load Data
                    </button>
                </div>
            </div>
            @endif
        </div>

        {{-- Metrics Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Revenue Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Drink Sales</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">GH₵ {{ number_format($drinksTotal, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Avg daily: GH₵ {{ number_format($averageDailyRevenue, 2) }}</p>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
                                {{ $revenueGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            @if($revenueGrowth >= 0)
                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            @else
                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            @endif
                        </svg>
                        {{ abs($revenueGrowth) }}%
                    </div>
                </div>
            </div>

            {{-- Profit Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Gross Profit</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">GH₵ {{ number_format($totalProfit, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Margin: {{ number_format($grossProfitMargin, 1) }}% (Avg: {{ number_format($averageProfitMargin, 1) }}%)</p>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
                                {{ $profitGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            @if($profitGrowth >= 0)
                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            @else
                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            @endif
                        </svg>
                        {{ abs($profitGrowth) }}%
                    </div>
                </div>
            </div>

            {{-- Units Sold Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Units Sold</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($totalItemsSold) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Damage rate: {{ number_format($damageRate, 1) }}%</p>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
                                {{ $itemsSoldGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            @if($itemsSoldGrowth >= 0)
                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            @else
                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            @endif
                        </svg>
                        {{ abs($itemsSoldGrowth) }}%
                    </div>
                </div>
            </div>

            {{-- Food Sales Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Food Sales</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">GH₵ {{ number_format($totalFoodSales, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Avg daily: GH₵ {{ number_format($averageDailyFoodSales, 2) }}</p>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
                                {{ $foodSalesGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            @if($foodSalesGrowth >= 0)
                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            @else
                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            @endif
                        </svg>
                        {{ abs($foodSalesGrowth) }}%
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Section (Lazy Loaded) --}}
        @if(!$chartsLoaded)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12">
            <div class="text-center">
                <button wire:click="loadCharts" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                    Load Charts & Detailed Analytics
                </button>
                <p class="text-sm text-gray-500 mt-2">Click to load visualizations and product performance data</p>
            </div>
        </div>
        @else
        {{-- Charts Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Revenue & Profit Trend --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue & Profit Trend</h3>
                <canvas id="revenueProfitChart" class="w-full" style="height: 300px;"></canvas>
            </div>

            {{-- Daily Food Sales --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Food Sales</h3>
                <canvas id="foodSalesChart" class="w-full" style="height: 300px;"></canvas>
            </div>  

            {{-- Daily Collection Discrepancies --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        Daily Collection Discrepancies
    </h3>

    <p class="text-sm text-gray-600 mb-4">
        Total Loss:
        <span
            class="font-semibold text-lg
                {{ $accumulatedLosses < 0 ? 'text-red-500' : ($accumulatedLosses > 0 ? 'text-green-400' : 'text-gray-400') }}
">
            GH₵ {{ number_format($accumulatedLosses, 2) }}
        </span>
        •
        On the House:
        <span class="font-semibold text-lg text-amber-500">
            GH₵ {{ number_format($totalOnTheHouse, 2) }}
        </span>
    </p>

    <canvas id="lossesChart" class="w-full" style="height: 300px;"></canvas>
</div>

            {{-- Payment Method Breakdown --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Method Breakdown</h3>
                <div class="space-y-4">
                    @foreach ($paymentMethodDistribution as $method)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">{{ $method['method'] }}</span>
                            <span class="text-sm font-semibold text-gray-900">GH₵ {{ number_format($method['amount'], 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                 style="width: {{ $method['percentage'] }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $method['percentage'] }}%</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Product Performance Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Top Selling Products --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Selling Products</h3>
                <div class="space-y-3">
                    @forelse(array_slice($topSellingProducts, 0, 8) as $i => $p)
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                            {{ $i + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $p['product_name'] }}</p>
                            <p class="text-sm text-gray-500">{{ $p['category'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ number_format($p['units_sold']) }} units</p>
                            <p class="text-xs text-gray-600">GH₵ {{ number_format($p['revenue'], 0) }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 text-center py-8">No sales data</p>
                    @endforelse
                </div>
            </div>

            {{-- Most Profitable Products --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Most Profitable Products</h3>
                <div class="space-y-3">
                    @forelse(array_slice($mostProfitableProducts, 0, 8) as $i => $p)
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                            {{ $i + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-base font-medium text-gray-900 uppercase truncate">{{ $p['product_name'] }}</p>
                            <p class="text-sm text-amber-500 italic">{{ number_format($p['units_sold']) }} units</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-green-600">GH₵ {{ number_format($p['total_profit'], 2) }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 text-center py-8">No data</p>
                    @endforelse
                </div>
            </div>

            {{-- Highest Loss Products --}}
            {{-- Least Performing Products --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Least Performing Products</h3>
    <div class="space-y-3">
        @forelse(array_slice($leastPerformingProducts, 0, 8) as $i => $p)
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
            <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                {{ $i + 1 }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-base font-medium text-gray-900 uppercase truncate">{{ $p['product_name'] }}</p>
                <p class="text-xs italic capitalize text-green-500">{{ $p['category'] }} • <span class="text-sm text-amber-500">{{ number_format($p['units_sold']) }} units</span></p>
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold text-orange-600">GH₵ {{ number_format($p['revenue'], 2) }}</p>
                <p class="text-xs text-gray-600">{{ number_format($p['profit_margin'], 1) }}% margin</p>
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-500 text-center py-8">No data available</p>
        @endforelse
    </div>
</div>
        </div>

        {{-- Inventory & Categories Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Inventory Overview --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Inventory Overview</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600">GH₵ {{ number_format($totalInventoryValue, 0) }}</p>
                        <p class="text-sm text-gray-600 mt-1">Current Value</p>
                    </div>
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <p class="text-2xl font-bold text-yellow-600">{{ $lowStockProducts }}</p>
                        <p class="text-sm text-gray-600 mt-1">Low Stock</p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg">
                        <p class="text-2xl font-bold text-red-600">{{ $outOfStockProducts }}</p>
                        <p class="text-sm text-gray-600 mt-1">Out of Stock</p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($inventoryTurnoverRate, 2) }}x</p>
                        <p class="text-sm text-gray-600 mt-1">Inventory Turnover</p>
                    </div>
                </div>
            </div>

            {{-- Top Categories --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Categories</h3>
                <div class="space-y-3">
                    @forelse(array_slice($categoryPerformance, 0, 6) as $cat)
                    <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-900">{{ $cat['category_name'] }}</p>
                            <p class="text-sm font-semibold text-gray-900">GH₵ {{ number_format($cat['revenue'], 0) }}</p>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full transition-all duration-300" 
                                 style="width: {{ $topCategory && $topCategory['revenue'] > 0 ? ($cat['revenue'] / $topCategory['revenue']) * 100 : 0 }}%"></div>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                            <span>{{ number_format($cat['units_sold']) }} units</span>
                            <span>{{ number_format($cat['profit_margin'], 1) }}% margin</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 text-center py-8">No category data available</p>
                    @endforelse
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Chart.js Initialization Scripts --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('livewire:init', function () {
        let charts = {};

        function initCharts() {
            // Destroy existing charts
            Object.values(charts).forEach(chart => chart?.destroy());
            charts = {};

            // Revenue & Profit Trend Chart
            const revenueProfitCtx = document.getElementById('revenueProfitChart');
            if (revenueProfitCtx) {
                charts.revenueProfit = new Chart(revenueProfitCtx, {
                    type: 'line',
                    data: {
                        labels: @json(array_column($dailySalesData, 'date')),
                        datasets: [
                            {
                                label: 'Revenue',
                                data: @json(array_column($dailySalesData, 'revenue')),
                                borderColor: '#3B82F6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            },
                            {
                                label: 'Profit',
                                data: @json(array_column($dailyProfitData, 'profit')),
                                borderColor: '#10B981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'top',
                                labels: { font: { size: 12 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': GH₵ ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                ticks: { 
                                    callback: value => 'GH₵' + value.toLocaleString()
                                }
                            }
                        }
                    }
                });
            }

            // Food Sales Chart
            const foodSalesCtx = document.getElementById('foodSalesChart');
            if (foodSalesCtx) {
                charts.foodSales = new Chart(foodSalesCtx, {
                    type: 'bar',
                    data: {
                        labels: @json(array_column($dailyFoodSalesData, 'date')),
                        datasets: [{
                            label: 'Food Sales',
                            data: @json(array_column($dailyFoodSalesData, 'amount')),
                            backgroundColor: '#F59E0B',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: context => 'GH₵ ' + context.parsed.y.toLocaleString()
                                }
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                ticks: {
                                    callback: value => 'GH₵' + value.toLocaleString()
                                }
                            }
                        }
                    }
                });
            }

// Daily Losses Chart
const lossesCtx = document.getElementById('lossesChart');
if (lossesCtx) {
    charts.losses = new Chart(lossesCtx, {
        type: 'bar',
        data: {
            labels: @json(array_column($dailyLossesData, 'date')),
            datasets: [
                {
                    label: 'Collection Shortfall',
                    data: @json(array_column($dailyLossesData, 'loss')),
                    backgroundColor: '#EF4444',
                    borderRadius: 4
                },
                {
                    label: 'On the House',
                    data: @json(array_column($dailyLossesData, 'on_the_house')),
                    backgroundColor: '#F97316',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: context => context.dataset.label + ': GH₵ ' + context.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: {
                        callback: value => 'GH₵' + value.toLocaleString()
                    }
                }
            }
        }
    });
}

        // Initialize charts on page load if charts are loaded
        if (@json($chartsLoaded)) {
            initCharts();
        }

        // Listen for chart load event
        Livewire.on('chartsLoaded', () => {
            setTimeout(initCharts, 100);
        });
    });
    </script>
    @endpush