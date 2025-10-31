@props(['activeItem' => null])
@php
    $f_name = explode(' ', Auth::user()->name)[0];
@endphp


<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scrollbar-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        @vite('resources/css/app.css')
        @livewireStyles
        <title>{{ $title ?? 'Trendy App' }}</title>
    </head>
    <body class="flex md:gap-4 p-1 w-full lg:p-4 scrollbar-hidden bg-gray-100 h-screen">
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap');


        </style>
        <aside class="w-fit h-fit">
           <livewire:components.sidebar :active-item="$activeItem"/>
        </aside>
        <main class="w-full  lg:h-[calc(100vh-2rem)] h-[calc(100vh-5rem)] overflow-auto scrollbar-hidden  flex flex-col">
            {{ $slot }}
        </main>
            @livewireScripts
    </body>
</html>
