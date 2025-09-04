@props(['activeItem' => null])
@php
    $f_name = explode(' ', Auth::user()->name)[0];
@endphp


<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        @vite('resources/css/app.css')
        @livewireStyles
        <title>{{ $title ?? 'Trendy App' }}</title>
    </head>
    <body class="flex gap-4 p-1 lg:p-4 bg-gray-100 h-screen">
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap');


        </style>
        <aside class="">
           <livewire:components.sidebar :active-item="$activeItem"/>
        </aside>
        <main class="w-full">
            {{ $slot }}
        </main>
            @livewireScripts
    </body>
</html>
