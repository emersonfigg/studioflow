<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Booking
    |--------------------------------------------------------------------------
    |
    | Minimum time, in minutes, between the current time and the first slot
    | offered in the public booking flow.
    |
    */

    'booking_min_lead_time_minutes' => env('BOOKING_MIN_LEAD_TIME_MINUTES', 10),

];
