{{-- resources/views/livewire/pages/activity-logs.blade.php --}}
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Activity Logs</h2>
                <p class="text-sm text-gray-600 mt-1">Monitor all system activities and database changes</p>
            </div>
            <div class="flex space-x-3">
                <select
                    wire:model.live="dateRange"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                >
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="all">All Time</option>
                </select>
                
                {{-- Export Button --}}
                
            @haspermission('create','logs')
                <button
                    wire:click="exportLogs"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 text-sm font-medium flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Export</span>
                </button>
            @endhaspermission
            </div>
        </div>

        {{-- Search and Filters --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="text"
                    placeholder="Search activities..."
                    wire:model.live.debounce.300ms="searchTerm"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                />
            </div>
            <select
                wire:model.live="selectedActionType"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
            >
                @foreach($this->actionTypes as $type)
                    <option value="{{ $type }}">
                        {{ $type === 'all' ? 'All Actions' : $this->formatActionType($type) }}
                    </option>
                @endforeach
            </select>
            <select
                wire:model.live="selectedEntityType"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
            >
                @foreach($this->entityTypes as $type)
                    <option value="{{ $type }}">
                        {{ $type === 'all' ? 'All Entities' : ucfirst(str_replace('_', ' ', $type)) }}
                    </option>
                @endforeach
            </select>
            <select
                wire:model.live="selectedUser"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
            >
                @foreach($this->users as $user)
                    <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Quick Filters --}}
        {{-- <div class="flex flex-wrap gap-2 mt-4">
            <button
                wire:click="setQuickFilter('today')"
                class="px-3 py-1 text-xs font-medium rounded-full {{ $dateRange === 'today' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
            >
                Today's Activity
            </button>
            <button
                wire:click="setQuickFilter('errors')"
                class="px-3 py-1 text-xs font-medium rounded-full {{ $selectedActionType === 'error' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
            >
                System Errors
            </button>
            <button
                wire:click="setQuickFilter('user_actions')"
                class="px-3 py-1 text-xs font-medium rounded-full {{ str_contains($selectedActionType, 'user') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
            >
                User Actions
            </button>
            <button
                wire:click="setQuickFilter('stock_changes')"
                class="px-3 py-1 text-xs font-medium rounded-full {{ str_contains($selectedActionType, 'stock') ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
            >
                Stock Changes
            </button>
        </div> --}}
    </div>

    {{-- Activity Logs Table --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        {{-- Table Controls --}}
        <div class="px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-700">
                    Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} results
                </span>
            </div>
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-700">Per page:</label>
                <select
                    wire:model.live="perPage"
                    class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                        <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                        <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getActionColor($log->action_type) }}">
                                        @php $icon = $this->getActionIcon($log->action_type); @endphp
                                        @if($icon === 'user')
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                        @elseif($icon === 'package')
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        @elseif($icon === 'shopping-cart')
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m0 0L12 21m-2.5-3H17"/>
                                            </svg>
                                        @elseif($icon === 'database')
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 6 2 8 2s8 0 8-2V7M4 7c0 2 6 2 8 2s8 0 8-2M4 7c0-2 6-2 8-2s8 0 8 2"/>
                                            </svg>
                                        @elseif($icon === 'users')
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
                                            </svg>
                                        @elseif($icon === 'exclamation-triangle')
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.098 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                            </svg>
                                        @else
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                        @endif
                                        <span>{{ $this->formatActionType($log->action_type) }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ $log->user->name ?? 'System' }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $log->user->role ?? 'Automated' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 md:px-6 py-4">
                                <div class="text-sm text-gray-900 max-w-xs">
                                    <div class="truncate" title="{{ $log->description }}">{{ $log->description }}</div>
                                </div>
                            </td>
                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $log->entity_type)) }}</div>
                                    @if($log->entity_id)
                                        <div class="text-xs text-gray-500">ID: {{ $log->entity_id }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center text-sm text-gray-900">
                                    <svg class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <div class="min-w-0">
                                        <div class="truncate">{{ $log->created_at->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $log->created_at->format('h:i:s A') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button
                                    wire:click="openDetailsModal({{ $log->id }})"
                                    class="text-blue-600 hover:text-blue-900 flex items-center space-x-1 transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span class="hidden sm:inline">View More</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No activity logs found</h3>
                                    <p class="text-gray-500">No activities match your current filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Summary Cards --}}
    {{-- <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Activities</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->totalActivities }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $dateRange === 'today' ? 'Today' : ucfirst($dateRange) }}</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">User Actions</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->userActions }}</p>
                    <p class="text-xs text-gray-500 mt-1">Logins, Updates, etc.</p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Stock Activities</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stockActivities }}</p>
                    <p class="text-xs text-gray-500 mt-1">Updates, Stock Taking</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-6 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Sales Records</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->salesRecords }}</p>
                    <p class="text-xs text-gray-500 mt-1">Daily Sales, Transactions</p>
                </div>
                <div class="p-3 bg-orange-100 rounded-full">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m0 0L12 21m-2.5-3H17"/>
                    </svg>
                </div>
            </div>
        </div>
    </div> --}}

    {{-- Details Modal --}}
    @if($showDetailsModal && $selectedLog)
        <div class="fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center z-50 p-4" wire:click.self="closeDetailsModal">
            <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Activity Log Details</h3>
                    <button 
                        wire:click="closeDetailsModal"
                        class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition-colors duration-150"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/> 
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    {{-- Basic Information --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Action Type</label>
                                <div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $this->getActionColor($selectedLog->action_type) }}">
                                        {{ $this->formatActionType($selectedLog->action_type) }}
                                    </span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $selectedLog->user->name ?? 'System' }}</div>
                                        <div class="text-xs text-gray-500">{{ $selectedLog->user->email ?? 'system@automated' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Entity Type</label>
                                <div class="text-sm text-gray-900">
                                    <span class="px-2 py-1 bg-gray-100 rounded-md">
                                        {{ ucfirst(str_replace('_', ' ', $selectedLog->entity_type)) }}
                                    </span>
                                </div>
                            </div>

                            @if($selectedLog->entity_id)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Entity ID</label>
                                    <div class="text-sm text-gray-900 font-mono bg-gray-50 px-3 py-2 rounded-md">
                                        {{ $selectedLog->entity_id }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Timestamp</label>
                                <div class="text-sm text-gray-900">
                                    <div class="flex items-center mb-1">
                                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                                                                <span>{{ $selectedLog->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 ml-6">
                                        {{ $selectedLog->created_at->format('h:i:s A') }}
                                    </div>
                                </div>
                            </div>

                            @if($selectedLog->description)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                    <div class="text-sm text-gray-900 bg-gray-50 border border-gray-200 rounded-md p-3">
                                        {{ $selectedLog->description }}
                                    </div>
                                </div>
                            @endif

                            @if($selectedLog->metadata)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Metadata</label>
                                    <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-sm text-gray-900 overflow-x-auto">
                                        <pre class="whitespace-pre-wrap break-words">{{ json_encode(json_decode($selectedLog->metadata, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Optional JSON Viewer Enhancements (future) --}}
                    {{-- You could implement a toggle here for raw/pretty views or collapsible metadata entries --}}
                </div>
            </div>
        </div>
    @endif
</div>
