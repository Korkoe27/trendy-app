<x-layouts.auth title="Trendy - Forgot PIN" heading="Forgot your PIN?" subheading="We will text a 6-digit code to your phone">
    <form method="POST" action="{{ route('pin.reset.send') }}" class="space-y-6 py-2">
        @csrf

        <div>
            <label for="phone" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Phone number</label>
            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" placeholder="024 123 4567"
                inputmode="tel" autocomplete="tel" autofocus required
                class="block w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent @error('phone') border-red-500 @enderror" />
            @error('phone')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full flex justify-center py-3 px-4 text-sm font-medium text-white bg-black hover:bg-gray-800 rounded-lg focus:ring-2 focus:ring-black transition duration-200 active:scale-95">
            Send code
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to sign in</a>
        </div>
    </form>
</x-layouts.auth>
