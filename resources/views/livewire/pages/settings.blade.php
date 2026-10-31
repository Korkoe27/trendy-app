{{-- Personal settings: name and PIN. pt-14 on phones keeps the page clear of the fixed menu button --}}
<div class="space-y-6 pt-14 lg:pt-0 max-w-3xl">
    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Header -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900">Settings</h2>
        <p class="text-base text-gray-600 mt-1">Manage your own name and sign-in PIN</p>

        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-xs font-semibold text-gray-500 uppercase">Phone number (your login)</dt>
                <dd class="text-gray-900">{{ $phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-500 uppercase">Role</dt>
                <dd class="text-gray-900">{{ $roleName ?? '—' }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-gray-500">To change your phone number, ask an administrator.</p>
    </div>

    @if ($isSuperAdmin)
        <!-- Block list: Super Admin only -->
        <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-4" data-testid="block-list">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Block list</h3>
                <p class="text-sm text-gray-500">Customers with a number on this list cannot place orders. Tabs they already have can still be paid and closed.</p>
            </div>

            <form wire:submit="blockNumber" class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3 items-start">
                <div>
                    <label for="blockPhone" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Mobile number</label>
                    <input id="blockPhone" type="tel" inputmode="tel" wire:model="blockPhone" placeholder="024 123 4567"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                    @error('blockPhone') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="blockReason" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Reason (optional)</label>
                    <input id="blockReason" type="text" wire:model="blockReason" maxlength="150" placeholder="e.g. Unpaid tab"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                    @error('blockReason') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="sm:pt-6">
                    <button type="submit" wire:loading.attr="disabled" wire:target="blockNumber"
                        class="w-full sm:w-auto px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold disabled:opacity-50">
                        Block
                    </button>
                </div>
            </form>

            <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg">
                @forelse ($blockedNumbers as $blocked)
                    <li wire:key="blocked-{{ $blocked->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ \App\Support\Phone::local($blocked->phone) }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $blocked->reason ?: 'No reason given' }} • added by {{ $blocked->blocked_by_name ?? 'unknown' }}, {{ $blocked->created_at->format('M j, Y') }}
                            </p>
                        </div>
                        <button type="button" wire:click="unblockNumber({{ $blocked->id }})" wire:confirm="Remove this number from the block list?"
                            class="shrink-0 px-3 py-1.5 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Unblock</button>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-gray-500">No blocked numbers.</li>
                @endforelse
            </ul>
        </section>
    @endif

    <!-- Name -->
    <form wire:submit="saveName" class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Your name</h3>
            <p class="text-sm text-gray-500">Shown in the menu and on the orders you take. Orders already placed keep the name they were taken under.</p>
        </div>

        <div>
            <label for="name" class="block text-xs font-semibold text-gray-600 uppercase mb-1">Full name</label>
            <input id="name" type="text" wire:model="name" autocomplete="name" maxlength="255"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
            @error('name') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveName"
                class="px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-black font-semibold disabled:opacity-50">
                Save name
            </button>
        </div>
    </form>

    <!-- PIN -->
    <form wire:submit="changePin" class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Change your PIN</h3>
            <p class="text-sm text-gray-500">
                Your PIN is 6 digits.
                @if ($pinChangedAt)
                    Last changed {{ $pinChangedAt->diffForHumans() }}.
                @endif
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach ([['currentPin', 'Current PIN', 'current-password'], ['newPin', 'New PIN', 'new-password'], ['newPinConfirmation', 'Confirm new PIN', 'new-password']] as [$field, $label, $autocomplete])
                <div>
                    <label for="{{ $field }}" class="block text-xs font-semibold text-gray-600 uppercase mb-1">{{ $label }}</label>
                    <input id="{{ $field }}" type="password" inputmode="numeric" maxlength="6" autocomplete="{{ $autocomplete }}" placeholder="••••••"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');" wire:model="{{ $field }}"
                        class="w-full px-4 py-2 text-lg tracking-[0.4em] text-center border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                    @error($field) <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">Avoid easy PINs such as 123456 or 111111.</p>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="changePin"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold disabled:opacity-50">
                Change PIN
            </button>
        </div>
    </form>
</div>
