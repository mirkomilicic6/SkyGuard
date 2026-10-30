<?php

return [

    // Najmanji broj letova i minuta nadzora da bi žarište dobilo preporuku.
    // Ispod toga preporuka je "premalo podataka".
    'min_flights_for_zone' => 5,
    'min_minutes_for_zone' => 120,

    // Ako bi žarište dobilo preporuku "zadržati", a broj detekcija u zadnjem razdoblju
    // porastao je barem za ovaj postotak, preporuka postaje "pojačati".
    'trend_escalation_pct' => 30,

    // Duljina razdoblja (u danima) za usporedbu trenda
    'recent_window_days' => 30,

    // Vremenski blokovi od 4 sata (početak uključen, kraj nije)
    'time_blocks' => [
        ['label' => '00:00–04:00', 'start' => 0,  'end' => 4],
        ['label' => '04:00–08:00', 'start' => 4,  'end' => 8],
        ['label' => '08:00–12:00', 'start' => 8,  'end' => 12],
        ['label' => '12:00–16:00', 'start' => 12, 'end' => 16],
        ['label' => '16:00–20:00', 'start' => 16, 'end' => 20],
        ['label' => '20:00–24:00', 'start' => 20, 'end' => 24],
    ],

];
