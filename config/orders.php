<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Order cache store
    |--------------------------------------------------------------------------
    |
    | The live layer of the order system (per-order locks and the cached open-order board) runs
    | on this cache store. Point it at "redis" in production. Leave it empty to use the app's
    | default cache store. Orders and payments themselves always live in the database, so
    | nothing here is ever the only copy of anything.
    |
    */

    'cache_store' => env('ORDERS_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Board refresh
    |--------------------------------------------------------------------------
    */

    'board_cache_seconds' => (int) env('ORDERS_BOARD_CACHE_SECONDS', 5),

    'lock_seconds' => (int) env('ORDERS_LOCK_SECONDS', 10),

];
