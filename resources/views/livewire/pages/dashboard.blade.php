{{-- resources/views/livewire/pages/dashboard.blade.php --}}
<div class="space-y-6">
    @php
        $hour = now()->hour;
        if ($hour < 12) {
            $greeting = "Good Morning";
        } elseif ($hour < 18) {
            $greeting = "Good Afternoon";
        } else {
            $greeting = "Good Evening";
        }

            $f_name = explode(' ', Auth::user()->name)[0];

    @endphp 

    {{-- Header Section --}}
    <section class="w-full h-full flex-col gap-2 flex rounded-2xl">
        <header class="w-full flex md:items-center justify-between py-2 md:p-4 rounded-2xl">
            <div class="">
                <h1 class="hidden md:flex items-center gap-1 md:gap-3">
                    <span class="lg:text-3xl text-base">{{ $greeting }}</span>
                    <span class="text-[#0F51AE] text-sm lg:text-xl rounded-full bg-[#F2F8FF] px-2 py-1 font-semibold">{{ $f_name }}</span>
                </h1>
                <livewire:components.clock/>
            </div>
            
            {{-- Show alert if displaying previous day's data --}}
            @if (!$hasTodayData)
                <div class="flex items-center gap-4">
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm">Showing yesterday's data. Today's inventory not recorded yet.</span>
                    </div>
                    <button 
                        wire:click="goToInventory"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
                    >
                        Take Today's Inventory
                    </button>
                </div>
            @endif
        </header>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
            {{-- Today's Revenue --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">
                            {{ $hasTodayData ? "Today's Revenue" : "Yesterday's Revenue" }}
                        </p>
                        <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-2">GH₵ {{ $this->todayRevenue }}</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                       <svg class="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M17 6a8 8 0 1 0 0 12" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="12" y1="2" x2="12" y2="22" stroke-linecap="round"/>
                        </svg>

                    </div>
                </div>
            </div>

            {{-- Items Sold --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Items Sold</p>
                        <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-2">{{ $this->itemsSold }}</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>
                            <path d="M12 22V12"/>
                            <polyline points="3.29 7 12 12 20.71 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Low Stock Items --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Low Stock Items</p>
                        <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-2">{{ count($this->lowStockProducts) }}</p>
                    </div>
                    <div class="bg-red-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
                            <path d="M12 9v4"/>
                            <path d="M12 17h.01"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Active Products --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600">Active Products</p>
                        <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-2">{{ $this->activeProducts }}</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>
                            <path d="M12 22V12"/>
                            <polyline points="3.29 7 12 12 20.71 7"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
            {{-- Recent Sales --}}
            <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="px-4 md:px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Sales</h3>
                </div>
                <div class="p-4 md:p-6">
                    @if(count($this->recentSales) > 0)
                        <div class="space-y-4">
                            @foreach($this->recentSales as $sale)
                                <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $sale['product'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $sale['category'] }} • Qty: {{ $sale['quantity'] }}</p>
                                    </div>
                                    <div class="text-right ml-4">
                                        <p class="text-sm font-semibold text-gray-900">GH₵ {{ $sale['revenue'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $sale['time'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v6a2 2 0 002 2h6a2 2 0 002-2v-7m0 0V9a2 2 0 00-2-2h-6a2 2 0 00-2 2v4z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No sales data</h3>
                            <p class="mt-1 text-sm text-gray-500">No sales recorded for {{ $hasTodayData ? 'today' : 'yesterday' }}.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Low Stock Alert --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="px-4 md:px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
                            <path d="M12 9v4"/>
                            <path d="M12 17h.01"/>
                        </svg>
                        Low Stock Alert
                    </h3>
                </div>
                <div class="p-4 md:p-6">
                    @if(count($this->lowStockItems) > 0)
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            @foreach($this->lowStockItems as $item)
                                <div class="border border-red-200 rounded-lg p-3 bg-red-50">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="text-sm font-medium text-gray-900 truncate pr-2">{{ $item['name'] }}</h4>
                                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded whitespace-nowrap">
                                            {{ $item['category'] }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-600 mb-2">
                                        <span>Current: {{ $item['current'] }}</span>
                                        <span>Min: {{ $item['minimum'] }}</span>
                                    </div>
                                    <div class="w-full bg-red-200 rounded-full h-2">
                                        <div 
                                            class="bg-red-600 h-2 rounded-full transition-all duration-300"
                                            style="width: {{ $item['percentage'] }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">All stocks healthy</h3>
                            <p class="mt-1 text-sm text-gray-500">No products are currently running low on stock.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>