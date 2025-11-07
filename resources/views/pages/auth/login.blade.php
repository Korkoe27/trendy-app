<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite('resources/css/app.css')
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Trendy - Login</title>
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
          <h2 class="mt-6 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900">Welcome Back</h2>
          <p class="mt-2 text-base sm:text-lg md:text-xl text-gray-600">Please sign in to your account</p>
        </div>
  
        <!-- Login Form -->
        <div class="bg-white p-6 sm:p-8 shadow-lg rounded-lg border border-gray-200">
          <form method="POST" action="{{ url('signin') }}" class="space-y-6 py-4">
            @csrf
  
            <!-- Username Field -->
            <div>
              <label for="username" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Username</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <input 
                  type="text"
                  name="username"
                  id="username"
                  value="{{ old('username') }}"
                  placeholder="Enter your username"
                  class="block w-full pl-10 pr-3 py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('username') border-red-500 @enderror"
                  required
                />
              </div>
              @error('username')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
              @enderror
            </div>
  
            <!-- Password Field -->
            <div>
              <label for="password" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Password</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <input 
                  type="password"
                  name="password"
                  id="password"
                  placeholder="Enter your password"
                  class="block w-full pl-10 pr-3 py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('password') border-red-500 @enderror"
                  required
                />
              </div>
              @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
              @enderror
            </div>
  
            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between">
              <label for="remember" class="flex items-center space-x-2 text-sm text-gray-700">
                <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-black border-gray-300 rounded focus:ring-black" />
                <span>Remember me</span>
              </label>
              {{-- <a href="#" class="text-sm font-medium text-black hover:text-gray-800">Forgot password?</a> --}}
            </div>
  
            <!-- Submit Button -->
            <button type="submit" class="group w-full flex justify-center py-3 px-4 text-sm font-medium text-white bg-black hover:bg-gray-800 rounded-lg focus:ring-2 focus:ring-black transform transition duration-200 hover:scale-105 active:scale-95">
              <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-200" ...></svg>
              </span>
              Sign In
            </button>
  
            <!-- Divider -->
            {{-- <div class="relative flex items-center py-2">
              <div class="flex-grow border-t border-gray-300"></div>
              <span class="px-2 text-sm text-gray-500 bg-white">Don't have an account?</span>
              <div class="flex-grow border-t border-gray-300"></div>
            </div> --}}
  
            <!-- Sign Up -->
            {{-- <div class="text-center">
              <a href="#" class="text-sm font-medium text-black hover:text-gray-800">Create a new account</a>
            </div> --}}
          </form>
        </div>
  
        <!-- Footer -->
        <p class="text-center text-xs text-gray-500">© {{ date('Y') }} TRENDY. All rights reserved.</p>
      </div>
    </main>
  </body>
  
</html>