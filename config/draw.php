<?php

return [
    // Orang per kamar. Ubah lewat .env: DRAW_ROOM_SIZE=4
    'room_size' => (int) env('DRAW_ROOM_SIZE', 4),

    // Urutan tampil. 'lead' = wajib 1 per kamar bila jumlahnya cukup.
    'roles' => [
        'pm'  => ['tag' => 'PM',  'css' => 't-pm',  'label' => 'Project Manager',          'lead' => true],
        'sm'  => ['tag' => 'SM',  'css' => 't-sm',  'label' => 'Senior Manager',           'lead' => true],
        'atm' => ['tag' => 'ATM', 'css' => 't-atm', 'label' => 'Asst. Technical Manager', 'lead' => true],
        'prg' => ['tag' => 'PRG', 'css' => 't-prg', 'label' => 'Programmer',               'lead' => false],
        'imp' => ['tag' => 'IMP', 'css' => 't-imp', 'label' => 'Implementor',              'lead' => false],
        'bb'  => ['tag' => 'BB',  'css' => 't-bb',  'label' => 'Backbone',                 'lead' => false],
    ],
];
