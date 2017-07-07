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
foreach ($opt_get_routes_result['routes'] as $route) {
	if($route['driverSerial'] === $route_id){
    $route_page = $route;
  }
}
//$route_next = [];
foreach ( $route_page['stops'] as $key=>$stop) {

	if(count ($route_page['stops'] ) > $key + 1) {
		$route_page['stops'][$key+1]['prev_stop'] = substr ( $stop['orderNo'] , 0 , 10 );
	}

	if( $key > 0 ) {
		$route_page['stops'][$key-1]['next_stop'] = substr ( $stop['orderNo'] , 0 , 10 );
	}

}

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, [
    'cache' => 'cache',
    'debug' => true
]);

session_start();
$_SESSION['current_route'] = $route_page['stops'];

$template = $twig->loadTemplate('route.html');
echo $template->render(
	[
		'route' => $route_page
	]

);
