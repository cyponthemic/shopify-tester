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
include('./library/optimo-route-get-routes.php');
$route_page =  array();
$route_id = $_SERVER['QUERY_STRING'];

function addItemToTotal(&$orders,&$order,&$items, $item){
	$proto =  array('id' => $item['variant_id'],'title' => $item['title'],'quantity' => $item['quantity']);
	$proto_detail= array(
		'name' => $order['billing_address']['first_name'].' '.$order['billing_address']['last_name'],
		'title' => $item['title'],
		'note' => $order['note'],
		'quantity' => $item['quantity']
	);
	$variant_id = strval($item['variant_id']);
	array_push($orders,$proto_detail);

	if(array_key_exists($variant_id, $items)){
		$items[$item['variant_id']]['quantity'] = $items[$item['variant_id']]['quantity'] +  $item['quantity'];
	}

	else{
		$items[$item['variant_id']] = $proto;
	}
}

foreach ($opt_get_routes_result['routes'] as $route) {
	if($route['driverSerial'] === $route_id){
    $route_page = $route;
  }
}
//$route_next = [];
$item_detail = [];
$order_detail_driver = [];
foreach ( $route_page['stops'] as $key=>$stop) {

	if(count ($route_page['stops'] ) > $key + 1) {
		$route_page['stops'][$key+1]['prev_stop'] = substr ( $stop['orderNo'] , 0 , 10 );
	}

	if( $key > 0 ) {
		$route_page['stops'][$key-1]['next_stop'] = substr ( $stop['orderNo'] , 0 , 10 );
	}

	$order_response = $client->request(
		'GET',
		"https://{$store}/admin/orders/".substr ( $stop['orderNo'] , 0 , 10 ).".json",
		[
			'query' => [
				'access_token' => $access_token
			]
		]
	);
	$order_result = json_decode($order_response->getBody()->getContents(), true);

	foreach ($order_result['order']['line_items'] as $item) {
					addItemToTotal($order_detail_driver,$order_result['order'],$item_detail, $item);
	}

}

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, [
    'cache' => 'cache',
    'debug' => true
]);

session_start();
$_SESSION['current_route'] = $route_page['stops'];

$template = $twig->loadTemplate('route-items.html');
echo $template->render(
	[
		'route' => $route_page,
		'order_detail_driver' => $order_detail_driver,
		'item_detail' => $item_detail
	]

);
