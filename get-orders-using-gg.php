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
			// /'fulfillment_status' => 'unshipped',
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

//http://maps.googleapis.com/maps/api/directions/json?origin=Adelaide,SA&destination=Adelaide,SA&waypoints=optimize:true|Barossa+Valley,SA|Clare,SA|Connawarra,SA|McLaren+Vale,SA&sensor=false
$items = [];
$tag_selector = 'WK#';
$order_total = 0;
$order_index = 0;
$orders_exceptions_total = 0;
$orders_exceptions_index = 0;

$orders_combined = array();
function addOrderToTotal(&$orders_combined, $order){
	array_push($orders_combined,$order);
}
function addItemToTotal(&$items, $item){
	$proto =  array('id' => $item['variant_id'],'title' => $item['title'],'quantity' => $item['quantity']);
	$variant_id = strval($item['variant_id']);
	if(array_key_exists($variant_id, $items)){
		$items[$item['variant_id']]['quantity'] = $items[$item['variant_id']]['quantity'] +  $item['quantity'];
	}
	else{
		$items[$item['variant_id']] = $proto;
	}
}

foreach ($result['orders'] as $order) {
	//if contain the week selector
	$notcontain = strpos($order['tags'], $tag_selector) === false;
	if($notcontain){
		$order_total = $order_total + 1;
		addOrderToTotal($orders_combined, $order);
		foreach ($order['line_items'] as $item) {
				addItemToTotal($items, $item);
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

$order_per_postcode= [];
foreach ($orders_combined as $key => $order) {
	//var_dump($order);
	$postcode = $order['billing_address']['zip'];
	if(array_key_exists($postcode, $order_per_postcode)){
		$order_per_postcode[$postcode]['waypoints_count']++;
		array_push($order_per_postcode[$postcode]['waypoints'],$order);
	}
	else{
		$order_per_postcode[$postcode] = [];
		$order_per_postcode[$postcode]['waypoints'] = [];
		$order_per_postcode[$postcode]['waypoints_count']= 1;
		array_push($order_per_postcode[$postcode]['waypoints'],$order);
	}

}

// $key => $value
$waypoints = '|';
foreach ($orders_combined as $address => $order) {
		$billing_address = $order['billing_address']['address2'].' '.$order['billing_address']['address1'].' '.$order['billing_address']['city'].' '.$order['billing_address']['zip'];
		$waypoints = $waypoints.urlencode($billing_address).'|';
}

$route = $client->request(
	'GET',
	"https://maps.googleapis.com/maps/api/directions/json",
	[
		'query' => [
			'origin' => '9 brighton street, Richmond 3121, Vic',
			'destination' => '9 brighton street, Richmond 3121, Vic',
			'sensor' =>false,
			'waypoints' => 'optimize:true'.$waypoints,
			'key' => 'AIzaSyBe0zIWhRATTe5GVCYvVs3rmLxzL7J73eY'
		]
	]
);

$route = json_decode($route->getBody()->getContents(), true);
var_dump($route);
die;
//Display error message if addresses are incorrect
foreach ($route['geocoded_waypoints'] as $key => $value) {
	$waypoint_index = intval($key-1);
	$is_origin_waypoint = $waypoint_index>-1;
	//ignore first and last waypoint because they are the start and finish
	if ($value['geocoder_status']!=="OK" &&$is_origin_waypoint && ($key+1) !== count($route['geocoded_waypoints'])) {
		echo $waypoint_index.' '.$value['geocoder_status'].' = '.$orders_combined[$waypoint_index]['billing_address']['address2'].' '.$orders_combined[$waypoint_index]['billing_address']['address1'].' '.$orders_combined[$waypoint_index]['billing_address']['city'].' '.$orders_combined[$waypoint_index]['billing_address']['zip'];
		echo '<hr>';
	}
	if($value['geocoder_status']!=="OK"){
		print_r($value['geocoder_status']);
	}
}
$orders_combined_sorted = array();
//Reorder addresses
foreach ($route['routes'][0]['waypoint_order'] as $new_order => $old_order) {
	$orders_combined_sorted[$new_order]=$orders_combined[$old_order];
}
//Json encoding
$json_result_orders = json_encode($result['orders']);
$json_result_orders_exceptions = json_encode($result_exceptions['orders']);
$json_combine_orders = json_encode($orders_combined);

$fp = fopen('./json/route.json', 'w');
fwrite($fp, json_encode($route));
fclose($fp);

$fp2 = fopen('./json/orders.json', 'w');
fwrite($fp2, json_encode($json_combine_orders));
fclose($fp2);

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, [
    'cache' => 'cache',
    'debug' => true
]);
$template = $twig->loadTemplate('orders.html');
echo $template->render(
	[
		'orders' => $result['orders'],
		'orders_exceptions' => $result_exceptions['orders'],
		'orders_combined' => $orders_combined,
		'orders_combined_sorted' => $orders_combined_sorted,
		'items' => $items,
		'order_total' => $order_total,
		'orders_exceptions_total' => $orders_exceptions_total,
		'$order_per_postcode' => $order_per_postcode
	]

);
