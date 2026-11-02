<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;


    protected $fillable =[
        "name",
        "sku",
        "category_id",
        "barcode",
        "stock_limit",
        // "cost_price",    
        "selling_price",
        "units_per_box",
        "is_vip",
        "daily_coins",
    ];

    protected $table = 'products';

    protected function casts(): array
    {
        return ['is_vip' => 'boolean', 'sold_out_at' => 'datetime'];
    }


    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    /** Only drinks have a stock process. */
    public function scopeStocked($query)
    {
        return $query->whereIn('category_id', Categories::stockedIds() ?: [0]);
    }

    /** Games products (snooker) have coins per day instead of stock. */
    public function scopeGames($query)
    {
        return $query->whereIn('category_id', Categories::gamesIds() ?: [0]);
    }

    public function tracksStock(): bool
    {
        return (bool) $this->category?->isStocked();
    }

    /**
     * A drink whose latest stock figure is zero can't be ordered. Non-drinks have no stock process, so they never run out.
     * Load `category` and `currentStock` first when checking many products.
     */
    public function isOutOfStock(): bool
    {
        return $this->tracksStock() && ($this->currentStock?->total_units ?? 0) <= 0;
    }

    /** Switched off by the bar during service (see OrderService::setAvailability). */
    public function isSoldOut(): bool
    {
        return $this->sold_out_at !== null;
    }

    /** Cannot be ordered: no stock left (drinks) or switched off by the bar. */
    public function isUnavailable(): bool
    {
        return $this->isSoldOut() || $this->isOutOfStock();
    }

    public function isGame(): bool
    {
        return (bool) $this->category?->isGames();
    }

    public function stocks(){
        return $this->hasMany(Stock::class)->orderBy('created_at', 'desc');
    }

    public function currentStock()
{
    return $this->hasOne(Stock::class)->latestOfMany('created_at');
}

    public function dailySales()
    {
        return $this->hasMany(DailySales::class);
    }

    public function dailySalesSummary(){
        return $this->hasMany(DailySalesSummary::class);
    }

    public function coinSales(){

        //add condition to check if product is a game product like snooker
        return $this->hasMany(CoinSales::class);
    }

    public function foodSales(){
        //add condition to check if product is a food product like pizza
        return $this->hasMany(FoodSales::class);
    }
}
