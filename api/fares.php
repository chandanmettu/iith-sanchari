<?php
// One shared timetable and fare source for the browser and server.
$data=json_decode(file_get_contents(__DIR__.'/../assets/routes.json'),true,512,JSON_THROW_ON_ERROR);
return $data['routes'];
