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

$str = $_SERVER['QUERY_STRING'];
parse_str($str, $qsp);
var_dump($qsp);
$order_id = $qsp['order_id'];
$line_items = explode("|", $qsp['line_items']);
var_dump($order_id);
var_dump($line_items);
$line_items_formated = [];
foreach ($line_items as $key => $value) {
	$proto = array('id' => $value);
	array_push($line_items_formated,$proto);
}
$order_response = $client->request(
	'POST',
	"https://{$store}/admin/orders/".$order_id."/fulfillments.json",
	[
		'query' => [
			'access_token' => $access_token
		],
		'form_params' => [
				'fulfillment' => [
					$line_items_formated
				]
    ]
	]

);
$order_result = json_decode($order_response->getBody()->getContents(), true);
die;
