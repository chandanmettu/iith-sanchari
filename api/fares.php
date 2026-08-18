<?php
// Server-side route table — the source of truth. The frontend never gets to say
// what a ticket costs or which route it names; it only sends a route id, and this
// file decides both. journey_mins is here (not just in assets/app.js) so the
// ticket's printed duration is derived server-side too.
return [
    'patan' => ['name' => 'Patancheru', 'fare' => 30,  'journey_mins' => 60],
    'miya'  => ['name' => 'Miyapur',    'fare' => 100, 'journey_mins' => 100],
];
