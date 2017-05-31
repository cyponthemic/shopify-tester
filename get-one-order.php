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
$order_id = $_SERVER['QUERY_STRING'];
//GET /admin/orders/#{id}.json?fields=id,line_items,name,total_price
$order_response = $client->request(
	'GET',
	"https://{$store}/admin/orders/".$order_id.".json",
	[
		'query' => [
			'access_token' => $access_token
		]
	]
);
$order_result = json_decode($order_response->getBody()->getContents(), true);
//GET /admin/orders/#{id}/fulfillments.json
$fulfillment_response = $client->request(
	'GET',
	"https://{$store}/admin/orders/".$order_id."/fulfillments.json",
	[
		'query' => [
			'access_token' => $access_token
		]
	]
);
$fulfillment_result = json_decode($fulfillment_response->getBody()->getContents(), true);
//var_dump($fulfillment_result);
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, [
    'cache' => 'cache',
    'debug' => true
]);
//prepare fulfillment endpoint
$order_id = $order_result['order']['id'];
$line_items = $order_result['order']['line_items'];
$line_items_formated = "";
foreach ($line_items as $item) {
	$line_items_formated = $line_items_formated.$item['id']."|";
}
$line_items_formated = substr($line_items_formated, 0, -1);
$fulfillment_endpoint = "post-fulfillements.php?order_id=".$order_id."&line_items=".$line_items_formated;
$last_fullfilement = $order_result['order']['fulfillments'][0]['id'];
$cancel_endpoint = "cancel-fulfillements.php?order_id=".$order_id."&ff_id=".$last_fullfilement;
$template = $twig->loadTemplate('order.html');
echo $template->render(
	[
		'order' => $order_result['order'],
		'fulfillment_endpoint' => $fulfillment_endpoint,
		'cancel_endpoint' => $cancel_endpoint
	]

);
