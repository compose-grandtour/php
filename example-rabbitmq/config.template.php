<?php
// Rename this file config.php and insert the proper value for connection string
// from the Overview page of your Compose dashboard

$connection_string = 'amqps://username:password@server:port/path';

// ** No need to edit below here **

$url_bits = parse_url($connection_string);
$rabbitmq['host'] = $url_bits['host'];
$rabbitmq['port'] = $url_bits['port'];
$rabbitmq['vhost'] = substr($url_bits['path'], 1);
$rabbitmq['username'] = $url_bits['user'];
$rabbitmq['password'] = $url_bits['pass'];

$connection_ssl_options = [
    "server_name_indication" => $rabbitmq['host']
];

$connection = new \PhpAmqpLib\Connection\AMQPSSLConnection(
	$rabbitmq['host'],
	$rabbitmq['port'],
	$rabbitmq['username'],
	$rabbitmq['password'],
    $rabbitmq['vhost'],
    $ssl_options
);


