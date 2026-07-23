<?php
// Server-side fare table — the source of truth. The frontend never gets to say
// what a ticket costs; it only sends a route id, and this file decides the price.
return [
    'patan' => ['name' => 'Patancheru', 'fare' => 30],
    'miya'  => ['name' => 'Miyapur',    'fare' => 100],
];
