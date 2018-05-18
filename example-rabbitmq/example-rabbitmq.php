<?php
require('vendor/autoload.php'); // brings the amqplib dependency
require('config.php'); // this sets $connection_string

var_dump($connection_string);
$url_pieces = parse_url($connection_string);

$ssl_options = [];
$connection = new \PhpAmqpLib\Connection\AMQPSSLConnection (
    $url_pieces['host'],
    $url_pieces['port'],
    $url_pieces['user'],
    $url_pieces['pass'],
    $url_pieces['path'],
    $ssl_options);

$channel = $connection->channel();
$channel->queue_declare('grand_tour');
$message = ["word" => "hai", "type" => "greeting"];
$amqp_msg = new PhpAmqpLib\Message\AMQPMessage(json_encode($message));
$channel->basic_publish($amqp_msg, '', 'grand_tour');
exit;


if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    header('Content-type: application/json');
    $list = $redis->keys("word:*");
    if (count($list) == 0) {
        return json_encode([]);
    }
    $words = [];
    foreach ($list as $item) {
        $row = $redis->hscan($item, 0);
        $words[] = $row[1];
    }

    echo json_encode($words);
}

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $w = $put_vars['word'];
        $d = $put_vars['definition'];

        $redis->hmset('word:' . $w, ['word' => $w, 'definition' => $d]); 

        echo 'added word ',$w,' and definition ',$d,' to database';
    }
}
