@props(['title' => 'Trendy', 'heading' => '', 'subheading' => ''])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite('resources/css/app.css')
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body class="font-sans antialiased bg-gray-50">
    <main class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <div class="mx-auto w-24 sm:w-32 md:w-40 flex justify-center">
                    <img src="{{ asset('assets/white-logo.png') }}" alt="Company Logo" class="w-full object-contain" />
                </div>
                <h2 class="mt-6 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900">{{ $heading }}</h2>
                @if ($subheading)
                    <p class="mt-2 text-base sm:text-lg text-gray-600">{{ $subheading }}</p>
                @endif
            </div>

            @if (session('message'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('message') }}</div>
            @endif

            @if (session('warning'))
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">{{ session('warning') }}</div>
            @endif

            <div class="bg-white p-6 sm:p-8 shadow-lg rounded-lg border border-gray-200">
                {{ $slot }}
            </div>

            <p class="text-center text-xs text-gray-500">© {{ date('Y') }} TRENDY. All rights reserved.</p>
        </div>
    </main>
</body>
</html>
