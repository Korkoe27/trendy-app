<?php

namespace Database\Factories;

use App\Models\DailySales;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditSales>
 */
class CreditSalesFactory extends Factory
{
    /** A small pool of regulars so the same people owe repeatedly, like a real bar. */
    public const CUSTOMERS = [
        ['Kofi Mensah', '0244123401'],
        ['Ama Serwaa', '0200123402'],
        ['Yaw Boateng', '0541123403'],
        ['Akosua Owusu', '0276123404'],
        ['Kwame Asante', '0555123405'],
        ['Efua Addo', '0244123406'],
        ['Nana Yaw', '0201123407'],
        ['Abena Darko', '0543123408'],
    ];

    public function definition(): array
    {
        [$name, $phone] = self::CUSTOMERS[array_rand(self::CUSTOMERS)];
        $units = fake()->numberBetween(1, 6);
        $price = fake()->randomFloat(2, 3, 20);

        return [
            'daily_sale_id' => DailySales::factory(),
            'product_id' => fn (array $attrs) => DailySales::find($attrs['daily_sale_id'])->product_id,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'units_credited' => $units,
            'credit_amount' => round($units * $price, 2),
            'status' => 'unpaid',
            'credit_date' => now()->subDay()->toDateString(),
            'payment_date' => null,
            'amount_paid' => 0,
            'notes' => null,
            'metadata' => json_encode(['demo' => true]),
        ];
    }

    /** Credit taken out against a closed day's daily_sales row. */
    public function forSale(DailySales $sale, ?array $customer = null): static
    {
        return $this->state(function () use ($sale, $customer) {
            [$name, $phone] = $customer ?? self::CUSTOMERS[array_rand(self::CUSTOMERS)];

            return [
                'daily_sale_id' => $sale->id,
                'product_id' => $sale->product_id,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'units_credited' => $sale->credit_units,
                'credit_amount' => $sale->credit_amount,
                'credit_date' => $sale->sales_date,
                'created_at' => $sale->created_at,
                'updated_at' => $sale->created_at,
            ];
        });
    }
}
