<x-layouts.auth title="Trendy - Reset PIN" heading="Enter your code" subheading="Code sent to {{ $phone }}. It expires in 5 minutes.">
    <form method="POST" action="{{ route('pin.reset.update') }}" class="space-y-6 py-2">
        @csrf

        <div>
            <label for="code" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">SMS code</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="one-time-code" placeholder="000000" autofocus required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('code') border-red-500 @enderror" />
            @error('code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="pin" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">New 6-digit PIN</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="new-password" placeholder="••••••" required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('pin') border-red-500 @enderror" />
            @error('pin')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="pin_confirmation" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Confirm new PIN</label>
            <input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="new-password" placeholder="••••••" required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent" />
        </div>

        <button type="submit" class="w-full flex justify-center py-3 px-4 text-sm font-medium text-white bg-black hover:bg-gray-800 rounded-lg focus:ring-2 focus:ring-black transition duration-200 active:scale-95">
            Reset PIN
        </button>

        <div class="text-center space-x-4">
            <a href="{{ route('pin.reset.request') }}" class="text-sm text-gray-600 hover:text-gray-900">Send a new code</a>
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to sign in</a>
        </div>
    </form>
</x-layouts.auth>
