<?php
//find the week number
//$today = date("Y-m-d");
$offset_date = '4';
$today_date = date("2017-06-02");
$today_minus = date('Y-m-d', strtotime($today_date.' +'.$offset_date.' days'));
$today_date = new DateTime($today_date);

$today_minus = new DateTime($today_minus);
$weekNumber = $today_date->format("W");
$localWeekNumber = $today_minus->format("W");
$local_delivery_week_number = $localWeekNumber - 1;
$currentDay = $today_date->format("D");
$date_min = date('Y-m-d',strtotime($today_minus->format("Y").'W'.($local_delivery_week_number)));
$date_min = date('Y-m-d', strtotime($date_min.' -'.($offset_date-1).' days'));
$date_min_exceptions = date('Y-m-d', strtotime($date_min.' -2 weeks'));
$date_min = new DateTime($date_min);
$date_min_exceptions = new DateTime($date_min_exceptions);
