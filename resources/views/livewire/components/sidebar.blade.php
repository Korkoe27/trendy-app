<!-- resources/views/livewire/sidebar.blade.php -->
<div class="relative h-full">
    <!-- Mobile overlay -->
    <div x-show="$wire.isOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black bg-opacity-50 lg:hidden"
         wire:click="closeSidebar">
    </div>

    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 h-full md:rounded-2xl z-50 w-72 bg-white shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0"
         :class="{ 'translate-x-0': $wire.isOpen, '-translate-x-full': !$wire.isOpen }">
        
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <img src="{{ asset('assets/logo.svg') }}" alt="" class="w-1/4 md:w-2/5 mx-auto">
            <button wire:click="closeSidebar" class="lg:hidden p-2 rounded-md hover:bg-gray-100">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-4 space-y-1">
            <a href="{{ route('dashboard') }}"
            wire:navigate 
            wire:current="bg-black text-white hover:text-gray-700 hover:bg-gray-100"
            {{-- wire:click="setActiveItem('/')" --}}
            class="flex items-center px-3 py-2 text-base font-medium rounded-md transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>

                Dashboard
            </a>

            <a href="{{ route('products') }}" 
            {{-- wire:click="setActiveItem('products')" --}}
            wire:navigate
            wire:current="bg-black text-white hover:text-gray-700 hover:bg-gray-100"
            class="flex items-center px-3 py-2 text-base font-medium rounded-md transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-icon lucide-package"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/></svg>
                Products
            </a>

            <a href="#" 
               wire:click="setActiveItem('projects')"
               class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 {{ $activeItem === 'projects' ? 'bg-blue-500 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Projects
            </a>

            <a href="" 
               wire:click="setActiveItem('settings')"
               class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 {{ $activeItem === 'settings' ? 'bg-blue-500 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
            </a>
        </nav>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center">
                <img class="w-8 h-8 rounded-full" src="https://via.placeholder.com/32x32" alt="User avatar">
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-700">John Doe</p>
                    <p class="text-xs text-gray-500">Admin</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu button -->
    <button wire:click="toggleSidebar" 
            class="fixed top-4 left-4 z-50 p-2 bg-white rounded-md shadow-md lg:hidden">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
</div>