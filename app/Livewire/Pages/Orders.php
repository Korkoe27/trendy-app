<?php

namespace App\Livewire\Pages;

use App\Models\Categories;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PriceService;
use App\Services\Payments\MobileMoneyService;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The live order board: waiters take orders, the bartender marks them ready, tabs stay open until
 * paid. All changes go through OrderService; this class only checks permissions and shapes the UI.
 */
class Orders extends Component
{
    use WithPagination;

    public string $searchTerm = '';

    /** Which board column is showing on small screens. */
    public string $activeColumn = 'pending';

    // Take order / add items modal
    public bool $showOrderModal = false;

    #[Locked]
    public ?int $editingOrderId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $notes = '';

    /** @var array<int, int> product id => quantity */
    public array $cart = [];

    public string $categoryFilter = 'all';

    public string $productSearch = '';

    // Close tab modal
    public bool $showCloseModal = false;

    #[Locked]
    public ?int $closingOrderId = null;

    public string $paymentMethod = 'cash';

    /** Number the mobile-money prompt goes to; starts as the customer's number on the order. */
    public string $momoPhone = '';

    // Cancel modal
    public bool $showCancelModal = false;

    #[Locked]
    public ?int $cancellingOrderId = null;

    public string $cancelReason = '';

    // View order modal (read-only, for completed orders)
    public bool $showViewModal = false;

    #[Locked]
    public ?int $viewingOrderId = null;

    protected $queryString = ['searchTerm'];

    public function updatedSearchTerm(): void
    {
        $this->resetPage();
    }

    // ------------------------------------------------------------------ permissions

    private function allow(string $action, string $module = 'orders'): void
    {
        abort_unless(Auth::user()?->hasPermission($action, $module), 403);
    }

    private function canServe(): bool
    {
        $user = Auth::user();

        return $user?->hasPermission('create', 'orders') || $user?->hasPermission('modify', 'orders');
    }

    /** May this user ever close tabs? (Which tabs is decided per order: see Order::canBeSettledBy.) */
    private function canSettle(): bool
    {
        $user = Auth::user();

        return $user?->hasPermission('create', 'orders') || $user?->hasPermission('modify', 'order_debts');
    }

