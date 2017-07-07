<?php
//TODO:
//https://api.optimoroute.com/v1/get_routes?key=0b548e064f3ea073e55fd61fbdc50690kk6ZqHhOAW4&date=2017-06-28
$opt_get_routes = $client->request(
	'GET',
	"https://api.optimoroute.com/v1/get_routes?key=".'0b548e064f3ea073e55fd61fbdc50690kk6ZqHhOAW4&date='.$today_date->format("Y-m-d")
);

$opt_get_routes_result = json_decode($opt_get_routes->getBody()->getContents(), true);
