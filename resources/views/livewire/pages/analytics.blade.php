
<div class="bg-gray-50">
    <div class="mx-auto space-y-8">

        <!-- Header & Timeframe Selector -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Sales Analytics Dashboard</h1>
                    <p class="text-gray-600 mt-2">
                        {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} –
                        {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button wire:click="$set('timeframe', '7d')"
                        class="px-5 py-3 rounded-xl font-medium transition-all {{ $timeframe === '7d' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        7 Days
                    </button>
                    <button wire:click="$set('timeframe', '30d')"
                        class="px-5 py-3 rounded-xl font-medium transition-all {{ $timeframe === '30d' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        30 Days
                    </button>
                    <button wire:click="$set('timeframe', '90d')"
                        class="px-5 py-3 rounded-xl font-medium transition-all {{ $timeframe === '90d' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        90 Days
                    </button>
                    <button wire:click="$set('timeframe', 'month')"
                        class="px-5 py-3 rounded-xl font-medium transition-all {{ $timeframe === 'month' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        This Month
                    </button>
                    <button wire:click="$set('timeframe', 'year')"
                        class="px-5 py-3 rounded-xl font-medium transition-all {{ $timeframe === 'year' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        This Year
                    </button>
                </div>
            </div>
        </div>

        <!-- Key Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Revenue -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-green-100 rounded-xl">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold px-3 py-1 rounded-full {{ $revenueGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $revenueGrowth >= 0 ? '+' : '' }}{{ $revenueGrowth }}%
                    </span>
                </div>
                <p class="text-gray-600 text-sm">Total Revenue</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">GH₵ {{ number_format($totalRevenue, 2) }}</p>
                <p class="text-sm text-gray-500 mt-2">Avg daily: GH₵ {{ number_format($averageDailyRevenue, 2) }}</p>
            </div>

            <!-- Total Profit -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-blue-100 rounded-xl">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold px-3 py-1 rounded-full {{ $profitGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $profitGrowth >= 0 ? '+' : '' }}{{ $profitGrowth }}%
                    </span>
                </div>
                <p class="text-gray-600 text-sm">Gross Profit</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">GH₵ {{ number_format($totalProfit, 2) }}</p>
                <p class="text-sm text-gray-500 mt-2">Margin: {{ number_format($grossProfitMargin, 1) }}% (Avg: {{ number_format($averageProfitMargin, 1) }}%)</p>
            </div>

            <!-- Items Sold -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-purple-100 rounded-xl">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold px-3 py-1 rounded-full {{ $itemsSoldGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $itemsSoldGrowth >= 0 ? '+' : '' }}{{ $itemsSoldGrowth }}%
                    </span>
                </div>
                <p class="text-gray-600 text-sm">Units Sold</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalItemsSold) }}</p>
                <p class="text-sm text-gray-500 mt-2">Damage rate: {{ number_format($damageRate, 1) }}%</p>
            </div>

            <!-- Food Sales -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-amber-100 rounded-xl">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m.4 8H5m14 0h-4m-5 0H7m12 8a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold px-3 py-1 rounded-full {{ $foodSalesGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $foodSalesGrowth >= 0 ? '+' : '' }}{{ $foodSalesGrowth }}%
                    </span>
                </div>
                <p class="text-gray-600 text-sm">Food Sales</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">GH₵ {{ number_format($totalFoodSales, 2) }}</p>
                <p class="text-sm text-gray-500 mt-2">Avg daily: GH₵ {{ number_format($averageDailyFoodSales, 2) }}</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Revenue & Profit Trend -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Revenue & Profit Trend</h3>
                <canvas id="revenueProfitChart" class="h-80"></canvas>
            </div>

            <!-- Food Sales & Daily Losses -->
            <div class="grid grid-cols-1 gap-8">
                <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Daily Food Sales</h3>
                    <canvas id="foodSalesChart" class="h-64"></canvas>
                </div>
                <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Daily Collection Discrepancies</h3>
                    <canvas id="dailyLossesChart" class="h-64"></canvas>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">Total Loss: <span class="font-bold text-red-600">GH₵ {{ number_format(abs($accumulatedLosses), 2) }}</span></p>
                        <p class="text-sm text-gray-500">On the House: GH₵ {{ number_format($totalOnTheHouse, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Distribution -->
        <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Payment Method Breakdown</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <canvas id="paymentPieChart" class="h-80 mx-auto"></canvas>
                </div>
                <div class="space-y-4 flex flex-col justify-center">
                    @foreach ($paymentMethodDistribution as $method)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-4 h-4 rounded-full
    {{ match($method['method']) {
        'Cash' => 'bg-green-500',
        'Mobile Money' => 'bg-blue-500',
        'Hubtel' => 'bg-purple-500',
        default => 'bg-orange-500'
    } }}">
</div>
                                <span class="font-medium text-gray-800">{{ $method['method'] }}</span>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">GH₵ {{ number_format($method['amount'], 2) }}</p>
                                <p class="text-sm text-gray-600">{{ $method['percentage'] }}%</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Product Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Top Selling -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Top Selling Products</h3>
                <div class="space-y-4">
                    @forelse(array_slice($topSellingProducts, 0, 8) as $i => $p)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center font-bold text-indigo-600">
                                    {{ $i + 1 }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $p['product_name'] }}</p>
                                    <p class="text-sm text-gray-500">{{ $p['category'] }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">{{ number_format($p['units_sold']) }} units</p>
                                <p class="text-sm text-green-600">GH₵ {{ number_format($p['revenue'], 0) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-8">No sales data</p>
                    @endforelse
                </div>
            </div>

            <!-- Most Profitable -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Most Profitable Products</h3>
                <div class="space-y-4">
                    @forelse(array_slice($mostProfitableProducts, 0, 8) as $i => $p)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center font-bold text-green-600">
                                    {{ $i + 1 }}
                                </div>
                                <p class="font-medium text-gray-900">{{ $p['product_name'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-700">GH₵ {{ number_format($p['total_profit'], 2) }}</p>
                                <p class="text-sm text-gray-600">{{ number_format($p['units_sold']) }} units</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-8">No data</p>
                    @endforelse
                </div>
            </div>

            <!-- Highest Losses -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-red-600 mb-6">Highest Loss Products</h3>
                <div class="space-y-4">
                    @forelse(array_slice($highestLossProducts, 0, 8) as $i => $p)
                        <div class="flex items-center justify-between p-4 bg-red-50 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center font-bold text-red-600">
                                    {{ $i + 1 }}
                                </div>
                                <p class="font-medium text-gray-900">{{ $p['product_name'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-red-700">GH₵ {{ number_format($p['total_loss'], 2) }}</p>
                                <p class="text-sm text-gray-600">{{ $p['damaged_units'] }} dmg • {{ $p['credit_units'] }} credit</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-8">No losses recorded</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Inventory & Category Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Inventory Status -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Inventory Overview</h3>
                <div class="grid grid-cols-3 gap-6 text-center">
                    <div>
                        <p class="text-3xl font-bold text-indigo-600">GH₵ {{ number_format($totalInventoryValue, 0) }}</p>
                        <p class="text-sm text-gray-600 mt-2">Current Value</p>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-yellow-600">{{ $lowStockProducts }}</p>
                        <p class="text-sm text-gray-600 mt-2">Low Stock</p>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-red-600">{{ $outOfStockProducts }}</p>
                        <p class="text-sm text-gray-600 mt-2">Out of Stock</p>
                    </div>
                </div>
                <div class="mt-8 text-center">
                    <p class="text-lg font-semibold text-gray-800">Inventory Turnover</p>
                    <p class="text-4xl font-bold text-indigo-600 mt-2">{{ number_format($inventoryTurnoverRate, 2) }}x</p>
                </div>
            </div>

            <!-- Top Categories -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Top Categories</h3>
                <div class="space-y-4">
                    @forelse(array_slice($categoryPerformance, 0, 6) as $cat)
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="font-medium text-gray-800">{{ $cat['category_name'] }}</span>
                                <span class="text-sm font-bold">GH₵ {{ number_format($cat['revenue'], 0) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-3 rounded-full transition-all"
                                     style="width: {{ $topCategory && $topCategory['revenue'] > 0 ? ($cat['revenue'] / $topCategory['revenue']) * 100 : 0 }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>{{ number_format($cat['units_sold']) }} units</span>
                                <span>{{ number_format($cat['profit_margin'], 1) }}% margin</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 py-8">No category data</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('livewire:load', function () {
    // Revenue & Profit Trend
    new Chart(document.getElementById('revenueProfitChart'), {
        type: 'line',
        data: {
            labels: @json(array_column($dailySalesData, 'date')),
            datasets: [
                {
                    label: 'Revenue',
                    data: @json(array_column($dailySalesData, 'revenue')),
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Profit',
                    data: @json(array_column($dailyProfitData, 'profit')),
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: value => 'GH₵' + value.toLocaleString() } }
            }
        }
    });

    // Food Sales Chart
    new Chart(document.getElementById('foodSalesChart'), {
        type: 'bar',
        data: {
            labels: @json(array_column($dailyFoodSalesData, 'date')),
            datasets: [{
                label: 'Food Sales',
                data: @json(array_column($dailyFoodSalesData, 'amount')),
                backgroundColor: '#F59E0B'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Daily Losses Chart
    new Chart(document.getElementById('dailyLossesChart'), {
        type: 'bar',
        data: {
            labels: @json(array_column($dailyLossesData, 'date')),
            datasets: [{
                label: 'Collection Shortfall',
                data: @json(array_map(fn($d) => $d['loss'] < 0 ? abs($d['loss']) : 0, $dailyLossesData)),
                backgroundColor: '#EF4444'
            }, {
                label: 'On the House',
                data: @json(array_column($dailyLossesData, 'on_the_house')),
                backgroundColor: '#F97316'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Payment Methods Pie Chart
    new Chart(document.getElementById('paymentPieChart'), {
        type: 'doughnut',
        data: {
            labels: @json(array_column($paymentMethodDistribution, 'method')),
            datasets: [{
                data: @json(array_column($paymentMethodDistribution, 'amount')),
                backgroundColor: ['#10B981', '#3B82F6', '#9333EA', '#F97316']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endpush