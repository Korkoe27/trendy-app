<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite('resources/css/app.css')
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Trendy - Change Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body class="font-sans antialiased bg-gray-50">
    <main class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md space-y-8">

            <!-- Logo Section -->
            <div class="text-center">
                <div class="mx-auto w-24 sm:w-32 md:w-40 flex justify-center">
                    <img src="{{ asset('assets/white-logo.png') }}" alt="Company Logo" class="w-full object-contain" />
                </div>
                <h2 class="mt-6 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900">Change Your Password</h2>
                <p class="mt-2 text-base sm:text-lg md:text-xl text-gray-600">You must update your password before continuing</p>
            </div>

            <!-- Alerts -->
            @if (session('warning'))
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Change Password Form -->
            <div class="bg-white p-6 sm:p-8 shadow-lg rounded-lg border border-gray-200">
                <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
                    @csrf

                    <!-- Current Password -->
                    <div>
                        <label for="current_password" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Current Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input 
                                id="current_password" 
                                name="current_password" 
                                type="password" 
                                placeholder="Enter your current password" 
                                required 
                                class="block w-full pl-10 pr-3 py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent"
                            />
                        </div>
                    </div>

                    <!-- New Password -->
                    <div>
                        <label for="password" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                placeholder="Enter new password" 
                                required 
                                class="block w-full pl-10 pr-3 py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters</p>
                    </div>

                    <!-- Confirm New Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Confirm New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input 
                                id="password_confirmation" 
                                name="password_confirmation" 
                                type="password" 
                                placeholder="Confirm new password" 
                                required 
                                class="block w-full pl-10 pr-3 py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent"
                            />
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="group w-full flex justify-center py-3 px-4 text-sm font-medium text-white bg-black hover:bg-gray-800 rounded-lg focus:ring-2 focus:ring-black transform transition duration-200 hover:scale-105 active:scale-95">
                        Change Password
                    </button>

                    <!-- Logout -->
                    <div class="text-center">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">Logout</button>
                        </form>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <p class="text-center text-xs text-gray-500">© {{ date('Y') }} TRENDY. All rights reserved.</p>
        </div>
    </main>
</body>
</html>
