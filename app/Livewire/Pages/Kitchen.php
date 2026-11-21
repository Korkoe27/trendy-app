<?php

namespace App\Livewire\Pages;

use App\Models\FoodOrder;
use App\Models\Foodstuff;
use App\Models\ShoppingList;
use App\Services\KitchenService;
use App\Services\OrderService;
use App\Services\PriceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The kitchen dashboard: food orders to prepare, the foodstuff list, and the chef's shopping
 * lists. Every change goes through OrderService or KitchenService; this class only checks
 * permissions and shapes the screen.
 */
class Kitchen extends Component
{
    // Foodstuff list
    public bool $showFoodstuff = false;

    public string $foodstuffName = '';

    public string $foodstuffUnit = '';

    public string $foodstuffSupplier = '';

    // Editing one item on the foodstuff list
    public ?int $editingFoodstuffId = null;

    public string $editFoodstuffName = '';

    public string $editFoodstuffUnit = '';

    public string $editFoodstuffSupplier = '';

    // New / edited shopping list
    public bool $showListModal = false;

    /** When set, the open list modal edits this pending list instead of creating a new one. */
    public ?int $editingListId = null;

    /** @var list<array{foodstuff_id: string, quantity: string, cost: string}> */
    public array $lines = [];

    public string $listSupplier = '';

    public string $listNotes = '';

    // Marking a list as bought
    public bool $showBoughtModal = false;

    public ?int $boughtListId = null;

    public string $paymentMethod = 'cash';

    private function allow(string $action): void
    {
        abort_unless(Auth::user()?->hasPermission($action, 'kitchen'), 403);
    }

    private function mayBuy(): bool
    {
        return (bool) Auth::user()?->hasPermission('create', 'expenses');
    }

    // ------------------------------------------------------------------ food orders

