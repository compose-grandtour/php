<?php
require('vendor/autoload.php'); // brings the amqplib dependency
require('config.php'); // this sets $connection

$channel = $connection->channel();
$channel->queue_declare('grand_tour', false, false, false, false);

function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}
register_shutdown_function('shutdown', $channel, $connection);

function get_word($message) {
    $data = json_decode($message->body, true);
    echo $data['word'];
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $channel->basic_consume('grand_tour', 'web', false, false, false, false, 'get_word');
}

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['message'])) {
        $word = $put_vars['message'];

        $message = ["word" => $word];
        $amqp_msg = new PhpAmqpLib\Message\AMQPMessage(json_encode($message));
        $channel->basic_publish($amqp_msg, '', 'grand_tour');

        echo $word;
        exit;
    }
}

$channel->wait();

