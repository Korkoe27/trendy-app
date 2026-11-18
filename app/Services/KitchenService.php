<?php

namespace App\Services;

use App\Models\ActivityLogs;
use App\Models\Expense;
use App\Models\Foodstuff;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The kitchen's foodstuff list and the chef's shopping lists. A list is made before going to the
 * market (what, how many, the price of each), shared on WhatsApp, and marked as bought by the
 * accountant or an admin, which records it as an expense on the spending dashboard.
 *
 * Business-rule failures throw \DomainException with a message that is safe to show to staff.
 */
class KitchenService
{
    public const MAX_LINES = 100;

    public const PAYMENT_METHODS = ['cash', 'bank_transfer', 'momo', 'credit'];

    // ------------------------------------------------------------------ foodstuff

    /**
     * @throws \DomainException
     */
    public function addFoodstuff(User $by, string $name, ?string $unit = null, ?string $supplier = null): Foodstuff
    {
        $this->assertMay($by, 'create');

        $name = trim($name);

        if ($name === '') {
            throw new \DomainException('Enter the name of the item.');
        }

        if (mb_strlen($name) > 120) {
            throw new \DomainException('The item name is too long.');
        }

        if (Foodstuff::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new \DomainException("{$name} is already on the foodstuff list.");
        }

        $item = Foodstuff::create([
            'name' => $name,
            'unit' => $this->clean($unit, 30),
            'supplier' => $this->clean($supplier, 255),
            'is_active' => true,
        ]);

        $this->log($by, 'foodstuff_add', "Added {$name} to the foodstuff list", 'foodstuff', $item->id);

        return $item;
    }

    // ------------------------------------------------------------------ shopping lists

    /**
     * @param  list<array{foodstuff_id: int|string, quantity?: int|float|string|null, cost: int|float|string}>  $lines  cost = price of one unit; with no quantity it is the price of the item as a whole
     *
     * @throws \DomainException
     */
    public function createList(User $by, array $lines, ?string $supplier = null, ?string $notes = null): ShoppingList
    {
        $this->assertMay($by, 'create');

        $prepared = $this->prepareLines($lines);

        return DB::transaction(function () use ($by, $prepared, $supplier, $notes) {
            $list = ShoppingList::create([
                'status' => ShoppingList::STATUS_PENDING,
                'supplier' => $this->clean($supplier, 255),
                'notes' => $this->clean($notes, 500),
                'total_cost' => round(array_sum(array_column($prepared, 'line_total')), 2),
                'created_by' => $by->id,
                'created_by_name' => $by->name ?? 'Unknown',
            ]);

            $list->update(['code' => $this->nextListCode()]);
            $list->items()->createMany($prepared);

            $this->log($by, 'shopping_list_create', "Made shopping list {$list->code} (₵".number_format((float) $list->total_cost, 2).')', 'shopping_list', $list->id);

            return $list->load('items');
        });
    }

