<?php

require "vendor/autoload.php";
require "config.php"; // sets the $mongo_* variables

$driver_options = ["cafile" => $mongo_certfile];
$client = new \MongoDB\Client($mongo_url, [], $driver_options);

$coll = $client->compose->words;
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    foreach($coll->find() as $w ) {
        $words[] = $w; 
    }
    echo json_encode($words);
} else if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $item = array(
            "word" => $put_vars['word'], 
            "definition" => $put_vars['definition']
        );
        $coll->insertOne($item);
        echo 'added word ',$put_vars['word'],' and definition ',$put_vars['definition'],' to database';
    }
}

