<x-layouts.app active-item="/" title="Dashboard">

        @php
    $hour = now()->hour;

    if ($hour < 12) {
        $greeting = "Good Morning";
    } elseif ($hour < 18) {
        $greeting = "Good Afternoon";
    } else {
        $greeting = "Good Evening";
    }
@endphp 
    <section class="w-full h-full flex-col gap-4 flex rounded-2xl">
        <header class="w-full flex  md:items-center justify-between py-4 px-2 md:p-4 rounded-2xl">

                <h1 class="hidden md:flex items-center gap-1 md:gap-3">
                    <span class="lg:text-3xl text-base">{{ $greeting }} </span>
                {{-- <span class="text-[#0F51AE] text-lg lg:text-xl rounded-full bg-[#F2F8FF] px-2 p-1 font-semibold">{{ Auth::user()->name  }}</span>  --}}
                    <span class="text-[#0F51AE] text-sm lg:text-xl rounded-full bg-[#F2F8FF] px-2 py-1 font-semibold">Trace</span>
                </h1>

                <livewire:components.clock/>
        </header>

        <div class="bg-white md:items-start md:justify-between flex w-full gap-2 md:gap-4 flex-col md:flex-row md:p-4 h-full rounded-2xl shadow-lg
        ">

            <div class="flex items-center border gap-10 p-8 rounded-2xl justify-between">
                <div>
                <p class="text-sm font-medium text-gray-600">Today's Revenue</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">GHC 5380.30</p>
                </div>
                <div class="bg-blue-100 p-2 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dollar-sign-icon lucide-dollar-sign"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>

                </div>
            </div>
            <div class="flex items-center border  p-8 rounded-2xl justify-between">
                <div>
                <p class="text-sm font-medium text-gray-600">Item's sold</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">189</p>
                </div>
                <div class="bg-blue-100 p-2 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-icon lucide-package"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/></svg>

                </div>
            </div>
            <div class="flex items-center border  p-8 rounded-2xl justify-between">
                <div>
                <p class="text-sm font-medium text-gray-600">Low Stock Items</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">39</p>
                </div>
                <div class="bg-red-100 p-2 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>

                </div>
            </div>
        </div>
    

    </section>
</x-layouts.app>