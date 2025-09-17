<div class="space-y-6">
    <!-- Header with time filter -->
    <section class="w-full h-full flex-col gap-2 flex rounded-2xl">
         <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Sales Analytics</h2>
                <p class="text-sm text-gray-600 mt-1">Track your pub's performance and trends</p>
            </div>
            <div class="flex space-x-2">
                @foreach(['7d' => 'Last 7 Days', '30d' => 'Last 30 Days', '90d' => 'Last 90 Days'] as $period => $label)
                    <button 
                        wire:click="$set('timeframe', '{{ $period }}')"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $timeframe === $period ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Revenue -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">GH₵ {{ number_format($totalRevenue, 2) }}</p>
                </div>
                <div class="p-3 rounded-full bg-green-100">
                    {{-- <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg> --}}

                        <svg class="w-6 h-6 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M17 6a8 8 0 1 0 0 12" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="12" y1="2" x2="12" y2="22" stroke-linecap="round"/>
                        </svg>
                </div>
            </div>
            <div class="flex items-center mt-4">
                <svg class="w-4 h-4 {{ $revenueGrowth >= 0 ? 'text-green-500' : 'text-red-500' }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($revenueGrowth >= 0)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    @endif
                </svg>
                <span class="text-sm font-medium {{ $revenueGrowth >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $revenueGrowth >= 0 ? '+' : '' }}{{ $revenueGrowth }}%
                </span>
                <span class="text-sm text-gray-500 ml-1">vs last period</span>
            </div>
        </div>

        <!-- Items Sold -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Items Sold</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($totalItemsSold) }}</p>
                </div>
                <div class="p-3 rounded-full bg-blue-100">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <div class="flex items-center mt-4">
                <svg class="w-4 h-4 {{ $itemsGrowth >= 0 ? 'text-green-500' : 'text-red-500' }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($itemsGrowth >= 0)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    @endif
                </svg>
                <span class="text-sm font-medium {{ $itemsGrowth >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $itemsGrowth >= 0 ? '+' : '' }}{{ $itemsGrowth }}%
                </span>
                <span class="text-sm text-gray-500 ml-1">vs last period</span>
            </div>
        </div>

        <!-- Average Order -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Average Order</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">GH₵ {{ number_format($averageOrder, 2) }}</p>
                </div>
                <div class="p-3 rounded-full bg-purple-100">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="flex items-center mt-4">
                <svg class="w-4 h-4 {{ $orderGrowth >= 0 ? 'text-green-500' : 'text-red-500' }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($orderGrowth >= 0)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    @endif
                </svg>
                <span class="text-sm font-medium {{ $orderGrowth >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $orderGrowth >= 0 ? '+' : '' }}{{ $orderGrowth }}%
                </span>
                <span class="text-sm text-gray-500 ml-1">vs last period</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Sales Chart -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Daily Sales Trend</h3>
            <div class="space-y-4">
                @foreach($salesData as $data)
                    <div class="flex items-center space-x-4">
                        <div class="w-12 text-sm text-gray-600">{{ $data['day'] }}</div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-gray-600">{{ $data['items'] }} items</span>
                                <span class="text-sm font-medium text-gray-900">₵{{ number_format($data['sales'], 2) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                    style="width: {{ $maxSales > 0 ? ($data['sales'] / $maxSales) * 100 : 0 }}%"
                                ></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Top Products -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Top Selling Products</h3>
            <div class="space-y-4">
                @forelse($topProducts as $product)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $product['name'] }}</p>
                            <p class="text-xs text-gray-600">{{ $product['sales'] }} units sold</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">₵{{ number_format($product['revenue'], 2) }}</p>
                            <p class="text-xs {{ $product['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $product['growth'] >= 0 ? '+' : '' }}{{ $product['growth'] }}%
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-500 py-4">
                        <p>No product sales data available</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Sales by Category</h3>
        <div class="space-y-6">
            @forelse($categoryData as $category)
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-900">{{ $category['name'] }}</span>
                        <span class="text-sm text-gray-600">₵{{ number_format($category['sales'], 2) }} ({{ $category['percentage'] }}%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div 
                            class="{{ $category['color'] }} h-3 rounded-full transition-all duration-300"
                            style="width: {{ $category['percentage'] }}%"
                        ></div>
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-500 py-4">
                    <p>No category data available</p>
                </div>
            @endforelse
        </div>
    </div>
    </section>

</div>