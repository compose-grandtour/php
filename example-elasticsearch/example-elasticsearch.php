<?php
require('config.php'); // this sets the $connection_string 
require('vendor/autoload.php');

$hosts = [$connection_string];

$clientBuilder = new \Elasticsearch\ClientBuilder();
$es = $clientBuilder->setHosts($hosts)->build();

// does this index exist? If not, create
$params = ['index' => 'grand_tour'];
try {
    $response = $es->indices()->getSettings($params);
} catch (Exception $e) {
    $response = $es->indices()->create($params);
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    header('Content-type: application/json');

    $search_params = ['index' => 'grand_tour'];
    $results = $es->search($params);

    $items = array();
    foreach ($results['hits']['hits'] as $row){
        $item = array(
            "word" => $row['_source']['word'], 
            "definition" => $row['_source']['definition']
        );
        array_push($items, $item);
    }
    echo json_encode($items);
}

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $word = $put_vars['word'];
        $defn = $put_vars['definition'];

        $data = [
            'index' => 'grand_tour',
            'type' => 'words',
            'body' => ["word" => $word, "definition" => $defn]
        ];

        $response = $es->index($data);
        echo 'added word ',$word,' and definition ',$defn,' to database';
    }
}
