<!-- resources/views/components/nav-link.blade.php -->
@props([
    'href' => '#',
    'active' => false,
    'route' => null,
    'wireClick' => null
])

@php
    $linkHref = $route ? route($route) : $href;
    
    $classes = $active 
        ? 'bg-black text-white' 
        : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600';
@endphp

<a href="{{ $linkHref }}" 
   @if($wireClick) wire:click="{{ $wireClick }}" @endif
   class="flex flex-col lg:flex-row items-center justify-center lg:justify-start p-3 lg:px-3 lg:py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $classes }} group">
    
    {{ $slot }}
    
</a>