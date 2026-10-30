<?php

return [

    /*
    |--------------------------------------------------------------------------
    | End of the pub's business day
    |--------------------------------------------------------------------------
    |
    | A VIP price day runs until the pub closes, which can be after midnight. Anything sold before
    | this time (24-hour HH:MM, app timezone) still belongs to the previous calendar day, so an
    | event on Saturday covers Saturday 05:00 to Sunday 05:00 with the default. Set it to the
    | pub's real closing hour; "00:00" makes the day end at midnight.
    |
    */

    'day_ends_at' => env('PRICING_DAY_ENDS_AT', '05:00'),

];
