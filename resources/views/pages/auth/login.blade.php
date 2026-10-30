<x-layouts.auth title="Trendy - Login" heading="Welcome Back" subheading="Sign in with your phone number and PIN">
    <form method="POST" action="{{ url('signin') }}" class="space-y-6 py-2">
        @csrf

        <!-- Phone number -->
        <div>
            <label for="phone" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Phone number</label>
            <input
                type="tel"
                name="phone"
                id="phone"
                value="{{ old('phone') }}"
                placeholder="024 123 4567"
                inputmode="tel"
                autocomplete="tel"
                autofocus
                required
                class="block w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('phone') border-red-500 @enderror"
            />
            @error('phone')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- PIN -->
        <div>
            <label for="pin" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">6-digit PIN</label>
            <input
                type="password"
                name="pin"
                id="pin"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="current-password"
                placeholder="••••••"
                required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('pin') border-red-500 @enderror"
            />
            @error('pin')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="flex items-center space-x-2 text-sm text-gray-700">
                <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-black border-gray-300 rounded focus:ring-black" />
                <span>Remember me</span>
            </label>
            <a href="{{ route('pin.reset.request') }}" class="text-sm font-medium text-black hover:text-gray-700">Forgot PIN?</a>
        </div>

        <button type="submit" class="w-full flex justify-center py-3 px-4 text-sm font-medium text-white bg-black hover:bg-gray-800 rounded-lg focus:ring-2 focus:ring-black transition duration-200 active:scale-95">
            Sign In
        </button>
    </form>
</x-layouts.auth>
