<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum data required before a zone gets a real recommendation
    |--------------------------------------------------------------------------
    | Below either threshold, RecommendationService returns "insufficient_data"
    | instead of guessing from too little history.
    */
    'min_flights_for_zone' => 5,
    'min_minutes_for_zone' => 120,

    /*
    |--------------------------------------------------------------------------
    | Trend escalation
    |--------------------------------------------------------------------------
    | If a zone would otherwise be "maintain" but its detection count over the
    | recent window has grown by at least this percentage compared to the
    | previous window of the same length, it's bumped up to "increase".
    */
    'trend_escalation_pct' => 30,

    /*
    |--------------------------------------------------------------------------
    | Recent / previous window length (days) used for the trend comparison
    |--------------------------------------------------------------------------
    */
    'recent_window_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Time-of-day blocks used to report each zone's most active period
    |--------------------------------------------------------------------------
    | Hour ranges are [start, end) in 24h time.
    */
    'time_blocks' => [
        ['label' => '00:00–04:00', 'start' => 0,  'end' => 4],
        ['label' => '04:00–08:00', 'start' => 4,  'end' => 8],
        ['label' => '08:00–12:00', 'start' => 8,  'end' => 12],
        ['label' => '12:00–16:00', 'start' => 12, 'end' => 16],
        ['label' => '16:00–20:00', 'start' => 16, 'end' => 20],
        ['label' => '20:00–24:00', 'start' => 20, 'end' => 24],
    ],

];
