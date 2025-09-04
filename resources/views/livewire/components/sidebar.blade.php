<div class="relative h-full">
    @php
    $f_name = explode(' ', Auth::user()->name)[0];
    $f_initial = strtoupper(substr(Auth::user()->name, 0, 1));
    $l_initial = strtoupper(substr(strrchr(Auth::user()?->name, ' '), 1, 1));
    $initials = $f_initial . $l_initial;
@endphp

    <!-- Mobile overlay -->
    <div x-show="$wire.isOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/50 bg-opacity-50 lg:hidden"
         wire:click="closeSidebar">
    </div>

    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 h-full md:rounded-2xl z-50 w-72 bg-white shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0"
         :class="{ 'translate-x-0': $wire.isOpen, '-translate-x-full': !$wire.isOpen }">
        
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <img src="{{ asset('assets/logo.svg') }}" alt="Logo" class="w-1/3 md:w-2/5 mx-auto">
            <button wire:click="closeSidebar" class="lg:hidden p-2 rounded-md hover:bg-gray-100">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-4 space-y-2">
            <!-- Dashboard Link -->
            <a href="{{ route('dashboard') }}"
               wire:navigate 
               wire:click="setActiveItem('dashboard')"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('dashboard') ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>
                Dashboard
            </a>

            <!-- Inventory Link -->
            <a href="{{ route('inventory') }}" 
               wire:navigate
               wire:click="setActiveItem('inventory')"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('inventory') ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                Inventory
            </a>

            <!-- Products Link -->
            <a href="{{ route('products') }}" 
               wire:navigate
               wire:click="setActiveItem('products')"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('products') ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 shrink-0">
                    <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>
                    <path d="M12 22V12"/>
                    <polyline points="3.29 7 12 12 20.71 7"/>
                    <path d="m7.5 4.27 9 5.15"/>
                </svg>
                Products
            </a>

            <!-- Stocks Link -->
            <a href="{{ route('stocks') }}" 
               wire:navigate
               wire:click="setActiveItem('stocks')"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('stocks') ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                Stocks
            </a>

            <!-- Logs Link -->
            <a href="{{ route('logs') }}" 
               wire:navigate
               wire:click="setActiveItem('logs')"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('logs') ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 shrink-0"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                Logs
            </a>

            <!-- Settings Link -->
            <a href="{{ route('settings') }}" 
               wire:navigate
               wire:click="setActiveItem('settings')"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 {{ request()->routeIs('settings') ? 'bg-black text-white' : 'bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
            </a>
        </nav>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center">
                <span class="rounded-full bg-gray-200 text-gray-600 w-10 h-10 flex items-center justify-center font-semibold">
                    {{ $initials }}
                </span>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-700">{{ $f_name }}</p>
                    {{-- <p class="text-xs text-gray-500">{{ Auth::user()->name }}</p> --}}
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST" class="mt-2">
                @csrf
                <button type="submit"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors duration-200 bg-white text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Logout
                </button>
            </form>
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