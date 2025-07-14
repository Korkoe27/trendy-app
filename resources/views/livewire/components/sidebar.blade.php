<!-- resources/views/livewire/components/sidebar.blade.php -->
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
            <!-- Dashboard Link -->
            <a href="{{ route('dashboard') }}"
            wire:navigate 
            wire:click="setActiveItem('dashboard')"
            class="flex items-center px-3 py-2 text-base font-medium rounded-md transition-colors duration-200 {{ request()->routeIs('dashboard') ? 'bg-black text-white' : 'bg-white text-black hover:text-gray-700 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>
                Dashboard
            </a>

            <!-- Products Link -->
            <a href="{{ route('products') }}" 
                wire:navigate
                wire:click="setActiveItem('products')"
                class="flex items-center px-3 py-2 text-base font-medium rounded-md transition-colors duration-200 {{ request()->routeIs('products') ? 'bg-black text-white' : 'bg-white text-black hover:text-gray-700 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-icon lucide-package mr-3">
                    <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>
                    <path d="M12 22V12"/>
                    <polyline points="3.29 7 12 12 20.71 7"/>
                    <path d="m7.5 4.27 9 5.15"/>
                </svg>
                Products
            </a>

            <!-- products Link -->
            <a href="{{ route('stocks') }}" 
                wire:navigate
                wire:click="setActiveItem('stocks')"
                class="flex items-center gap-3 px-3 py-2 text-base font-medium rounded-md transition-colors duration-200 {{ request()->routeIs('stocks') ? 'bg-black text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>

                Stocks
            </a>

            <!-- Settings Link -->
            <a href="{{ route('products') }}" 
               wire:navigate
               wire:click="setActiveItem('settings')"
               class="flex items-center px-3 py-2 text-base font-medium rounded-md transition-colors duration-200 {{ request()->routeIs('settings') ? 'bg-black text-white' : 'text-gray-700 hover:bg-gray-100' }}">
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