<x-layouts.auth title="Trendy - Change PIN" heading="Set Your PIN" subheading="Choose a 6-digit PIN you will remember">
    @if ($errors->any())
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
        @csrf

        <div>
            <label for="current_pin" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Current PIN</label>
            <input id="current_pin" name="current_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="current-password" placeholder="••••••" required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent" />
        </div>

        <div>
            <label for="pin" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">New PIN</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="new-password" placeholder="••••••" required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent" />
            <p class="mt-1 text-xs text-gray-500">Exactly 6 digits. Avoid 123456, 111111 and similar.</p>
        </div>

        <div>
            <label for="pin_confirmation" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Confirm new PIN</label>
            <input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="new-password" placeholder="••••••" required
                class="block w-full px-4 py-3 text-xl tracking-[0.5em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent" />
        </div>

        <button type="submit" class="w-full flex justify-center py-3 px-4 text-sm font-medium text-white bg-black hover:bg-gray-800 rounded-lg focus:ring-2 focus:ring-black transition duration-200 active:scale-95">
            Save PIN
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">Logout</button>
    </form>
</x-layouts.auth>
