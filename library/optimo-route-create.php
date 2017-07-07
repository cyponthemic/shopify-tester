<?php
$opt_orders_to_create = array();
$opt_address_errors = array();
function optimoRouteCreateOrder (&$date,&$client,&$opt_orders_to_create,&$opt_address_errors, $orders_combined){
	foreach ($orders_combined as $address => $order) {
			$addresse_type =  $order['shipping_address'] ? 'shipping_address' : 'billing_address';
			$shipping_address = $order[$addresse_type]['address1'].' '.$order[$addresse_type]['address2'].' '.$order[$addresse_type]['city'].' '.$order[$addresse_type]['zip'];
			$opt_order = array(
				'operation' => 'CREATE',
				'orderNo' => $order["id"].$order["name"],
				'acceptDuplicateOrderNo' => false,
				'type' => 'D',
				'date' => $date,
				'location' => [
					'address' => $shipping_address
				],
				'duration' => 2,
				'twFrom' => '08:00',
				'twTo' => '18:00'
			);

			$opt_create_order = $client->request(
				'POST',
				"https://api.optimoroute.com/v1/create_order?key=".'0b548e064f3ea073e55fd61fbdc50690kk6ZqHhOAW4',
				['json' => $opt_order]
			);
			$opt_create_order_result = json_decode($opt_create_order->getBody()->getContents(), true);
			if ($opt_create_order_result["success"] !== true ) {
				$opt_create_order_result['shipping_address'] = $shipping_address;
				array_push ($opt_address_errors, $opt_create_order_result);
			}
	}
}
