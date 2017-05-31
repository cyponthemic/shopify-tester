<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$db = new Mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASS'), getenv('MYSQL_DB'));

$store = 'localorganicdelivery-com-au.myshopify.com';
$select = $db->query("SELECT access_token FROM installs WHERE store = '$store'");
$user = $select->fetch_object();
$access_token = $user->access_token;

$client = new Client();

$response = $client->request(
	'GET',
	"https://{$store}/admin/customers.json",
	[
		'query' => [
			'country' => 'Australia',
			'access_token' => $access_token
		]
	]
);
$result = json_decode($response->getBody()->getContents(), true);

//https://maps.googleapis.com/maps/api/directions/json?origin=Adelaide,SA&destination=Adelaide,SA&waypoints=optimize:true|Barossa+Valley,SA|Clare,SA|Connawarra,SA|McLaren+Vale,SA&key=API_KEY

//var_dump($result['orders']);
//die;

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, [
    'cache' => 'cache',
    'debug' => true
]);

$template = $twig->loadTemplate('customers.html');
echo $template->render(['customers' => $result['customers']]);
