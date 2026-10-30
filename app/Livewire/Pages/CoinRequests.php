<?php

namespace App\Livewire\Pages;

use App\Models\CoinRequest;
use App\Models\Product;
use App\Services\CoinRequestService;
use App\Services\PriceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Snooker coins: the attendant requests coins, the bar gives them. Every change goes through
 * CoinRequestService; this class only checks permissions and shapes the screen.
 */
class CoinRequests extends Component
{
    public $productId = '';

    public int $quantity = 1;

    public string $note = '';

    public bool $showVoidModal = false;

    public ?int $voidingId = null;

    public string $voidReason = '';

    public function mount(): void
    {
        $first = app(CoinRequestService::class)->games()->first();
        $this->productId = $first?->id ?? '';
    }

    private function allow(string $action): void
    {
        abort_unless(Auth::user()?->hasPermission($action, 'coin_requests'), 403);
    }

    public function incrementQuantity(): void
    {
        $this->quantity = min(app(CoinRequestService::class)->maxPerRequest(), $this->quantity + 1);
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function requestCoins(CoinRequestService $coins): void
    {
        $this->allow('create');

        $game = Product::find((int) $this->productId);

        if (! $game) {
            $this->addError('productId', 'Pick a game.');

            return;
        }

        try {
            $coins->request(Auth::user(), $game, (int) $this->quantity, $this->note);
        } catch (\DomainException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->reset('note');
        $this->quantity = 1;
        session()->flash('message', 'Coins requested. The bar has been notified.');
    }

    public function give(int $id, CoinRequestService $coins): void
    {
        $this->allow('modify');

        try {
            $coins->give(CoinRequest::findOrFail($id), Auth::user());
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('message', 'Marked as given.');
    }

    public function cancel(int $id, CoinRequestService $coins): void
    {
        abort_unless(Auth::user()?->hasPermission('create', 'coin_requests') || Auth::user()?->hasPermission('delete', 'coin_requests'), 403);

        try {
            $coins->cancel(CoinRequest::findOrFail($id), Auth::user());
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('message', 'Request cancelled.');
    }

    public function openVoid(int $id): void
    {
        $this->allow('delete');

        $this->voidingId = $id;
        $this->voidReason = '';
        $this->resetErrorBag();
        $this->showVoidModal = true;
    }

    public function closeVoid(): void
    {
        $this->showVoidModal = false;
        $this->voidingId = null;
        $this->resetErrorBag();
    }

    public function voidGiven(CoinRequestService $coins): void
    {
        $this->allow('delete');

        try {
            $coins->void(CoinRequest::findOrFail($this->voidingId), Auth::user(), $this->voidReason);
        } catch (\DomainException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        $this->closeVoid();
        session()->flash('message', 'The coins were voided and no longer count.');
    }

    public function render(CoinRequestService $coins)
    {
        $date = $coins->businessDate();
        $games = $coins->games()->map(fn (Product $game) => [
            'id' => $game->id,
            'name' => $game->name,
            'daily' => (int) $game->daily_coins,
            'available' => $coins->available($game, $date),
            'price' => app(PriceService::class)->quoteNow($game)['price'],
        ]);

        $today = CoinRequest::whereDate('business_date', $date->toDateString());

        return view('livewire.pages.coin-requests', [
            'games' => $games,
            'selected' => $games->firstWhere('id', (int) $this->productId),
            'waiting' => (clone $today)->where('status', CoinRequest::STATUS_REQUESTED)->orderBy('requested_at')->get(),
            'given' => (clone $today)->whereIn('status', [CoinRequest::STATUS_GIVEN, CoinRequest::STATUS_VOIDED])->orderByDesc('given_at')->get(),
            'totals' => $coins->givenOn($date),
            'date' => $date,
            'canRequest' => Auth::user()->hasPermission('create', 'coin_requests'),
            'canGive' => Auth::user()->hasPermission('modify', 'coin_requests'),
            'canManage' => Auth::user()->hasPermission('delete', 'coin_requests'),
            'userId' => (int) Auth::id(),
            'maxPerRequest' => $coins->maxPerRequest(),
            'hasWaiting' => $coins->blocksNewRequests() && $coins->waitingFor(Auth::user()) !== null,
        ]);
    }
}
