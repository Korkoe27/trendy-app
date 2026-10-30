<?php

return [

    /*
    |--------------------------------------------------------------------------
    | End of the pub's working day (the closing hour)
    |--------------------------------------------------------------------------
    |
    | The pub works in sessions that run past midnight (Saturday 15:00 to Sunday 03:00). Anything
    | sold before this time (24-hour HH:MM, app timezone) still belongs to the previous calendar
    | day's session, so a VIP price day set for Saturday runs until Sunday at this time. "00:00"
    | makes the day end at midnight.
    |
    */

    'day_ends_at' => env('PRICING_DAY_ENDS_AT', '03:00'),

    /*
    |--------------------------------------------------------------------------
    | Opening hour
    |--------------------------------------------------------------------------
    |
    | Only used when a VIP price day covers part of a session: the end-of-day stock count weighs
    | the event price by the share of trading hours (opening to closing) the event covers.
    |
    */

    'opens_at' => env('PRICING_OPENS_AT', '15:00'),

];