    /**
     * Re-curate a list that has not been bought yet: replace its items, supplier and notes with a
     * new set of lines, then it is sent to WhatsApp again. The list keeps its code.
     *
     * @param  list<array{foodstuff_id: int|string, quantity?: int|float|string|null, cost: int|float|string}>  $lines
     *
     * @throws \DomainException
     */
    public function updateList(User $by, ShoppingList $list, array $lines, ?string $supplier = null, ?string $notes = null): ShoppingList
    {
        $this->assertMay($by, 'create');

        $prepared = $this->prepareLines($lines);

        return DB::transaction(function () use ($by, $list, $prepared, $supplier, $notes) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();

            if (! $list->isPending()) {
                throw new \DomainException("Only a list that has not been bought can be edited. {$list->code} is {$list->status}.");
            }

            $list->update([
                'supplier' => $this->clean($supplier, 255),
                'notes' => $this->clean($notes, 500),
                'total_cost' => round(array_sum(array_column($prepared, 'line_total')), 2),
            ]);

            $list->items()->delete();
            $list->items()->createMany($prepared);

            $this->log($by, 'shopping_list_edit', "Edited shopping list {$list->code} (₵".number_format((float) $list->total_cost, 2).')', 'shopping_list', $list->id);

            return $list->load('items');
        });
    }

    /**
     * Validate and normalise the lines shared by createList and updateList.
     *
     * @param  list<array{foodstuff_id: int|string, quantity?: int|float|string|null, cost: int|float|string}>  $lines
     * @return list<array{foodstuff_id: int, item_name: string, unit: ?string, quantity: ?float, cost: float, line_total: float}>
     *
     * @throws \DomainException
     */
    private function prepareLines(array $lines): array
    {
        $lines = array_values(array_filter($lines, fn ($line) => ! empty($line['foodstuff_id'])));

        if ($lines === []) {
            throw new \DomainException('Add at least one item to the list.');
        }

        if (count($lines) > self::MAX_LINES) {
            throw new \DomainException('A list can have at most '.self::MAX_LINES.' items.');
        }

        $items = Foodstuff::whereIn('id', collect($lines)->pluck('foodstuff_id')->map(fn ($id) => (int) $id))->get()->keyBy('id');
        $prepared = [];
        $seen = [];

        foreach ($lines as $line) {
            $item = $items->get((int) $line['foodstuff_id']);

            if (! $item || ! $item->is_active) {
                throw new \DomainException('One of the items is no longer on the foodstuff list.');
            }

            if (isset($seen[$item->id])) {
                throw new \DomainException("{$item->name} is on the list twice. Change its quantity instead.");
            }

            $seen[$item->id] = true;

            $quantity = $line['quantity'] ?? null;
            $cost = $line['cost'] ?? null;

            // The quantity is optional: not every item is priced per unit. Blank = the price is for the item as it is.
            $hasQuantity = $quantity !== null && trim((string) $quantity) !== '';

            if ($hasQuantity && (! is_numeric($quantity) || (float) $quantity <= 0 || (float) $quantity > 100000)) {
                throw new \DomainException("Enter a quantity above 0 for {$item->name}, or leave it blank.");
            }

            if (! is_numeric($cost) || (float) $cost <= 0 || (float) $cost > 1000000) {
                throw new \DomainException("Enter the price of {$item->name}.");
            }

            $quantity = $hasQuantity ? round((float) $quantity, 2) : null;
            $cost = round((float) $cost, 2);

            $prepared[] = [
                'foodstuff_id' => $item->id,
                'item_name' => $item->name,
                'unit' => $item->unit,
                'quantity' => $quantity,
                'cost' => $cost,
                'line_total' => $quantity === null ? $cost : round($quantity * $cost, 2),
            ];
        }

        return $prepared;
    }

    /** The list as a WhatsApp message, for the chef to send to whoever is doing the shopping. */
    public function whatsappText(ShoppingList $list): string
    {
        $list->loadMissing('items');

        $lines = ["*Shopping list {$list->code}*", $list->created_at->format('D j M Y'), ''];

        foreach ($list->items as $i => $item) {
            if ($item->quantity === null) {
                $lines[] = ($i + 1).". {$item->item_name} - GH₵ ".number_format((float) $item->line_total, 2);

                continue;
            }

            $quantity = rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');
            $unit = $item->unit ? ' '.$item->unit : '';
            $lines[] = ($i + 1).". {$item->item_name} - {$quantity}{$unit} @ GH₵ ".number_format((float) $item->cost, 2).' = GH₵ '.number_format((float) $item->line_total, 2);
        }

        $lines[] = '';
        $lines[] = '*Total: GH₵ '.number_format((float) $list->total_cost, 2).'*';

        if ($list->supplier) {
            $lines[] = 'Supplier: '.$list->supplier;
        }

        if ($list->notes) {
            $lines[] = 'Note: '.$list->notes;
        }

        $lines[] = 'From: '.($list->created_by_name ?? 'Kitchen');

        return implode("\n", $lines);
    }

    /** No phone number: WhatsApp opens its chat picker so the chef chooses who to send it to. */
    public function whatsappUrl(ShoppingList $list): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->whatsappText($list));
    }

    /**
     * The accountant or an admin confirms the shopping was done and how it was paid; it is recorded
     * as a (paid) expense on the spending dashboard.
     *
     * @throws \DomainException
     */
    public function markBought(User $by, ShoppingList $list, string $paymentMethod): ShoppingList
    {
        if (! $by->hasPermission('create', 'expenses')) {
            throw new \DomainException('Only the accountant or an admin can mark a shopping list as bought.');
        }

        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new \DomainException('Choose how it was paid.');
        }

        return DB::transaction(function () use ($by, $list, $paymentMethod) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();

            if (! $list->isPending()) {
                throw new \DomainException("{$list->code} is already {$list->status}.");
            }

            $items = $list->items()->get();

            $expense = Expense::create([
                'reference' => 'SHP-'.$list->code,
                'amount' => $list->total_cost,
                'description' => "Foodstuff shopping list {$list->code}: {$items->count()} ".($items->count() === 1 ? 'item' : 'items'),
                'incurred_at' => now(),
                'payment_method' => $paymentMethod,
                'paid_by' => $by->name ?? 'Unknown',
                'category' => 'foodstuff',
                'notes' => $list->notes,
                'status' => 'paid',
                'supplier' => $list->supplier,
                'items' => $items->map(fn (ShoppingListItem $item) => [
                    'product_id' => '',
                    'product_name' => $item->item_name,
                    'sku' => '',
                    'barcode' => '',
                    'category' => 'foodstuff',
                    'description' => $item->item_name,
                    'vendor' => $list->supplier,
                    'quantity' => $item->quantity === null ? '' : (float) $item->quantity,
                    'paid_quantity' => $item->quantity === null ? '' : (float) $item->quantity,
                    'free_quantity' => '',
                    'unit_cost' => $item->quantity === null ? '' : (float) $item->cost,
                    'total_cost' => (float) $item->line_total,
                    'supplier_id' => '',
                    'supplier_name' => $list->supplier,
                ])->all(),
                'metadata' => [
                    'shopping_list' => $list->code,
                    'total_items' => $items->count(),
                    'created_by' => ['id' => $list->created_by, 'name' => $list->created_by_name],
                    'bought_by' => ['id' => $by->id, 'name' => $by->name ?? 'Unknown', 'timestamp' => now()->toIso8601String()],
                ],
            ]);

            $list->update([
                'status' => ShoppingList::STATUS_BOUGHT,
                'bought_at' => now(),
                'bought_by' => $by->id,
                'bought_by_name' => $by->name ?? 'Unknown',
                'payment_method' => $paymentMethod,
                'expense_id' => $expense->id,
            ]);

            $this->log($by, 'shopping_list_bought', "Marked {$list->code} as bought (₵".number_format((float) $list->total_cost, 2).", {$paymentMethod})", 'shopping_list', $list->id);

            return $list;
        });
    }

    /**
     * @throws \DomainException
     */
    public function cancelList(User $by, ShoppingList $list): ShoppingList
    {
        $this->assertMay($by, 'delete');

        return DB::transaction(function () use ($by, $list) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();

            if (! $list->isPending()) {
                throw new \DomainException("Only a list that has not been bought can be cancelled. {$list->code} is {$list->status}.");
            }

            $list->update(['status' => ShoppingList::STATUS_CANCELLED, 'cancelled_at' => now()]);

            $this->log($by, 'shopping_list_cancel', "Cancelled shopping list {$list->code}", 'shopping_list', $list->id);

            return $list;
        });
    }

    // ------------------------------------------------------------------ internals

    /**
     * The next list code: SL-YYYYMMDD-NNNN with a per-day sequence, matching the
     * expense reference style (EXP-YYYYMMDD-NNNN). The day restarts the counter at 1.
     */
    private function nextListCode(): string
    {
        $today = now()->format('Ymd');

        $last = ShoppingList::where('code', 'like', "SL-{$today}-%")
            ->latest('id')
            ->first();

        $seq = 1;

        if ($last && preg_match('/SL-\d{8}-(\d{4})/', $last->code, $matches)) {
            $seq = (int) $matches[1] + 1;
        }

        return 'SL-'.$today.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function assertMay(User $by, string $action): void
    {
        if (! $by->hasPermission($action, 'kitchen')) {
            throw new \DomainException('You are not allowed to do this in the kitchen.');
        }
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function log(User $by, string $action, string $description, string $entityType, int $entityId): void
    {
        ActivityLogs::create([
            'user_id' => $by->id,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => ['timestamp' => now()],
        ]);
    }
}
