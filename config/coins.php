<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coins per request
    |--------------------------------------------------------------------------
    |
    | The most coins one request can ask for. Attendants request one coin at a time, so a customer
    | who needs two coins is two requests (each one visible to the bar). Raise it only if the pub
    | decides to hand out coins in batches.
    |
    */

    'max_per_request' => (int) env('COIN_MAX_PER_REQUEST', 1),

    /*
    |--------------------------------------------------------------------------
    | One waiting request per attendant
    |--------------------------------------------------------------------------
    |
    | While an attendant has a coin waiting for the bar, they cannot request another until it is
    | given (or they cancel it). The bar always has at most one open request per attendant.
    |
    */

    'one_waiting_per_attendant' => (bool) env('COIN_ONE_WAITING_PER_ATTENDANT', true),

];
