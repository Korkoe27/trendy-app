<div class="space-y-6">
    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Header with Tabs -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">User Management</h2>
                <p class="text-base text-gray-600 mt-1">Manage system users and their permissions</p>
            </div>

            @if ($activeTab === 'users')
                @haspermission('create', 'users')
                    <div class="flex space-x-3">
                        <button wire:click="showAddUserModal"
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-800 transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                                </path>
                            </svg>
                            <span>Add User</span>
                        </button>
                    </div>
                @endhaspermission
            @else
                @haspermission('create', 'users')
                    <div class="flex space-x-3">
                        <button wire:click="showAddRoleModal"
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-800 transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                                </path>
                            </svg>
                            <span>Add Role</span>
                        </button>
                    </div>
                @endhaspermission
            @endif
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button wire:click="switchTab('users')"
                    class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'users' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Users
                </button>
                @haspermission('modify', 'users')
                    <button wire:click="switchTab('roles')"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'roles' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Roles & Permissions
                    </button>
                @endhaspermission
            </nav>
        </div>

        <!-- Search and Filter -->
        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text"
                    placeholder="{{ $activeTab === 'users' ? 'Search users or roles...' : 'Search roles...' }}"
                    wire:model.live="searchTerm"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            @if ($activeTab === 'users')
                <select wire:model.live="selectedRole"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="all">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    <!-- Users Table -->
    @if ($activeTab === 'users')
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Created At
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 mr-3">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                        <div>
                                            <div class="text-base font-medium uppercase text-gray-900">
                                                {{ $user->name }}
                                            </div>
                                            <div class="text-base uppercase text-gray-500">
                                                {{ $user->username }} @if ($user->email)
                                                    • {{ $user->email }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 text-base uppercase text-gray-900 py-4 whitespace-nowrap">
                                    {{ $user->role->name }}
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap">
                                    <button wire:click="toggleUserStatus({{ $user->id }})"
                                        class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $user->is_active ? 'text-green-600 bg-green-100 hover:bg-green-200' : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }} transition-colors">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="px-6 text-base text-gray-900 py-4 whitespace-nowrap">
                                    {{ $user->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                    <div class="flex space-x-2">
                                        <button wire:click="showViewUserModalMethod({{ $user->id }})"
                                            class="text-blue-600 hover:text-blue-800" title="View User">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        @haspermission('modify', 'users')
                                            <button wire:click="showEditUserModal({{ $user->id }})"
                                                class="text-green-600 hover:text-green-800" title="Edit User">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 4h2m-6.586 9.414l8.586-8.586a2 2 0 112.828 2.828l-8.586 8.586H7v-2.828zM5 19h14" />
                                                </svg>
                                            </button>
                                            <button wire:click="resetUserPassword({{ $user->id }})"
                                                class="text-orange-600 hover:text-orange-800" title="Reset Password">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </button>
                                        @endhaspermission

                                        @haspermission('delete', 'users')
                                            <button wire:click="confirmDeleteUser({{ $user->id }})"
                                                class="text-red-600 hover:text-red-900" title="Delete User">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endhaspermission
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"
                                    class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
                                    No users found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $users->links() }}
            </div>
        </div>
    @endif

    <!-- Roles Table -->
    @if ($activeTab === 'roles')
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Role Name
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Description
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Users Count
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Created At
                            </th>
                            <th
                                class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($roles as $role)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <div class="text-base font-medium uppercase text-gray-900">
                                            {{ $role->name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-base text-gray-600">
                                        {{ $role->description ?? 'No description' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}
                                    </span>
                                </td>
                                <td class="px-6 text-base text-gray-900 py-4 whitespace-nowrap">
                                    {{ $role->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-base font-medium">
                                    <div class="flex space-x-2">
                                        <button wire:click="showManagePermissionsModal({{ $role->id }})"
                                            class="text-purple-600 hover:text-purple-800" title="Manage Permissions">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </button>

                                        @haspermission('modify', 'roles')
                                            <button wire:click="showEditRoleModal({{ $role->id }})"
                                                class="text-green-600 hover:text-green-800" title="Edit Role">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 4h2m-6.586 9.414l8.586-8.586a2 2 0 112.828 2.828l-8.586 8.586H7v-2.828zM5 19h14" />
                                                </svg>
                                            </button>
                                        @endhaspermission

                                        @haspermission('delete', 'roles')
                                            <button wire:click="confirmDeleteRole({{ $role->id }})"
                                                class="text-red-600 hover:text-red-900" title="Delete Role">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endhaspermission
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"
                                    class="px-6 py-4 whitespace-nowrap text-base text-gray-500 text-center">
                                    No roles found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $roles->links() }}
            </div>
        </div>
    @endif

    <!-- Add/Edit User Modal -->
    @if ($showUserModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        {{ $isEditMode ? 'Edit User' : 'Add New User' }}
                    </h3>
                    <button wire:click="closeUserModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveUser">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="name"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter full name" required />
                            @error('name')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Username -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="username"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter username" required />
                            @error('username')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" wire:model="email"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter email address" />
                            @error('email')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Role <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="role_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required>
                                <option value="">Select a role</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            @error('role_id')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Password @if (!$isEditMode)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <input type="password" wire:model="password"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="{{ $isEditMode ? 'Leave blank to keep current password' : 'Enter password' }}"
                                {{ $isEditMode ? '' : 'required' }} />
                            @error('password')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password Confirmation -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm Password @if (!$isEditMode)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <input type="password" wire:model="password_confirmation"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Confirm password" {{ $isEditMode ? '' : 'required' }} />
                        </div>

                        <!-- Status -->
                        <div class="flex items-center">
                            <input type="checkbox" wire:model="is_active" id="is_active"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                            <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                                Active User
                            </label>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200 mt-6">
                        <button type="button" wire:click="closeUserModal"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>{{ $isEditMode ? 'Update User' : 'Create User' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- View User Modal -->
    @if ($showViewUserModal && $viewUser)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">User Details</h3>
                    <button wire:click="closeViewUserModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Full Name</label>
                            <p class="mt-1 text-base text-gray-900 uppercase">{{ $viewUser->name }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Username</label>
                            <p class="mt-1 text-base text-gray-900 uppercase">{{ $viewUser->username }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Email</label>
                            <p class="mt-1 text-base text-gray-900">{{ $viewUser->email ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Role</label>
                            <p class="mt-1 text-base text-gray-900 uppercase">{{ $viewUser->role->name }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <p class="mt-1">
                                <span
                                    class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $viewUser->is_active ? 'text-green-600 bg-green-100' : 'text-gray-600 bg-gray-100' }}">
                                    {{ $viewUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Created At</label>
                            <p class="mt-1 text-base text-gray-900">
                                {{ $viewUser->created_at->format('M j, Y h:i A') }}</p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    @if ($viewUser->user_logs && $viewUser->user_logs->count() > 0)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Recent Activity</h4>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                <!-- Recent Activity Section (Continuation of View User Modal) -->
                                @foreach ($viewUser->user_logs as $log)
                                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-blue-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900">{{ $log->description }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $log->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <p class="text-sm text-gray-500 text-center">No recent activity</p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-6 border-t border-gray-200 mt-6">
                    <button wire:click="closeViewUserModal"
                        class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete User Confirmation Modal -->
    @if ($showDeleteUserModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Delete User</h3>
                <p class="text-sm text-gray-600 text-center mb-6">
                    Are you sure you want to delete this user? This action cannot be undone.
                </p>

                <div class="flex space-x-3">
                    <button wire:click="closeDeleteUserModal"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button wire:click="deleteUser"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Add/Edit Role Modal -->
    @if ($showRoleModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        {{ $isEditRoleMode ? 'Edit Role' : 'Add New Role' }}
                    </h3>
                    <button wire:click="closeRoleModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveRole">
                    <div class="space-y-4">
                        <!-- Role Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Role Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="roleName"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="Enter role name" required />
                            @error('roleName')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Role Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Description
                            </label>
                            <textarea wire:model="roleDescription" rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="Enter role description (optional)"></textarea>
                            @error('roleDescription')
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200 mt-6">
                        <button type="button" wire:click="closeRoleModal"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>{{ $isEditRoleMode ? 'Update Role' : 'Create Role' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Role Confirmation Modal -->
    @if ($showDeleteRoleModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Delete Role</h3>
                <p class="text-sm text-gray-600 text-center mb-6">
                    Are you sure you want to delete this role? This will also remove all associated permissions.
                </p>

                <div class="flex space-x-3">
                    <button wire:click="closeDeleteRoleModal"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button wire:click="deleteRole"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Manage Permissions Modal -->
    @if ($showPermissionsModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Manage Permissions</h3>
                        <p class="text-sm text-gray-600 mt-1">Role: <span
                                class="font-medium uppercase">{{ $selectedRoleName }}</span></p>
                    </div>
                    <button wire:click="closePermissionsModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="updatePermissions">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Module
                                    </th>
                                    <th
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Create
                                    </th>
                                    <th
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        View
                                    </th>
                                    <th
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Modify
                                    </th>
                                    <th
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Delete
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($modules as $module)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span
                                                    class="text-sm font-medium text-gray-900 uppercase">{{ $module->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button"
                                                wire:click="togglePermission({{ $module->id }}, 'can_create')"
                                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition-colors
                                            {{ $permissions[$module->id]['can_create'] ?? false ? 'bg-green-100 text-green-600 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="{{ $permissions[$module->id]['can_create'] ?? false ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' }}" />
                                                </svg>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button"
                                                wire:click="togglePermission({{ $module->id }}, 'can_view')"
                                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition-colors
                                            {{ $permissions[$module->id]['can_view'] ?? false ? 'bg-blue-100 text-blue-600 hover:bg-blue-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="{{ $permissions[$module->id]['can_view'] ?? false ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' }}" />
                                                </svg>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button"
                                                wire:click="togglePermission({{ $module->id }}, 'can_modify')"
                                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition-colors
                                            {{ $permissions[$module->id]['can_modify'] ?? false ? 'bg-yellow-100 text-yellow-600 hover:bg-yellow-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="{{ $permissions[$module->id]['can_modify'] ?? false ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' }}" />
                                                </svg>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button"
                                                wire:click="togglePermission({{ $module->id }}, 'can_delete')"
                                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition-colors
                                            {{ $permissions[$module->id]['can_delete'] ?? false ? 'bg-red-100 text-red-600 hover:bg-red-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="{{ $permissions[$module->id]['can_delete'] ?? false ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' }}" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200 mt-6">
                        <button type="button" wire:click="closePermissionsModal"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Save Permissions</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
