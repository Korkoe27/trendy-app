<div class="space-y-6">
    <!-- Header with Timeframe Selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Sales Analytics</h2>
                <p class="text-sm text-gray-600 mt-1">Trendy Analytics</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('timeframe', '7d')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $timeframe === '7d' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                    7 Days
                </button>
                <button wire:click="$set('timeframe', '30d')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $timeframe === '30d' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                    30 Days
                </button>
                <button wire:click="$set('timeframe', '90d')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $timeframe === '90d' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                    90 Days
                </button>
                <button wire:click="$set('timeframe', 'month')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $timeframe === 'month' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                    This Month
                </button>
            </div>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Revenue -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-lg bg-green-100">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                        </path>
                    </svg>
                </div>
                <span
                    class="text-xs font-semibold px-2 py-1 rounded-full {{ $revenueGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $revenueGrowth >= 0 ? '+' : '' }}{{ $revenueGrowth }}%
                </span>
            </div>
            <p class="text-sm font-medium text-gray-600 mb-1">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900">GH₵ {{ number_format($totalRevenue, 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">Avg: GH₵ {{ number_format($averageDailyRevenue, 2) }}/day</p>
        </div>

        <!-- Total Profit -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-lg bg-blue-100">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <span
                    class="text-xs font-semibold px-2 py-1 rounded-full {{ $revenueGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $revenueGrowth >= 0 ? '+' : '' }}{{ $revenueGrowth }}%
                </span>
            </div>
            <p class="text-sm font-medium text-gray-600 mb-1">Total Profit</p>
            <p class="text-2xl font-bold text-gray-900">GH₵ {{ number_format($totalProfit, 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">Margin: {{ number_format($grossProfitMargin, 1) }}%</p>
        </div>

        <!-- Items Sold -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-lg bg-purple-100">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <span
                    class="text-xs font-semibold px-2 py-1 rounded-full {{ $itemsSoldGrowth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $itemsSoldGrowth >= 0 ? '+' : '' }}{{ $itemsSoldGrowth }}%
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-2">Total units sold</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totalItemsSold) }}</p>
            <p class="text-xs text-gray-500 mt-2">GH₵ {{ number_format($totalExpenses) }}</p>
        </div>

        <!-- Total Expenses -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-lg bg-orange-100">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                        </path>
                    </svg>
                </div>
                <span
                    class="text-xs font-semibold px-2 py-1 rounded-full {{ $expensesGrowth >= 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                    {{ $expensesGrowth >= 0 ? '+' : '' }}{{ $expensesGrowth }}%
                </span>
            </div>
            <p class="text-sm font-medium text-gray-600 mb-1">Total Expenses</p>
            <p class="text-2xl font-bold text-gray-900">GH₵ {{ number_format($totalExpenses, 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">Stock purchases</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Daily Sales Trend -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Sales Trend</h3>
            <div class="h-64">
                @if (count($dailySalesData) > 0)
                    <div class="space-y-3">
                        @php
                            $maxRevenue = max(array_column($dailySalesData, 'revenue'));
                        @endphp
                        @foreach (array_slice($dailySalesData, -7) as $day)
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="text-gray-600 font-medium">{{ $day['date'] }}</span>
                                    <span class="text-gray-900 font-semibold">GH₵
                                        {{ number_format($day['revenue'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full transition-all"
                                        style="width: {{ $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue) * 100 : 0 }}%">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center justify-center h-full text-gray-500">
                        No sales data available
                    </div>
                @endif
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Methods</h3>
            <div class="space-y-4">
                @foreach ($paymentMethodDistribution as $payment)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                @if ($payment['method'] === 'Cash')
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                @elseif($payment['method'] === 'Mobile Money')
                                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                @elseif($payment['method'] === 'Hubtel')
                                    <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                                @else
                                    <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                @endif
                                <span class="text-sm font-medium text-gray-700">{{ $payment['method'] }}</span>
                            </div>
                            <span class="text-sm text-gray-600">
                                GH₵ {{ number_format($payment['amount'], 2) }} ({{ $payment['percentage'] }}%)
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            @if ($payment['method'] === 'Cash')
                                <div class="bg-green-500 h-2 rounded-full"
                                    style="width: {{ $payment['percentage'] }}%"></div>
                            @elseif($payment['method'] === 'Mobile Money')
                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $payment['percentage'] }}%">
                                </div>
                            @elseif($payment['method'] === 'Hubtel')
                                <div class="bg-purple-500 h-2 rounded-full"
                                    style="width: {{ $payment['percentage'] }}%"></div>
                            @else
                                <div class="bg-orange-500 h-2 rounded-full"
                                    style="width: {{ $payment['percentage'] }}%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Products and Categories -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Selling Products -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Selling Products</h3>
            <div class="space-y-3">
                @forelse(array_slice($topSellingProducts, 0, 5) as $index => $product)
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="text-sm font-bold text-blue-600">{{ $index + 1 }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $product['product_name'] }}</p>
                            <p class="text-xs text-gray-500">{{ number_format($product['units_sold']) }} units sold
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">GH₵
                                {{ number_format($product['revenue'], 2) }}</p>
                            <p class="text-xs text-green-600">+GH₵ {{ number_format($product['profit'], 2) }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-500 py-8">
                        No product data available
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Category Performance -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Category Performance</h3>
            <div class="space-y-4">
                @forelse(array_slice($categoryPerformance, 0, 5) as $category)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">{{ $category['category_name'] }}</span>
                            <span class="text-sm text-gray-600">GH₵
                                {{ number_format($category['revenue'], 2) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full"
                                    style="width: {{ $category['profit_margin'] > 100 ? 100 : $category['profit_margin'] }}%">
                                </div>
                            </div>
                            <span
                                class="text-xs text-gray-600 w-12 text-right">{{ number_format($category['profit_margin'], 1) }}%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ number_format($category['units_sold']) }} units • GH₵
                            {{ number_format($category['profit'], 2) }} profit</p>
                    </div>
                @empty
                    <div class="text-center text-gray-500 py-8">
                        No category data available
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Inventory and Loss Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Inventory Value -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 rounded-lg bg-indigo-100">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-600">Inventory Value</p>
            </div>
            <p class="text-xl font-bold text-gray-900">GH₵ {{ number_format($totalInventoryValue, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Turnover: {{ number_format($inventoryTurnoverRate, 2) }}x</p>
        </div>

        <!-- Low Stock Alert -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 rounded-lg bg-yellow-100">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-600">Low Stock</p>
            </div>
            <p class="text-xl font-bold text-gray-900">{{ $lowStockProducts }}</p>
            <p class="text-xs text-gray-500 mt-1">Products need restock</p>
        </div>

        <!-- Out of Stock -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 rounded-lg bg-red-100">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-600">Out of Stock</p>
            </div>
            <p class="text-xl font-bold text-gray-900">{{ $outOfStockProducts }}</p>
            <p class="text-xs text-gray-500 mt-1">Products unavailable</p>
        </div>

        <!-- Damaged/Loss -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 rounded-lg bg-rose-100">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-600">Damaged/Loss</p>
            </div>
            <p class="text-xl font-bold text-gray-900">{{ number_format($totalDamagedUnits) }}</p>
            <p class="text-xs text-gray-500 mt-1">GH₵ {{ number_format($totalDamagedValue, 2) }} lost</p>
        </div>
    </div>

    <!-- Highest Loss Products -->
    @if (count($highestLossProducts) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Products with Highest Losses</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-xs font-medium text-gray-600 uppercase">Product</th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-gray-600 uppercase">Damaged Units
                            </th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-gray-600 uppercase">Loss Value
                            </th>
                            <th class="text-right py-3 px-4 text-xs font-medium text-gray-600 uppercase">Credit Units
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach (array_slice($highestLossProducts, 0, 5) as $product)
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 text-sm text-gray-900">{{ $product['product_name'] }}</td>
                                <td class="py-3 px-4 text-sm text-right text-red-600">
                                    {{ number_format($product['damaged_units']) }}</td>
                                <td class="py-3 px-4 text-sm text-right text-red-600 font-medium">GH₵
                                    {{ number_format($product['loss_value'], 2) }}</td>
                                <td class="py-3 px-4 text-sm text-right text-orange-600">
                                    {{ number_format($product['credit_units']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
