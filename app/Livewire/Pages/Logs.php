<?php

    namespace App\Livewire\Pages;

    use App\Models\{ActivityLogs,User};
    use Carbon\Carbon;
    use Livewire\Component;
    use Livewire\WithPagination;

    class Logs extends Component
    {
        use WithPagination;

        public $searchTerm = '';
        public $selectedActionType = 'all';
        public $selectedEntityType = 'all';
        public $selectedUser = 'all';
        public $dateRange = 'today';
        public $showDetailsModal = false;
        public $selectedLog = null;
        public $perPage = 15;

        protected $queryString = [
            'searchTerm' => ['except' => ''],
            'selectedActionType' => ['except' => 'all'],
            'selectedEntityType' => ['except' => 'all'],
            'selectedUser' => ['except' => 'all'],
            'dateRange' => ['except' => 'today'],
        ];

        public function mount()
        {
            $this->resetPage();
        }

        public function updatingSearchTerm()
        {
            $this->resetPage();
        }

        public function updatingSelectedActionType()
        {
            $this->resetPage();
        }

        public function updatingSelectedEntityType()
        {
            $this->resetPage();
        }

        public function updatingSelectedUser()
        {
            $this->resetPage();
        }

        public function updatingDateRange()
        {
            $this->resetPage();
        }

        public function getActionTypesProperty()
        {
            $types = ActivityLogs::distinct()->pluck('action_type')->sort()->toArray();
            return array_merge(['all'], $types);
        }

        public function getEntityTypesProperty()
        {
            $types = ActivityLogs::distinct()->pluck('entity_type')->sort()->toArray();
            return array_merge(['all'], $types);
        }

        public function getUsersProperty()
        {
            $users = User::select('id', 'name')->orderBy('name')->get();
            return collect([['id' => 'all', 'name' => 'All Users']])->merge(
                $users->map(fn($user) => ['id' => $user->id, 'name' => $user->name])
            );
        }


        public function formatUserFriendlyDescription($log)
{
    $descriptions = [
        'user_login' => "{$log->user->name} logged into the system",
        'user_logout' => "{$log->user->name} logged out of the system",
        'user_create' => "New user account created: {$this->getEntityName($log)}",
        'user_update' => "User account updated: {$this->getEntityName($log)}",
        'user_delete' => "User account removed: {$this->getEntityName($log)}",
        'product_create' => "New product added: {$this->getEntityName($log)}",
        'product_update' => "Product details updated: {$this->getEntityName($log)}",
        'product_delete' => "Product removed: {$this->getEntityName($log)}",
        'stock_update' => "Stock levels adjusted for {$this->getEntityName($log)}",
        'stock_taking' => "Stock count performed for {$this->getEntityName($log)}",
        'daily_sales_record' => "Daily sales recorded by {$log->user->name}",
    ];

    return $descriptions[$log->action_type] ?? $log->description;
}

private function getEntityName($log)
{
    // Extract entity name from metadata if available
    if ($log->metadata) {
        $metadata = is_string($log->metadata) ? json_decode($log->metadata, true) : $log->metadata;
        return $metadata['name'] ?? $metadata['title'] ?? "ID: {$log->entity_id}";
    }
    return $log->entity_id ? "ID: {$log->entity_id}" : 'system';
}
        public function getFilteredLogsProperty()
        {
            $query = ActivityLogs::with('user')
                ->when($this->searchTerm, function($q) {
                    $q->where(function($query) {
                        $query->where('description', 'like', '%' . $this->searchTerm . '%')
                            ->orWhere('action_type', 'like', '%' . $this->searchTerm . '%')
                            ->orWhereHas('user', function($userQuery) {
                                $userQuery->where('name', 'like', '%' . $this->searchTerm . '%');
                            });
                    });
                })
                ->when($this->selectedActionType !== 'all', function($q) {
                    $q->where('action_type', $this->selectedActionType);
                })
                ->when($this->selectedEntityType !== 'all', function($q) {
                    $q->where('entity_type', $this->selectedEntityType);
                })
                ->when($this->selectedUser !== 'all', function($q) {
                    $q->where('user_id', $this->selectedUser);
                })
                ->when($this->dateRange !== 'all', function($q) {
                    switch($this->dateRange) {
                        case 'today':
                            $q->whereDate('created_at', Carbon::today());
                            break;
                        case 'week':
                            $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case 'month':
                            $q->whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year);
                            break;
                    }
                })
                ->orderBy('created_at', 'desc');

            return $query->paginate($this->perPage);
        }

        public function getTotalActivitiesProperty()
        {
            return $this->filteredLogs->total();
        }

        public function getUserActionsProperty()
        {
            return ActivityLogs::where('action_type', 'like', '%user%')
                ->when($this->dateRange !== 'all', function($q) {
                    switch($this->dateRange) {
                        case 'today':
                            $q->whereDate('created_at', Carbon::today());
                            break;
                        case 'week':
                            $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case 'month':
                            $q->whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year);
                            break;
                    }
                })
                ->count();
        }

        public function getStockActivitiesProperty()
        {
            return ActivityLogs::where('action_type', 'like', '%stock%')
                ->when($this->dateRange !== 'all', function($q) {
                    switch($this->dateRange) {
                        case 'today':
                            $q->whereDate('created_at', Carbon::today());
                            break;
                        case 'week':
                            $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case 'month':
                            $q->whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year);
                            break;
                    }
                })
                ->count();
        }

        public function getSalesRecordsProperty()
        {
            return ActivityLogs::where('action_type', 'like', '%sales%')
                ->when($this->dateRange !== 'all', function($q) {
                    switch($this->dateRange) {
                        case 'today':
                            $q->whereDate('created_at', Carbon::today());
                            break;
                        case 'week':
                            $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                            break;
                        case 'month':
                            $q->whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year);
                            break;
                    }
                })
                ->count();
        }

        public function openDetailsModal($logId)
        {
            $this->selectedLog = ActivityLogs::with('user')->find($logId);
            $this->showDetailsModal = true;
        }

        public function closeDetailsModal()
        {
            $this->showDetailsModal = false;
            $this->selectedLog = null;
        }

        public function getActionIcon($actionType)
        {
            $icons = [
                'user_login' => 'user',
                'user_logout' => 'user',
                'stock_update' => 'package',
                'stock_taking' => 'package',
                'daily_sales_record' => 'shopping-cart',
                'product_create' => 'database',
                'product_update' => 'database',
                'product_delete' => 'database',
                'user_create' => 'users',
                'user_update' => 'users',
                'user_delete' => 'users',
            ];

            return $icons[$actionType] ?? 'activity';
        }

        public function getActionColor($actionType)
        {
            $colors = [
                'user_login' => 'text-green-600 bg-green-100',
                'user_logout' => 'text-gray-600 bg-gray-100',
                'stock_update' => 'text-blue-600 bg-blue-100',
                'stock_taking' => 'text-blue-600 bg-blue-100',
                'daily_sales_record' => 'text-purple-600 bg-purple-100',
                'product_create' => 'text-green-600 bg-green-100',
                'user_create' => 'text-green-600 bg-green-100',
                'product_update' => 'text-yellow-600 bg-yellow-100',
                'user_update' => 'text-yellow-600 bg-yellow-100',
                'product_delete' => 'text-red-600 bg-red-100',
                'user_delete' => 'text-red-600 bg-red-100',
            ];

            return $colors[$actionType] ?? 'text-gray-600 bg-gray-100';
        }

        public function formatActionType($actionType)
        {
            return collect(explode('_', $actionType))
                ->map(fn($word) => ucfirst($word))
                ->join(' ');
        }

        public function render()
        {
            return view('livewire.pages.logs', [
                'logs' => $this->filteredLogs,
            ]);
        }
    }