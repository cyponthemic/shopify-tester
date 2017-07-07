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

include('./library/set_time_and_date.php');

$response = $client->request(
	'GET',
	"https://{$store}/admin/orders.json",
	[
		'query' => [
			'fulfillment_status' => 'unshipped',
			'limit' => 250,
			'access_token' => $access_token,
			'created_at_min' =>  $date_min->format("Y-m-d")
		]
	]
);
$result = json_decode($response->getBody()->getContents(), true);

$response_exceptions = $client->request(
	'GET',
	"https://{$store}/admin/orders.json",
	[
		'query' => [
			'fulfillment_status' => 'unshipped',
			'limit' => 250,
			'access_token' => $access_token,
			'created_at_min' =>  $date_min_exceptions->format("Y-m-d")
		]
	]
);

$result_exceptions = json_decode($response_exceptions->getBody()->getContents(), true);

$items = [];
$items_brunswick = [];
$items_thornbury = [];

$tag_selector = 'WK#';
$order_total = 0;
$order_index = 0;
$orders_exceptions_total = 0;
$orders_exceptions_index = 0;

$order_detail_deliveries = [];
$order_detail_brunswick = [];
$order_detail_thornbury = [];

$orders_combined = array();
function addOrderToTotal(&$orders_combined, $order){
	array_push($orders_combined,$order);
}
function addItemToTotal(&$orders,&$order,&$items, $item){
	$proto =  array('id' => $item['title'],'title' => $item['title'],'quantity' => $item['quantity']);
	$proto_detail= array(
		'name' => $order['billing_address']['first_name'].' '.$order['billing_address']['last_name'],
		'title' => $item['title'],
		'note' => $order['note'],
		'quantity' => $item['quantity']
	);
	$variant_id = strval($item['title']);
	array_push($orders,$proto_detail);

	if(array_key_exists($variant_id, $items)){
		$items[$item['title']]['quantity'] = $items[$item['title']]['quantity'] +  $item['quantity'];
	}
	else{
		$items[$item['title']] = $proto;
	}

}

function checkPickUp($zip) {
	$pickup = '';

	$thornbury_post_code = ['3070','3071','3072','3073','3078','3084'];
  $brunswick_post_code = ['3055','3056','3057'];

	if( in_array ( $zip , $thornbury_post_code) ):
	$pickup = 'thornbury';
	elseif( in_array ( $zip , $brunswick_post_code) ):
	$pickup = 'brunswick';
	else:
	$pickup = 'none';
	endif;

	return $pickup;
}

foreach ($result['orders'] as $order) {
	//if contain the week selector
	$notcontain = strpos($order['tags'], $tag_selector) === false;

	if($notcontain){
		$order_total = $order_total + 1;
		addOrderToTotal($orders_combined, $order);

		foreach ($order['line_items'] as $item) {
				$pickup =  checkPickUp($order['shipping_address']['zip']);
				switch ($pickup) {
					case 'thornbury':
						addItemToTotal($order_detail_thornbury,$order,$items_thornbury, $item);
						break;
					case 'brunswick':
						addItemToTotal($order_detail_brunswick,$order,$items_brunswick, $item);
						break;
					default:
						addItemToTotal($order_detail_deliveries,$order,$items, $item);
						break;
				}

		}
	}
	else {
		unset($result['orders'][$order_index]);
	}
	$order_index = $order_index + 1;
}

foreach ($result_exceptions['orders'] as $order) {
	//if contain the week selector of this week
	$contain = strpos($order['tags'], $tag_selector.$local_delivery_week_number) !== false;
	if($contain){
		$orders_exceptions_total = $orders_exceptions_total + 1;
		addOrderToTotal($orders_combined, $order);
		foreach ($order['line_items'] as $item) {
				addItemToTotal($items, $item);
		}
	}
	else {
	 	unset($result_exceptions['orders'][$orders_exceptions_index]);
	}
	$orders_exceptions_index = $orders_exceptions_index + 1;
}

include('./library/optimo-route-create.php');
//optimoRouteCreateOrder ($today_date->format("Y-m-d"),$client,$opt_orders_to_create,$opt_address_errors,$orders_combined);


$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, [
    'cache' => 'cache',
    'debug' => true
]);
$template = $twig->loadTemplate('orders.html');
echo $template->render(
	[
		'opt_address_errors' => $opt_address_errors,
		'orders' => $result['orders'],
		'orders_exceptions' => $result_exceptions['orders'],
		'orders_combined' => $orders_combined,
		'items' => $items,
		'items_thornbury' => $items_thornbury,
		'items_brunswick' => $items_brunswick,
		'order_total' => $order_total,
		'orders_exceptions_total' => $orders_exceptions_total,
		'order_detail_deliveries' => $order_detail_deliveries,
		'order_detail_brunswick' => $order_detail_brunswick,
		'order_detail_thornbury' => $order_detail_thornbury

	]

);
