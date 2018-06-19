<?php
require('vendor/autoload.php'); // brings the amqplib dependency
require('config.php'); // this sets $connection

$channel = $connection->channel();
$channel->queue_declare('grand_tour');

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
        $word = $put_vars['word'];
        $defn = $put_vars['definition'];

        $message = ["word" => $word, "type" => $defn];
        $amqp_msg = new PhpAmqpLib\Message\AMQPMessage(json_encode($message));
        $channel->basic_publish($amqp_msg, '', 'grand_tour');

        echo 'added word ',$word,' and definition ',$defn,' to queue';
    }
}
