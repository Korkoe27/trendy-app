<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriesFactory> */
    use HasFactory;
    protected $fillable=[
        "name",
        'pricing_model'
    ];

    protected $table = 'categories';

    /** The one category whose products have a stock process (counted, restocked, low-stock alerts). */
    public const STOCKED_NAME = 'drinks';

    /** Games products (snooker) work on coins that come back every day instead of stock. */
    public const GAMES_NAME = 'games';

    /**
     * Ids of the categories whose products are stocked: only "drinks" (any capitalisation).
     * Food, games, snacks and services are never counted or restocked; food is one "Food total"
     * at end of day and snooker coins are tracked through coin requests.
     *
     * @return array<int, int>
     */
    public static function stockedIds(): array
    {
        return static::idsNamed(self::STOCKED_NAME);
    }

    /** @return array<int, int> */
    public static function gamesIds(): array
    {
        return static::idsNamed(self::GAMES_NAME);
    }

    /** @return array<int, int> */
    private static function idsNamed(string $name): array
    {
        return static::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isStocked(): bool
    {
        return strtolower(trim((string) $this->name)) === self::STOCKED_NAME;
    }

    public function isGames(): bool
    {
        return strtolower(trim((string) $this->name)) === self::GAMES_NAME;
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