    public function markReady(int $foodOrderId, OrderService $orders): void
    {
        $this->allow('modify');

        $food = FoodOrder::with('order')->findOrFail($foodOrderId);

        try {
            $orders->markReady($food->order, Auth::user(), 'kitchen');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('message', "{$food->code} marked ready. The waiter has been notified.");
    }

    // ------------------------------------------------------------------ foodstuff

    public function openFoodstuff(): void
    {
        $this->allow('view');

        $this->cancelEditFoodstuff();
        $this->reset('foodstuffName', 'foodstuffUnit', 'foodstuffSupplier');
        $this->resetErrorBag();
        $this->showFoodstuff = true;
    }

    public function closeFoodstuff(): void
    {
        $this->showFoodstuff = false;
        $this->cancelEditFoodstuff();
        $this->resetErrorBag();
    }

    public function addFoodstuff(KitchenService $kitchen): void
    {
        $this->allow('create');

        try {
            $item = $kitchen->addFoodstuff(Auth::user(), $this->foodstuffName, $this->foodstuffUnit, $this->foodstuffSupplier);
        } catch (\DomainException $e) {
            $this->addError('foodstuffName', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->reset('foodstuffName', 'foodstuffUnit', 'foodstuffSupplier');
        session()->flash('message', "{$item->name} added to the foodstuff list.");
    }

    /** Put one foodstuff row into edit mode inside the list. */
    public function editFoodstuff(int $id): void
    {
        $this->allow('create');

        $item = Foodstuff::findOrFail($id);

        $this->editingFoodstuffId = $id;
        $this->editFoodstuffName = $item->name;
        $this->editFoodstuffUnit = $item->unit ?? '';
        $this->editFoodstuffSupplier = $item->supplier ?? '';
        $this->resetErrorBag();
    }

    public function cancelEditFoodstuff(): void
    {
        $this->editingFoodstuffId = null;
        $this->editFoodstuffName = '';
        $this->editFoodstuffUnit = '';
        $this->editFoodstuffSupplier = '';
        $this->resetErrorBag();
    }

    public function saveFoodstuff(KitchenService $kitchen): void
    {
        $this->allow('create');

        try {
            $item = $kitchen->updateFoodstuff(
                Auth::user(),
                Foodstuff::findOrFail($this->editingFoodstuffId),
                $this->editFoodstuffName,
                $this->editFoodstuffUnit,
                $this->editFoodstuffSupplier
            );
        } catch (\DomainException $e) {
            $this->addError('editFoodstuffName', $e->getMessage());

            return;
        }

        $this->cancelEditFoodstuff();
        session()->flash('message', "{$item->name} updated on the foodstuff list.");
    }

    // ------------------------------------------------------------------ shopping lists

    public function openList(): void
    {
        $this->allow('create');

        $this->editingListId = null;
        $this->reset('listSupplier', 'listNotes');
        $this->lines = [$this->blankLine()];
        $this->resetErrorBag();
        $this->showListModal = true;
    }

    public function closeList(): void
    {
        $this->showListModal = false;
        $this->editingListId = null;
        $this->resetErrorBag();
    }

    private function blankLine(): array
    {
        return ['foodstuff_id' => '', 'quantity' => '', 'cost' => ''];
    }

    /** Pre-fill the list modal with a pending list so the chef can re-curate it. */
    public function editList(int $listId): void
    {
        $this->allow('create');

        $list = ShoppingList::with('items')->findOrFail($listId);

        if (! $list->isPending()) {
            session()->flash('error', "Only a list that has not been bought can be edited.");

            return;
        }

        $this->editingListId = $listId;
        $this->listSupplier = $list->supplier ?? '';
        $this->listNotes = $list->notes ?? '';
        $this->lines = $list->items->map(fn ($item) => [
            'foodstuff_id' => $item->foodstuff_id ? (string) $item->foodstuff_id : '',
            'quantity' => $item->quantity === null ? '' : $this->prettyNumber((float) $item->quantity),
            'cost' => $this->prettyNumber((float) $item->cost),
        ])->values()->all() ?: [$this->blankLine()];

        $this->resetErrorBag();
        $this->showListModal = true;
    }

    private function prettyNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function addLine(): void
    {
        $this->allow('create');

        if (count($this->lines) < KitchenService::MAX_LINES) {
            $this->lines[] = $this->blankLine();
        }
    }

    public function removeLine(int $index): void
    {
        $this->allow('create');

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [$this->blankLine()];
    }

    public function submitList(KitchenService $kitchen): void
    {
        $this->allow('create');

        try {
            if ($this->editingListId) {
                $list = $kitchen->updateList(Auth::user(), ShoppingList::findOrFail($this->editingListId), $this->lines, $this->listSupplier, $this->listNotes);
            } else {
                $list = $kitchen->createList(Auth::user(), $this->lines, $this->listSupplier, $this->listNotes);
            }
        } catch (\DomainException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        $editing = $this->editingListId !== null;

        $this->closeList();
        session()->flash('message', $editing
            ? "Shopping list {$list->code} updated. Choose who to send it to on WhatsApp."
            : "Shopping list {$list->code} saved. Choose who to send it to on WhatsApp.");

        // Opens WhatsApp with the (new or re-curated) list so the chef picks the person to send it to.
        $this->dispatch('open-whatsapp', url: $kitchen->whatsappUrl($list));
    }

    public function sendWhatsapp(int $listId, KitchenService $kitchen): void
    {
        $this->allow('view');

        $this->dispatch('open-whatsapp', url: $kitchen->whatsappUrl(ShoppingList::with('items')->findOrFail($listId)));
    }

    public function openBought(int $listId): void
    {
        abort_unless($this->mayBuy(), 403);

        $this->boughtListId = $listId;
        $this->paymentMethod = 'cash';
        $this->resetErrorBag();
        $this->showBoughtModal = true;
    }

    public function closeBought(): void
    {
        $this->showBoughtModal = false;
        $this->boughtListId = null;
        $this->resetErrorBag();
    }

    public function markBought(KitchenService $kitchen): void
    {
        abort_unless($this->mayBuy(), 403);

        try {
            $list = $kitchen->markBought(Auth::user(), ShoppingList::findOrFail($this->boughtListId), $this->paymentMethod);
        } catch (\DomainException $e) {
            $this->addError('paymentMethod', $e->getMessage());

            return;
        }

        $this->closeBought();
        session()->flash('message', "{$list->code} marked as bought and recorded on the spending dashboard.");
    }

    public function cancelList(int $listId, KitchenService $kitchen): void
    {
        $this->allow('delete');

        try {
            $list = $kitchen->cancelList(Auth::user(), ShoppingList::findOrFail($listId));
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('message', "{$list->code} cancelled.");
    }

    public function render()
    {
        $date = app(PriceService::class)->businessDate()->toDateString();

        $food = FoodOrder::with('items')->whereDate('business_date', $date);

        return view('livewire.pages.kitchen', [
            'date' => $date,
            'pending' => (clone $food)->where('status', FoodOrder::STATUS_PENDING)->with('order:id,notes,code')->orderBy('id')->get(),
            'ready' => (clone $food)->where('status', FoodOrder::STATUS_READY)->with('order:id,notes,code')->orderBy('ready_at')->get(),
            'pickedUp' => (clone $food)->where('status', FoodOrder::STATUS_PICKED_UP)->with('order:id,code')->orderByDesc('picked_up_at')->limit(15)->get(),
            'openLists' => ShoppingList::with('items')->where('status', ShoppingList::STATUS_PENDING)->orderByDesc('id')->get(),
            'pastLists' => ShoppingList::whereIn('status', [ShoppingList::STATUS_BOUGHT, ShoppingList::STATUS_CANCELLED])->orderByDesc('id')->limit(8)->get(),
            'foodstuff' => Foodstuff::where('is_active', true)->orderBy('name')->get(),
            'canReady' => Auth::user()->hasPermission('modify', 'kitchen'),
            'canCreate' => Auth::user()->hasPermission('create', 'kitchen'),
            'canCancel' => Auth::user()->hasPermission('delete', 'kitchen'),
            'canBuy' => $this->mayBuy(),
            'paymentMethods' => ['cash' => 'Cash', 'momo' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer', 'credit' => 'Credit'],
            'modalOpen' => $this->showFoodstuff || $this->showListModal || $this->showBoughtModal,
        ]);
    }
}
