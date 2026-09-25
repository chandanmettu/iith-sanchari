<?php
require __DIR__.'/hosted.php';
$in=input();limit_requests('recover',40,60);$row=authenticated_booking($in);
respond(booking_response(reconcile($row)));
