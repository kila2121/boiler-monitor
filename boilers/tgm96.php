<?php
return [
    'id'         => 'tgm96',
    'name'       => 'ТГМ-96',
    'load_range' => [200, 480],
    'parameters' => ['steam_pressure','steam_temperature','flue_gas_temp','gas_flow','excess_air'],
    'reference'  => [
        'steam_pressure'    => 140,
        'steam_temperature' => 560,
        'flue_gas_temp'     => 120,
        'gas_flow'          => 45,
        'excess_air'        => 1.15,
        'max_deviation'     => [
            'steam_pressure'    => 3,
            'steam_temperature' => 10,
            'flue_gas_temp'     => 5,
            'excess_air'        => 0.05
        ]
    ]
];