    /** Admins and managers close any tab; a waiter only the ones they took. */
    private function canSettleAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('modify', 'order_debts');
    }

    /** An order this user is allowed to see, or 403 (a waiter never reaches another waiter's order). */
    private function visibleOrder(?int $orderId): Order
    {
        $order = Order::findOrFail($orderId);

        abort_unless($order->canBeSeenBy(Auth::user()), 403);

        return $order;
    }

    /** The order being added to, edited or closed, or 403 when this user may not touch it. */
    private function manageableOrder(?int $orderId): Order
    {
        $order = $this->visibleOrder($orderId);

        abort_unless($order->canBeManagedBy(Auth::user()), 403);

        return $order;
    }

    // ------------------------------------------------------------------ take order / add items

    public function openTakeOrder(): void
    {
        $this->allow('create');

        $this->resetOrderForm();
        $this->showOrderModal = true;
    }

    public function openAddItems(int $orderId): void
    {
        $order = $this->manageableOrder($orderId);

        if (! $order->isOpen()) {
            $this->failWith('This order is already '.$order->status.'.');

            return;
        }

        $this->resetOrderForm();
        $this->editingOrderId = $order->id;
        $this->customerName = $order->customer_name;
        $this->customerPhone = Phone::local($order->customer_phone);
        $this->showOrderModal = true;
    }

    /** A returning customer: fill in the name from their open tab if the waiter has not typed one. */
    public function updatedCustomerPhone(OrderService $orders): void
    {
        if ($this->editingOrderId || trim($this->customerName) !== '') {
            return;
        }

        if ($tab = $orders->findOpenTab($this->customerPhone)) {
            $this->customerName = $tab->customer_name;
        }
    }

    public function closeOrderModal(): void
    {
        $this->showOrderModal = false;
        $this->resetOrderForm();
    }

    public function addToCart(int $productId): void
    {
        // Managers may add to someone else's order without being able to take new ones; saving re-checks.
        abort_unless(Auth::user()?->hasPermission('create', 'orders') || $this->canSettleAny(), 403);

        $this->cart[$productId] = min(OrderService::MAX_QUANTITY, ($this->cart[$productId] ?? 0) + 1);
        $this->resetErrorBag('cart');
    }

    public function decrementCart(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($this->cart[$productId] <= 1) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId]--;
        }
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function saveOrder(OrderService $orders): void
    {
        if ($this->editingOrderId) {
            $this->manageableOrder($this->editingOrderId);
        } else {
            $this->allow('create');
        }

        $this->resetErrorBag();

        try {
            if ($this->editingOrderId) {
                $order = $orders->addItems(Order::findOrFail($this->editingOrderId), Auth::user(), $this->cart);
                $message = "Items added to {$order->code}. The bartender has been notified.";
            } else {
                $this->validate([
                    'customerName' => 'required|string|max:255',
                    'customerPhone' => ['required', function ($attribute, $value, $fail) {
                        if (! Phone::isValid($value)) {
                            $fail('Enter a valid Ghana mobile number, e.g. 024 123 4567.');
                        }
                    }],
                    'notes' => 'nullable|string|max:500',
                ], [
                    'customerName.required' => 'Enter a name for the customer.',
                    'customerPhone.required' => 'Enter the customer\'s phone number.',
                ]);

                [$order, $addedToTab] = $orders->placeOrAddToTab(Auth::user(), $this->customerName, $this->customerPhone, $this->cart, $this->notes ?: null);

                $message = $addedToTab
                    ? "Added to {$order->code} ({$order->customer_name}), tab now ₵".number_format((float) $order->total, 2).'. The bartender has been notified.'
                    : "{$order->code} placed for {$order->customer_name}.";
            }
        } catch (\DomainException $e) {
            $this->addError('cart', $e->getMessage());

            return;
        }

        session()->flash('message', $message);
        $this->closeOrderModal();
    }

    /** Change or remove an item that is still waiting for the bartender (from the add-items modal). */
    public function changeItemQuantity(OrderService $orders, int $itemId, int $quantity): void
    {
        if (! $this->editingOrderId) {
            return;
        }

        $this->manageableOrder($this->editingOrderId);

        $this->resetErrorBag('cart');

        try {
            $orders->setItemQuantity(Order::findOrFail($this->editingOrderId), Auth::user(), $itemId, max(0, $quantity));
        } catch (\DomainException $e) {
            $this->addError('cart', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ bartender / waiter actions

    public function markReady(OrderService $orders, int $orderId): void
    {
        $this->allow('modify');

        $order = $this->visibleOrder($orderId);

        $this->run(fn () => $orders->markReady($order, Auth::user()), 'Order marked ready.');
    }

    public function serve(OrderService $orders, int $orderId): void
    {
        abort_unless($this->canServe(), 403);

        $order = $this->visibleOrder($orderId);

        $this->run(fn () => $orders->serve($order, Auth::user()), 'Order served. The tab stays open until it is paid.');
    }

    // ------------------------------------------------------------------ close tab

    public function openCloseModal(int $orderId): void
    {
        $order = $this->manageableOrder($orderId);

        if (! $order->isOpen()) {
            $this->failWith('This order is already '.$order->status.'.');

            return;
        }

        $this->closingOrderId = $order->id;
        $this->momoPhone = Phone::local($order->customer_phone);
        // Only admins and managers can take cash; a waiter's close goes through mobile money.
        $this->paymentMethod = ($order->payments()->active()->exists() || ! Order::canTakeCash(Auth::user())) ? 'momo' : 'cash';
        $this->resetErrorBag();
        $this->showCloseModal = true;
    }

    public function closeCloseModal(): void
    {
        $this->showCloseModal = false;
        $this->closingOrderId = null;
        $this->resetErrorBag();
    }

    public function confirmClose(OrderService $orders): void
    {
        $order = $this->manageableOrder($this->closingOrderId);

        if (! Order::canTakeCash(Auth::user())) {
            $this->addError('paymentMethod', 'Only a manager or an admin can close a tab with cash. Use mobile money, or ask a manager.');

            return;
        }

        if ($this->paymentMethod !== 'cash') {
            $this->addError('paymentMethod', 'Use "Send payment prompt" for mobile money, or choose cash.');

            return;
        }

        try {
            $order = $orders->closeWithCash($order, Auth::user());
        } catch (\DomainException $e) {
            $this->addError('paymentMethod', $e->getMessage());

            return;
        }

        session()->flash('message', "{$order->code} paid in cash (GH₵ ".number_format((float) $order->total, 2).') and closed.');
        $this->closeCloseModal();
    }

    /** Send the mobile-money prompt to the customer's phone (only works once HUBTEL_PAYMENTS_ENABLED is on). */
    public function startMobileMoney(MobileMoneyService $momo): void
    {
        $order = $this->manageableOrder($this->closingOrderId);

        $this->resetErrorBag();

        try {
            $payment = $momo->start($order, Auth::user(), $this->momoPhone);
        } catch (\DomainException $e) {
            $this->addError('paymentMethod', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            Log::error('Mobile money start failed', ['error' => $e->getMessage()]);
            $this->addError('paymentMethod', 'Could not reach the payment provider. Please try again or settle with cash.');

            return;
        }

        $this->afterPaymentUpdate($payment);
    }

    /** Called every few seconds while the customer is approving the prompt, and by "Check status". */
    public function checkPayment(MobileMoneyService $momo): void
    {
        $order = $this->closingOrderId ? $this->manageableOrder($this->closingOrderId) : null;
        $payment = $order?->payments()->latest('id')->first();

        if (! $payment) {
            return;
        }

        $this->afterPaymentUpdate($momo->refresh($payment));
    }

    public function cancelPayment(MobileMoneyService $momo): void
    {
        $order = $this->manageableOrder($this->closingOrderId);
        $momo->cancelAttempt($order, Auth::user());

        $this->paymentMethod = 'cash';
        $this->resetErrorBag();
    }

    private function afterPaymentUpdate(OrderPayment $payment): void
    {
        if ($payment->isPaid()) {
            $order = Order::find($payment->order_id);
            session()->flash('message', "{$order->code} paid by mobile money (GH₵ ".number_format((float) $payment->amount, 2).') and closed.');
            $this->closeCloseModal();

            return;
        }

        if (! $payment->isActive()) {
            $this->addError('paymentMethod', 'The mobile money payment did not go through'.($payment->failure_reason ? ': '.$payment->failure_reason : '.').' You can try again or settle with cash.');
        }
    }

    // ------------------------------------------------------------------ cancel

    public function openCancelModal(int $orderId): void
    {
        $this->allow('delete');

        $this->cancellingOrderId = $this->visibleOrder($orderId)->id;
        $this->cancelReason = '';
        $this->resetErrorBag();
        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->cancellingOrderId = null;
        $this->cancelReason = '';
        $this->resetErrorBag();
    }

    public function confirmCancel(OrderService $orders): void
    {
        $this->allow('delete');

        try {
            $order = $orders->cancel($this->visibleOrder($this->cancellingOrderId), Auth::user(), $this->cancelReason);
        } catch (\DomainException $e) {
            $this->addError('cancelReason', $e->getMessage());

            return;
        }

        session()->flash('message', "{$order->code} cancelled.");
        $this->closeCancelModal();
    }

    // ------------------------------------------------------------------ view order

    public function openViewOrder(int $orderId): void
    {
        $this->allow('view');

        $this->viewingOrderId = $this->visibleOrder($orderId)->id;
        $this->showViewModal = true;
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->viewingOrderId = null;
    }

    // ------------------------------------------------------------------ render

    public function render(OrderService $orders, MobileMoneyService $momo)
    {
        $board = $this->filterBoard($this->ownOnly($orders->board()));

        $history = Order::query()
            ->visibleTo(Auth::user())
            ->whereIn('status', [Order::STATUS_CLOSED, Order::STATUS_CANCELLED])
            ->when($this->searchTerm !== '', fn ($q) => $this->applySearch($q))
            ->with('items')
            ->orderByDesc('id')
            ->paginate(10);

        $products = collect();
        $categories = collect();
        $cartLines = [];
        $cartTotal = 0.0;
        $editingOrder = null;
        $existingTab = null;
        $quotes = [];

        if ($this->showOrderModal) {
            $products = Product::query()
                ->where('is_active', true)
                ->where('selling_price', '>', 0)
                ->with('category')
                ->when($this->categoryFilter !== 'all', fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
                ->when(trim($this->productSearch) !== '', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($this->productSearch)).'%']))
                ->orderBy('name')
                ->get();

            $categories = Categories::whereHas('products', fn ($q) => $q->where('is_active', true))->orderBy('name')->get();

            $cartProducts = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

            // Same price rule as OrderService::place(), so the screen matches what will be charged.
            $prices = app(PriceService::class);
            $quotes = $prices->quotesNow($products->concat($cartProducts)->unique('id'));

            foreach ($this->cart as $productId => $quantity) {
                if ($product = $cartProducts->get($productId)) {
                    $price = $quotes[$product->id]['price'];
                    $line = round($price * $quantity, 2);
                    $cartLines[] = ['id' => $product->id, 'name' => $product->name, 'price' => $price, 'quantity' => $quantity, 'total' => $line, 'event' => $quotes[$product->id]['event'], 'label' => $quotes[$product->id]['label'], 'increase' => $quotes[$product->id]['increase']];
                    $cartTotal += $line;
                }
            }

            $editingOrder = $this->editingOrderId ? Order::with('items')->find($this->editingOrderId) : null;
            $existingTab = $editingOrder ? null : $orders->findOpenTab($this->customerPhone);
        }

        return view('livewire.pages.orders', [
            'board' => $board,
            'history' => $history,
            'products' => $products,
            'categories' => $categories,
            'cartLines' => $cartLines,
            'cartTotal' => $cartTotal,
            'quotes' => $quotes,
            'editingOrder' => $editingOrder,
            'existingTab' => $existingTab,
            'numberBlocked' => $this->showOrderModal && \App\Models\BlockedNumber::isBlocked($this->editingOrderId ? $editingOrder?->customer_phone : $this->customerPhone),
            'existingTabBlocked' => $existingTab && ! $existingTab->canBeManagedBy(Auth::user()),
            'closingOrder' => $closingOrder = $this->showCloseModal ? Order::with('items')->find($this->closingOrderId) : null,
            'activePayment' => $closingOrder?->payments()->active()->latest('id')->first(),
            'cancellingOrder' => $this->showCancelModal ? Order::find($this->cancellingOrderId) : null,
            'viewingOrder' => $viewingOrder = $this->showViewModal ? Order::with('items')->find($this->viewingOrderId) : null,
            'viewingCancelledBy' => $viewingOrder?->cancelled_by ? User::whereKey($viewingOrder->cancelled_by)->value('name') : null,
            'momoEnabled' => $momo->available(),
            'momoSwitchedOn' => $momo->enabled(),
            'canOrder' => Auth::user()->hasPermission('create', 'orders'),
            'canReady' => Auth::user()->hasPermission('modify', 'orders'),
            'canServe' => $this->canServe(),
            'canSettle' => $this->canSettle(),
            'canSettleAny' => $this->canSettleAny(),
            'canTakeCash' => Order::canTakeCash(Auth::user()),
            'userId' => (int) Auth::id(),
            'canCancel' => Auth::user()->hasPermission('delete', 'orders'),
            'modalOpen' => $this->showOrderModal || $this->showCloseModal || $this->showCancelModal || $this->showViewModal,
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /** The board is cached for everyone, so a waiter's view is cut down here. */
    private function ownOnly(array $board): array
    {
        $user = Auth::user();

        if (Order::seesAll($user)) {
            return $board;
        }

        return array_map(fn (array $column) => array_values(array_filter(
            $column,
            fn (array $order) => $order['taken_by'] !== null && $order['taken_by'] === (int) $user->id
        )), $board);
    }

    private function filterBoard(array $board): array
    {
        $term = mb_strtolower(trim($this->searchTerm));

        if ($term === '') {
            return $board;
        }

        $digits = preg_replace('/\D+/', '', ltrim($term, '0+'));

        return array_map(fn (array $orders) => array_values(array_filter($orders, function (array $order) use ($term, $digits) {
            return str_contains(mb_strtolower($order['customer_name']), $term)
                || str_contains(mb_strtolower($order['code']), $term)
                || ($digits !== '' && strlen($digits) >= 3 && str_contains($order['customer_phone'], $digits));
        })), $board);
    }

    private function applySearch($query)
    {
        $term = trim($this->searchTerm);
        $digits = preg_replace('/\D+/', '', ltrim($term, '0+'));

        return $query->where(function ($q) use ($term, $digits) {
            // LOWER() because LIKE is case-sensitive on PostgreSQL.
            $needle = '%'.mb_strtolower($term).'%';

            $q->whereRaw('LOWER(customer_name) LIKE ?', [$needle])->orWhereRaw('LOWER(code) LIKE ?', [$needle]);

            if (strlen($digits) >= 3) {
                $q->orWhere('customer_phone', 'like', "%{$digits}%");
            }
        });
    }

    private function run(\Closure $action, string $successMessage): void
    {
        try {
            $action();
            session()->flash('message', $successMessage);
        } catch (\DomainException $e) {
            $this->failWith($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Order action failed', ['error' => $e->getMessage()]);
            $this->failWith('Something went wrong. Please try again.');
        }
    }

    private function failWith(string $message): void
    {
        session()->flash('error', $message);
    }

    private function resetOrderForm(): void
    {
        $this->editingOrderId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->notes = '';
        $this->cart = [];
        $this->categoryFilter = 'all';
        $this->productSearch = '';
        $this->resetValidation();
    }
}
