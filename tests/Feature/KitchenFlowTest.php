<?php

namespace Tests\Feature;

use App\Livewire\Pages\Inventory;
use App\Livewire\Pages\Kitchen;
use App\Livewire\Pages\Orders;
use App\Models\Expense;
use App\Models\FoodOrder;
use App\Models\FoodSales;
use App\Models\Foodstuff;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\FoodSalesService;
use App\Services\KitchenService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Food orders reach the kitchen, the chef marks them ready and the waiter picks them up; the chef's
 * shopping lists end up on the spending dashboard; the day's food revenue fills the stock count.
 */
class KitchenFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $jollof;

    private int $rice;

    private OrderService $orders;

    private User $waiter;

    private User $bartender;

    private User $chef;

    private User $manager;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-14 20:00:00'); // Saturday evening, inside the session
        config(['pricing.day_ends_at' => '03:00']);
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $food = DB::table('categories')->insertGetId(['name' => 'Food', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = $this->product($drinks, 'Club Beer', 30);
        DB::table('stocks')->insert(['product_id' => $this->beer, 'total_units' => 100, 'cost_price' => 20, 'cost_margin' => 10, 'total_cost' => 2000, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        $this->jollof = $this->product($food, 'Jollof Rice', 75);
        $this->rice = $this->product($food, 'Fried Rice', 60);

        $this->orders = app(OrderService::class);

        $this->waiter = $this->userWith(['orders' => ['view', 'create']], 'Garnet');
        $this->bartender = $this->userWith(['orders' => ['view', 'modify']], 'Akua');
        $this->chef = $this->userWith(['kitchen' => ['view', 'create', 'modify']], 'Kofi Chef');
        $this->manager = $this->userWith(['orders' => ['view', 'create', 'modify'], 'order_debts' => ['view', 'modify'], 'kitchen' => ['view', 'create', 'modify', 'delete'], 'expenses' => ['view', 'create'], 'inventory' => ['view', 'create', 'modify']], 'Boss');
        $this->accountant = $this->userWith(['kitchen' => ['view'], 'expenses' => ['view', 'create']], 'Esi Accountant');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function product(int $categoryId, string $name, float $price): int
    {
        return DB::table('products')->insertGetId(['name' => $name, 'category_id' => $categoryId, 'selling_price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, list<string>> $grants module => actions */
    private function userWith(array $grants, string $name): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ($grants as $module => $actions) {
            $moduleId = DB::table('modules')->where('name', $module)->value('id')
                ?? DB::table('modules')->insertGetId(['name' => $module, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('permissions')->insert([
                'role_id' => $roleId, 'module_id' => $moduleId,
                'can_view' => in_array('view', $actions), 'can_create' => in_array('create', $actions),
                'can_modify' => in_array('modify', $actions), 'can_delete' => in_array('delete', $actions),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return User::forceCreate([
            'name' => $name, 'username' => strtolower(str_replace(' ', '', $name)).uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ]);
    }

    private function place(array $items, ?User $by = null, string $phone = '0241234567'): Order
    {
        return $this->orders->place($by ?? $this->manager, 'Kwame Mensah', $phone, $items);
    }

    // ------------------------------------------------------------------ food orders

    public function test_food_in_an_order_is_saved_as_a_food_order_for_the_kitchen(): void
    {
        $order = $this->place([$this->beer => 2, $this->jollof => 2, $this->rice => 1]);

        $this->assertSame([OrderItem::STATION_BAR, OrderItem::STATION_KITCHEN, OrderItem::STATION_KITCHEN], $order->items->pluck('station')->all());

        $food = FoodOrder::sole();
        $this->assertSame('FO-'.str_pad((string) $food->id, 4, '0', STR_PAD_LEFT), $food->code);
        $this->assertSame($order->id, $food->order_id);
        $this->assertSame(FoodOrder::STATUS_PENDING, $food->status);
        $this->assertSame('210.00', $food->total, '2 x 75 + 60, the drinks are not part of it');
        $this->assertSame(3, $food->items_count);
        $this->assertSame('2026-11-14', $food->business_date->toDateString());
        $this->assertSame('Boss', $food->taken_by_name);
        $this->assertSame(2, $food->items()->count());
    }

    public function test_a_drinks_only_order_has_no_food_order(): void
    {
        $this->place([$this->beer => 2]);

        $this->assertSame(0, FoodOrder::count());
    }

    public function test_the_bar_and_the_kitchen_each_mark_their_own_part_and_the_order_is_ready_when_both_are(): void
    {
        $order = $this->place([$this->beer => 1, $this->jollof => 1]);

        $this->orders->markReady($order, $this->bartender);
        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING, $order->status, 'the food is still with the kitchen');
        $this->assertSame(OrderItem::STATUS_READY, $order->items->firstWhere('station', 'bar')->status);
        $this->assertSame(OrderItem::STATUS_PENDING, $order->items->firstWhere('station', 'kitchen')->fresh()->status);
        $this->assertSame(FoodOrder::STATUS_PENDING, FoodOrder::sole()->status);

        $this->orders->markReady($order, $this->chef, 'kitchen');

        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);
        $food = FoodOrder::sole();
        $this->assertSame(FoodOrder::STATUS_READY, $food->status);
        $this->assertSame($this->chef->id, $food->ready_by);
        $this->assertNotNull($food->ready_at);
        $this->assertSame($this->chef->id, $order->items()->where('station', 'kitchen')->value('ready_by'));
    }

    public function test_the_kitchen_can_finish_first_and_the_waiter_picks_the_food_up_with_the_order(): void
    {
        $order = $this->place([$this->beer => 1, $this->jollof => 1], $this->waiter);

        $this->orders->markReady($order, $this->chef, 'kitchen');
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status, 'the drinks are not ready');
        $this->assertSame(FoodOrder::STATUS_READY, FoodOrder::sole()->status);

        $this->orders->markReady($order, $this->bartender);
        $this->orders->serve($order, $this->waiter);

        $food = FoodOrder::sole();
        $this->assertSame(FoodOrder::STATUS_PICKED_UP, $food->status);
        $this->assertSame($this->waiter->id, $food->picked_up_by);
        $this->assertNotNull($food->picked_up_at);
    }

    public function test_the_bartender_cannot_mark_food_ready_and_the_kitchen_cannot_mark_what_is_not_theirs(): void
    {
        $order = $this->place([$this->beer => 1, $this->jollof => 1]);

        try {
            $this->orders->markReady($order, $this->bartender, 'kitchen');
            $this->fail('The bartender must not mark food ready.');
        } catch (\DomainException $e) {
            $this->assertSame('Only the kitchen can mark food as ready.', $e->getMessage());
        }

        $this->assertSame(OrderItem::STATUS_PENDING, $order->items()->where('station', 'kitchen')->value('status'));

        $drinksOnly = $this->place([$this->beer => 1], null, '0245550000');

        $this->expectExceptionMessage('There is no food waiting on this order.');
        $this->orders->markReady($drinksOnly, $this->chef, 'kitchen');
    }

    public function test_a_food_only_order_gives_the_bar_nothing_to_mark(): void
    {
        $order = $this->place([$this->jollof => 1]);

        $this->expectExceptionMessage('There are no drinks waiting on this order.');
        $this->orders->markReady($order, $this->bartender);
    }

    public function test_more_food_joins_the_pending_food_order_but_a_new_round_after_pickup_starts_a_new_one(): void
    {
        $order = $this->place([$this->jollof => 1], $this->waiter);

        $this->orders->addItems($order, $this->waiter, [$this->rice => 1, $this->jollof => 1]);
        $food = FoodOrder::sole();
        $this->assertSame('210.00', $food->total, 'both jollofs merged, plus the rice');
        $this->assertSame(3, $food->items_count);

        $this->orders->markReady($order, $this->chef, 'kitchen');
        $this->orders->serve($order, $this->waiter);

        $this->orders->addItems($order, $this->waiter, [$this->rice => 2]);

        $this->assertSame(2, FoodOrder::count());
        $this->assertSame([FoodOrder::STATUS_PICKED_UP, FoodOrder::STATUS_PENDING], FoodOrder::orderBy('id')->pluck('status')->all());
        $this->assertSame('120.00', FoodOrder::orderBy('id')->get()->last()->total);
    }

    public function test_changing_or_removing_food_before_it_is_cooked_updates_the_food_order(): void
    {
        $order = $this->place([$this->beer => 1, $this->jollof => 2, $this->rice => 1]);
        $jollof = $order->items->firstWhere('product_id', $this->jollof);
        $rice = $order->items->firstWhere('product_id', $this->rice);

        $this->orders->setItemQuantity($order, $this->manager, $jollof->id, 1);
        $this->assertSame('135.00', FoodOrder::sole()->total);

        $this->orders->setItemQuantity($order, $this->bartender, $rice->id, 0); // the bartender can edit a pending order
        $this->assertSame('75.00', FoodOrder::sole()->total);
        $this->assertSame(1, FoodOrder::sole()->items_count);

        $this->orders->setItemQuantity($order, $this->manager, $jollof->id, 0);
        $this->assertSame(0, FoodOrder::count(), 'no food left, so the kitchen has nothing to cook');
    }

    public function test_cancelling_the_order_cancels_its_food(): void
    {
        $order = $this->place([$this->jollof => 1]);

        $this->orders->cancel($order, $this->manager, 'Left');

        $food = FoodOrder::sole();
        $this->assertSame(FoodOrder::STATUS_CANCELLED, $food->status);
        $this->assertNotNull($food->cancelled_at);
    }

    public function test_the_order_card_tells_the_bartender_the_food_is_with_the_kitchen(): void
    {
        $order = $this->place([$this->beer => 1, $this->jollof => 1], $this->waiter);

        $this->actingAs($this->bartender);
        Livewire::test(Orders::class)
            ->assertSee('Drinks Ready')
            ->assertSeeHtml('data-testid="waiting-kitchen"')
            ->assertSeeHtml('data-testid="kitchen-item-tag"')
            ->call('markReady', $order->id)
            ->assertDontSee('Drinks Ready')
            ->assertSeeHtml('data-testid="waiting-kitchen"');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    // ------------------------------------------------------------------ the kitchen dashboard

    public function test_the_kitchen_dashboard_needs_the_kitchen_right(): void
    {
        $this->actingAs($this->waiter)->get(route('kitchen'))->assertForbidden();
        $this->actingAs($this->chef)->get(route('kitchen'))->assertOk()->assertSee('Kitchen');
    }

    public function test_the_chef_sees_food_orders_and_marks_them_ready_from_the_dashboard(): void
    {
        $order = $this->place([$this->beer => 1, $this->jollof => 2], $this->waiter);
        $food = FoodOrder::sole();
        $this->actingAs($this->chef);

        Livewire::test(Kitchen::class)
            ->assertSeeHtml('data-testid="food-order-pending"')
            ->assertSee('Kwame Mensah')
            ->assertSee('2x Jollof Rice')
            ->assertDontSee('Club Beer', false) // drinks are the bar's
            ->call('markReady', $food->id)
            ->assertSeeHtml('data-testid="food-order-ready"')
            ->assertDontSeeHtml('data-testid="food-order-pending"');

        $this->assertSame(FoodOrder::STATUS_READY, $food->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status, 'the drinks are still with the bar');
    }

    public function test_someone_who_can_only_view_the_kitchen_cannot_mark_food_ready(): void
    {
        $this->place([$this->jollof => 1]);
        $this->actingAs($this->accountant);

        Livewire::test(Kitchen::class)
            ->assertDontSee('Food Ready')
            ->call('markReady', FoodOrder::sole()->id)
            ->assertForbidden();
    }

    public function test_picked_up_food_moves_to_the_picked_up_column(): void
    {
        $order = $this->place([$this->jollof => 1], $this->waiter);
        $this->orders->markReady($order, $this->chef, 'kitchen');
        $this->orders->serve($order, $this->waiter);
        $this->actingAs($this->chef);

        Livewire::test(Kitchen::class)
            ->assertSee('Picked up tonight (1)')
            ->assertDontSeeHtml('data-testid="food-order-ready"');
    }

    // ------------------------------------------------------------------ foodstuff

    public function test_the_chef_adds_foodstuff_and_duplicates_are_refused(): void
    {
        $this->actingAs($this->chef);

        Livewire::test(Kitchen::class)
            ->call('openFoodstuff')
            ->set('foodstuffName', 'Tomatoes')->set('foodstuffUnit', 'kg')->set('foodstuffSupplier', 'Makola')
            ->call('addFoodstuff')
            ->assertHasNoErrors()
            ->assertSet('foodstuffName', '')
            ->assertSee('Tomatoes')
            ->set('foodstuffName', ' tomatoes ')
            ->call('addFoodstuff')
            ->assertHasErrors('foodstuffName');

        $item = Foodstuff::sole();
        $this->assertSame('Tomatoes', $item->name);
        $this->assertSame('kg', $item->unit);
        $this->assertSame('Makola', $item->supplier);
    }

    public function test_only_people_with_the_create_right_can_add_foodstuff(): void
    {
        $this->actingAs($this->accountant);

        Livewire::test(Kitchen::class)->set('foodstuffName', 'Pepper')->call('addFoodstuff')->assertForbidden();

        $this->assertSame(0, Foodstuff::count());
    }

    // ------------------------------------------------------------------ shopping lists

    private function stuff(string $name, ?string $unit = 'kg'): Foodstuff
    {
        return Foodstuff::create(['name' => $name, 'unit' => $unit, 'is_active' => true]);
    }

    public function test_the_chef_makes_a_shopping_list_and_whatsapp_opens_with_it(): void
    {
        $tomatoes = $this->stuff('Tomatoes');
        $noodles = $this->stuff('Noodles', 'carton');
        $this->actingAs($this->chef);

        Livewire::test(Kitchen::class)
            ->call('openList')
            ->set('lines', [
                ['foodstuff_id' => (string) $tomatoes->id, 'quantity' => '2', 'cost' => '15'],
                ['foodstuff_id' => (string) $noodles->id, 'quantity' => '1.5', 'cost' => '40'],
            ])
            ->set('listSupplier', 'Makola Market')
            ->set('listNotes', 'Fresh only')
            ->call('submitList')
            ->assertHasNoErrors()
            ->assertSet('showListModal', false)
            ->assertSee('SL-')
            ->assertDispatched('open-whatsapp', function (string $event, array $params) {
                return str_starts_with($params['url'], 'https://wa.me/?text=')
                    && str_contains(urldecode($params['url']), 'Tomatoes - 2 kg @ GH₵ 15.00 = GH₵ 30.00')
                    && str_contains(urldecode($params['url']), 'Total: GH₵ 90.00');
            });

        $list = ShoppingList::sole();
        $this->assertSame(ShoppingList::STATUS_PENDING, $list->status);
        $this->assertSame('90.00', $list->total_cost, '2 x 15 + 1.5 x 40');
        $this->assertSame('Makola Market', $list->supplier);
        $this->assertSame('Kofi Chef', $list->created_by_name);
        $this->assertSame(2, $list->items()->count());
        $this->assertSame('SL-20261114-0001', $list->code);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'shopping_list_create']);
    }

    public function test_shopping_list_codes_include_the_date_and_a_daily_sequence(): void
    {
        $kitchen = app(KitchenService::class);
        $tomatoes = $this->stuff('Tomatoes');
        $onions = $this->stuff('Onions');

        $first = $kitchen->createList($this->chef, [['foodstuff_id' => $tomatoes->id, 'quantity' => 1, 'cost' => 5]]);
        $second = $kitchen->createList($this->chef, [['foodstuff_id' => $onions->id, 'quantity' => 1, 'cost' => 5]]);

        $this->assertSame('SL-20261114-0001', $first->code);
        $this->assertSame('SL-20261114-0002', $second->code);
    }

    public function test_the_shopping_list_sequence_restarts_each_day(): void
    {
        $kitchen = app(KitchenService::class);
        $kitchen->createList($this->chef, [['foodstuff_id' => $this->stuff('Tomatoes')->id, 'quantity' => 1, 'cost' => 5]]);

        Carbon::setTestNow('2026-11-15 09:00:00');
        $next = $kitchen->createList($this->chef, [['foodstuff_id' => $this->stuff('Onions')->id, 'quantity' => 1, 'cost' => 5]]);

        $this->assertSame('SL-20261115-0001', $next->code);
    }

    public function test_the_whatsapp_message_lists_every_item_the_total_and_who_made_it(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [
            ['foodstuff_id' => $this->stuff('Pepper', 'bag')->id, 'quantity' => 3, 'cost' => 12.5],
        ], 'Kantamanto', 'Hot ones');

        $text = app(KitchenService::class)->whatsappText($list);

        $this->assertStringContainsString("*Shopping list {$list->code}*", $text);
        $this->assertStringContainsString('1. Pepper - 3 bag @ GH₵ 12.50 = GH₵ 37.50', $text);
        $this->assertStringContainsString('*Total: GH₵ 37.50*', $text);
        $this->assertStringContainsString('Supplier: Kantamanto', $text);
        $this->assertStringContainsString('Note: Hot ones', $text);
        $this->assertStringContainsString('From: Kofi Chef', $text);
    }

    public function test_a_list_can_be_sent_again_from_the_dashboard(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [['foodstuff_id' => $this->stuff('Onions')->id, 'quantity' => 1, 'cost' => 10]]);
        $this->actingAs($this->accountant);

        Livewire::test(Kitchen::class)
            ->assertSeeHtml('data-testid="shopping-list"')
            ->call('sendWhatsapp', $list->id)
            ->assertDispatched('open-whatsapp');
    }

    public function test_a_shopping_list_needs_valid_lines(): void
    {
        $tomatoes = $this->stuff('Tomatoes');
        $kitchen = app(KitchenService::class);

        $cases = [
            'no lines' => [[], 'Add at least one item'],
            'no price' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => 1, 'cost' => '']], 'Enter the price of Tomatoes'],
            'zero price' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => 1, 'cost' => 0]], 'Enter the price of Tomatoes'],
            'a zero quantity' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => 0, 'cost' => 5]], 'Enter a quantity above 0 for Tomatoes, or leave it blank'],
            'a negative quantity' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => -2, 'cost' => 5]], 'Enter a quantity above 0'],
            'a text quantity' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => 'lots', 'cost' => 5]], 'Enter a quantity above 0'],
            'text price' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => 1, 'cost' => 'abc']], 'Enter the price of Tomatoes'],
            'the same item twice' => [[['foodstuff_id' => $tomatoes->id, 'quantity' => 1, 'cost' => 5], ['foodstuff_id' => $tomatoes->id, 'quantity' => 2, 'cost' => 5]], 'on the list twice'],
            'unknown item' => [[['foodstuff_id' => 99999, 'quantity' => 1, 'cost' => 5]], 'no longer on the foodstuff list'],
        ];

        foreach ($cases as $label => [$lines, $message]) {
            try {
                $kitchen->createList($this->chef, $lines);
                $this->fail("Expected a refusal: {$label}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $label);
            }
        }

        $this->assertSame(0, ShoppingList::count());

        $this->actingAs($this->chef);
        Livewire::test(Kitchen::class)->call('openList')->call('submitList')->assertHasErrors('lines');
    }

    public function test_the_quantity_is_optional_and_the_price_is_then_for_the_item_as_a_whole(): void
    {
        $kitchen = app(KitchenService::class);
        $list = $kitchen->createList($this->chef, [
            ['foodstuff_id' => $this->stuff('Tomatoes')->id, 'quantity' => '2', 'cost' => '15'],   // 2 x 15
            ['foodstuff_id' => $this->stuff('Spices')->id, 'quantity' => '', 'cost' => '40'],      // one price for the lot
            ['foodstuff_id' => $this->stuff('Gas', 'cylinder')->id, 'cost' => '120'],              // no quantity key at all
        ]);

        $this->assertSame('190.00', $list->total_cost, '30 + 40 + 120');
        $this->assertNull($list->items[1]->quantity);
        $this->assertSame('40.00', $list->items[1]->line_total);
        $this->assertNull($list->items[2]->quantity);

        $text = $kitchen->whatsappText($list);
        $this->assertStringContainsString('1. Tomatoes - 2 kg @ GH₵ 15.00 = GH₵ 30.00', $text);
        $this->assertStringContainsString('2. Spices - GH₵ 40.00', $text);
        $this->assertStringContainsString('3. Gas - GH₵ 120.00', $text);
        $this->assertStringNotContainsString('Spices - 1', $text);
        $this->assertStringContainsString('*Total: GH₵ 190.00*', $text);

        $kitchen->markBought($this->accountant, $list, 'cash');
        $items = Expense::sole()->items;
        $this->assertSame('', $items[1]['quantity']);
        $this->assertEquals(40, $items[1]['total_cost']);
        $this->assertEquals(2, $items[0]['quantity']);
    }

    public function test_the_form_starts_with_a_blank_quantity_and_submits_without_one(): void
    {
        $stuff = $this->stuff('Spices');
        $this->actingAs($this->chef);

        Livewire::test(Kitchen::class)
            ->call('openList')
            ->assertSet('lines.0.quantity', '')
            ->assertSee('Qty (optional)')
            ->set('lines.0.foodstuff_id', (string) $stuff->id)
            ->set('lines.0.cost', '40')
            ->assertSee('Total: GH₵ 40.00')
            ->call('submitList')
            ->assertHasNoErrors()
            ->assertSee('Spices');

        $this->assertNull(ShoppingList::sole()->items->first()->quantity);
    }

    public function test_only_people_with_the_create_right_can_make_lists(): void
    {
        $tomatoes = $this->stuff('Tomatoes');

        $this->expectException(\DomainException::class);
        app(KitchenService::class)->createList($this->accountant, [['foodstuff_id' => $tomatoes->id, 'quantity' => 1, 'cost' => 5]]);
    }

    public function test_the_accountant_marks_a_list_bought_and_it_lands_on_the_spending_dashboard(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [
            ['foodstuff_id' => $this->stuff('Tomatoes')->id, 'quantity' => 2, 'cost' => 15],
            ['foodstuff_id' => $this->stuff('Oil', 'litre')->id, 'quantity' => 1, 'cost' => 60],
        ], 'Makola');
        $this->actingAs($this->accountant);

        Livewire::test(Kitchen::class)
            ->call('openBought', $list->id)
            ->assertSeeHtml('data-testid="bought-modal"')
            ->set('paymentMethod', 'momo')
            ->call('markBought')
            ->assertHasNoErrors()
            ->assertSet('showBoughtModal', false)
            ->assertSee('marked as bought');

        $list->refresh();
        $this->assertSame(ShoppingList::STATUS_BOUGHT, $list->status);
        $this->assertSame('Esi Accountant', $list->bought_by_name);
        $this->assertSame('momo', $list->payment_method);

        $expense = Expense::sole();
        $this->assertSame($expense->id, $list->expense_id);
        $this->assertSame('SHP-'.$list->code, $expense->reference);
        $this->assertSame('90.00', $expense->amount);
        $this->assertSame('foodstuff', $expense->category);
        $this->assertSame('paid', $expense->status);
        $this->assertSame('momo', $expense->payment_method);
        $this->assertSame('Makola', $expense->supplier);
        $this->assertSame('Esi Accountant', $expense->paid_by);
        $this->assertCount(2, $expense->items);
        $this->assertSame('Tomatoes', $expense->items[0]['product_name']);
        $this->assertEquals(30, $expense->items[0]['total_cost']);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'shopping_list_bought', 'user_id' => $this->accountant->id]);
    }

    public function test_the_chef_cannot_mark_a_list_bought_and_a_list_is_bought_once(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [['foodstuff_id' => $this->stuff('Onions')->id, 'quantity' => 1, 'cost' => 10]]);

        $this->actingAs($this->chef);
        Livewire::test(Kitchen::class)->assertDontSeeHtml('data-testid="mark-bought"')->call('openBought', $list->id)->assertForbidden();

        try {
            app(KitchenService::class)->markBought($this->chef, $list, 'cash');
            $this->fail('The chef has no expenses right.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('accountant or an admin', $e->getMessage());
        }

        $kitchen = app(KitchenService::class);
        $kitchen->markBought($this->accountant, $list, 'cash');

        try {
            $kitchen->markBought($this->manager, $list->fresh(), 'cash');
            $this->fail('A list can only be bought once.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already bought', $e->getMessage());
        }

        $this->assertSame(1, Expense::count());
    }

    public function test_an_unknown_payment_method_is_refused(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [['foodstuff_id' => $this->stuff('Onions')->id, 'quantity' => 1, 'cost' => 10]]);

        $this->expectExceptionMessage('Choose how it was paid.');
        app(KitchenService::class)->markBought($this->accountant, $list, 'bitcoin');
    }

    public function test_a_pending_list_can_be_cancelled_by_those_with_the_delete_right_only(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [['foodstuff_id' => $this->stuff('Onions')->id, 'quantity' => 1, 'cost' => 10]]);

        $this->actingAs($this->chef);
        Livewire::test(Kitchen::class)->call('cancelList', $list->id)->assertForbidden();

        $this->actingAs($this->manager);
        Livewire::test(Kitchen::class)->call('cancelList', $list->id);

        $this->assertSame(ShoppingList::STATUS_CANCELLED, $list->fresh()->status);

        try {
            app(KitchenService::class)->markBought($this->accountant, $list->fresh(), 'cash');
            $this->fail('A cancelled list cannot be bought.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }

        $this->assertSame(0, Expense::count());
    }

    public function test_a_pending_list_can_be_edited_and_whatsapp_opens_with_the_updated_list(): void
    {
        $tomatoes = $this->stuff('Tomatoes');
        $onions = $this->stuff('Onions');
        $list = app(KitchenService::class)->createList($this->chef, [
            ['foodstuff_id' => $tomatoes->id, 'quantity' => 2, 'cost' => 15],
        ], 'Makola');
        $this->actingAs($this->chef);

        Livewire::test(Kitchen::class)
            ->call('editList', $list->id)
            ->assertSee('Edit shopping list')
            ->assertSet('listSupplier', 'Makola')
            ->assertSet('lines.0.quantity', '2')
            ->assertSet('lines.0.cost', '15')
            ->set('lines', [
                ['foodstuff_id' => (string) $tomatoes->id, 'quantity' => '3', 'cost' => '15'],
                ['foodstuff_id' => (string) $onions->id, 'quantity' => '1', 'cost' => '10'],
            ])
            ->set('listSupplier', 'Kantamanto')
            ->call('submitList')
            ->assertHasNoErrors()
            ->assertSet('showListModal', false)
            ->assertDispatched('open-whatsapp', function (string $event, array $params) {
                $text = urldecode($params['url']);

                return str_starts_with($params['url'], 'https://wa.me/?text=')
                    && str_contains($text, 'Tomatoes - 3 kg @ GH₵ 15.00 = GH₵ 45.00')
                    && str_contains($text, 'Onions - 1 kg @ GH₵ 10.00 = GH₵ 10.00')
                    && str_contains($text, 'Total: GH₵ 55.00')
                    && str_contains($text, 'Supplier: Kantamanto');
            });

        $list->refresh();
        $this->assertSame(ShoppingList::STATUS_PENDING, $list->status);
        $this->assertSame('55.00', $list->total_cost);
        $this->assertSame('Kantamanto', $list->supplier);
        $this->assertSame(2, $list->items()->count());
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'shopping_list_edit']);
    }

    public function test_a_bought_or_cancelled_list_cannot_be_edited(): void
    {
        $kitchen = app(KitchenService::class);

        $bought = $kitchen->createList($this->chef, [['foodstuff_id' => $this->stuff('Tomatoes')->id, 'quantity' => 1, 'cost' => 5]]);
        $kitchen->markBought($this->accountant, $bought, 'cash');

        try {
            $kitchen->updateList($this->chef, $bought->fresh(), [['foodstuff_id' => $this->stuff('Onions')->id, 'quantity' => 1, 'cost' => 5]]);
            $this->fail('A bought list must not be edited.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('bought', $e->getMessage());
        }

        $cancelled = $kitchen->createList($this->chef, [['foodstuff_id' => $this->stuff('Rice')->id, 'quantity' => 1, 'cost' => 5]]);
        $kitchen->cancelList($this->manager, $cancelled);

        try {
            $kitchen->updateList($this->chef, $cancelled->fresh(), [['foodstuff_id' => $this->stuff('Yam')->id, 'quantity' => 1, 'cost' => 5]]);
            $this->fail('A cancelled list must not be edited.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }
    }

    public function test_only_people_with_the_create_right_can_edit_lists(): void
    {
        $list = app(KitchenService::class)->createList($this->chef, [['foodstuff_id' => $this->stuff('Tomatoes')->id, 'quantity' => 1, 'cost' => 5]]);

        $this->actingAs($this->accountant);
        Livewire::test(Kitchen::class)
            ->assertDontSeeHtml('data-testid="edit-list"')
            ->call('editList', $list->id)
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ food sales

    public function test_the_days_food_revenue_counts_every_live_food_order_and_skips_cancelled_ones(): void
    {
        $this->place([$this->jollof => 2, $this->rice => 1], null, '0241111111');                // 210
        $cancelled = $this->place([$this->jollof => 5], null, '0242222222');                       // cancelled
        $this->orders->cancel($cancelled, $this->manager, 'Left');
        $picked = $this->place([$this->jollof => 1, $this->beer => 4], null, '0243333333');        // 75 food, drinks excluded
        $this->orders->markReady($picked, $this->chef, 'kitchen');
        $this->orders->markReady($picked, $this->bartender);
        $this->orders->serve($picked, $this->manager);

        $day = app(FoodSalesService::class)->forDate('2026-11-14');

        $this->assertSame(2, $day['orders']);
        $this->assertSame(4, $day['items']);
        $this->assertSame(285.0, $day['revenue']);
        $this->assertSame(1, $day['cancelled']);
        $this->assertSame(1, $day['unfinished'], 'one food order was never cooked');
        $this->assertSame([['name' => 'Jollof Rice', 'quantity' => 3, 'amount' => 225.0], ['name' => 'Fried Rice', 'quantity' => 1, 'amount' => 60.0]], $day['breakdown']);

        $this->assertSame(0.0, app(FoodSalesService::class)->forDate('2026-11-13')['revenue']);
    }

    private function takeDay(): void
    {
        Carbon::setTestNow('2026-11-15 16:00:00');
        $this->actingAs($this->manager);
    }

    private function submitDay(?string $typedFood = null)
    {
        $page = Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-11-14')
            ->set('cashAmount', '700')
            ->set('momoAmount', '0')
            ->set('hubtelAmount', '0')
            ->set("productStocks.{$this->beer}.closing_units", '90'); // 10 beers at 30 = 300

        if ($typedFood !== null) {
            $page->set('foodTotal', $typedFood); // a crafted value must be ignored
        }

        return $page->call('submitInventory')->assertHasNoErrors();
    }

    public function test_the_stock_count_shows_the_food_revenue_instead_of_an_input(): void
    {
        $this->place([$this->jollof => 2, $this->rice => 1]); // 210, never cooked
        $this->takeDay();

        Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('salesDate', '2026-11-14')
            ->assertSeeHtml('data-testid="food-revenue"')
            ->assertSee('GH₵ 210.00')
            ->assertSeeHtml('data-testid="food-unfinished"')
            ->assertDontSeeHtml('wire:model.defer="foodTotal"')
            ->set('salesDate', '2026-11-13')
            ->assertSee('GH₵ 0.00')
            ->assertDontSeeHtml('data-testid="food-unfinished"');
    }

    public function test_submitting_the_count_saves_the_food_revenue_and_the_days_food_summary(): void
    {
        $order = $this->place([$this->jollof => 2, $this->rice => 1], $this->waiter); // 210
        $this->orders->markReady($order, $this->chef, 'kitchen');
        $this->orders->serve($order, $this->waiter);
        $this->takeDay();

        $this->submitDay('5');

        $summary = DB::table('daily_sales_summaries')->first();
        $this->assertSame(210.0, (float) $summary->food_total, 'filled in automatically; the typed value is ignored');

        $food = FoodSales::sole();
        $this->assertSame('2026-11-14', $food->sales_date->toDateString());
        $this->assertSame(1, $food->orders_count);
        $this->assertSame(3, $food->items_count);
        $this->assertSame('210.00', $food->revenue);
        $this->assertSame(0, $food->cancelled_count);
        $this->assertSame('Jollof Rice', $food->breakdown[0]['name']);
        $this->assertSame($this->manager->id, $food->recorded_by);
    }

    public function test_a_day_without_food_orders_saves_zero_food(): void
    {
        $this->takeDay();
        $this->submitDay();

        $this->assertSame(0.0, (float) DB::table('daily_sales_summaries')->value('food_total'));
        $this->assertSame('0.00', FoodSales::sole()->revenue);
    }

    public function test_editing_the_day_refreshes_the_food_summary(): void
    {
        $this->place([$this->jollof => 1]);
        $this->takeDay();
        $this->submitDay();
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        Carbon::setTestNow('2026-11-14 22:00:00');
        $this->place([$this->rice => 1], null, '0245550000'); // another food order for the same session
        Carbon::setTestNow('2026-11-15 16:00:00');

        Livewire::test(Inventory::class)
            ->call('openEditModal', $summaryId)
            ->set("productStocks.{$this->beer}.closing_units", '90')
            ->call('updateInventory')
            ->assertHasNoErrors();

        $this->assertSame(135.0, (float) DB::table('daily_sales_summaries')->value('food_total'));
        $this->assertSame('135.00', FoodSales::sole()->revenue, 'one summary per day, refreshed');
    }

    public function test_correcting_an_old_day_from_before_food_orders_keeps_its_typed_food_amount(): void
    {
        $this->takeDay();
        $this->submitDay();
        // A day recorded by hand before food orders existed: a typed amount and no food summary.
        DB::table('daily_sales_summaries')->update(['food_total' => 480]);
        FoodSales::query()->delete();
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        Livewire::test(Inventory::class)
            ->call('openEditModal', $summaryId)
            ->assertSee('Recorded earlier, before food orders')
            ->assertSee('GH₵ 480.00')
            ->set("productStocks.{$this->beer}.closing_units", '90')
            ->call('updateInventory')
            ->assertHasNoErrors();

        $this->assertSame(480.0, (float) DB::table('daily_sales_summaries')->value('food_total'));
        $this->assertSame(0, FoodSales::count(), 'no summary is invented for a day with no food orders');
    }

    public function test_the_old_food_sales_table_is_rebuilt_as_the_daily_summary(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('food_sales', ['sales_date', 'orders_count', 'items_count', 'revenue', 'cancelled_count', 'breakdown']));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('food_sales', 'quantity_sold'));
    }

    public function test_the_kitchen_migration_grants_rights_and_creates_the_chef_role_once(): void
    {
        $migration = require base_path('database/migrations/2026_11_12_100200_add_kitchen_module_roles_and_permissions.php');
        $migration->up();
        $migration->up();

        $moduleId = DB::table('modules')->where('name', 'kitchen')->value('id');
        $this->assertSame(1, DB::table('modules')->where('name', 'kitchen')->count());
        $this->assertSame(1, DB::table('roles')->where('name', 'Chef')->count());

        $rights = (array) DB::table('permissions')
            ->where('module_id', $moduleId)
            ->where('role_id', DB::table('roles')->where('name', 'Chef')->value('id'))
            ->first(['can_view', 'can_create', 'can_modify', 'can_delete']);

        $this->assertEquals(['can_view' => 1, 'can_create' => 1, 'can_modify' => 1, 'can_delete' => 0], $rights);
    }
}
