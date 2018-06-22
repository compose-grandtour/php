# PHP, ElasticSearch, Redis and RabbitMQ: the Compose Grand Tour

Welcome back to the next PHP leg of the Compose Grand Tour.  This series aims to show you around a simple example web application written in different languages (see the Python, NodeJS and Golang tours) and connecting to many of the wonderful database platforms available from compose.  In Part 1 of the PHP journey, there were visits to PostgreSQL, MySQL and MongoDB.  In this article our scheduled destinations will be ElasticSearch, Redis and RabbitMQ.  Check your seatbelts are fastened and your hand luggage stowed - we're off!

All the examples are based on the same simple web application, which we saw in part 1 - with a small adaptation for RabbitMQ because it really isn't a database in the traditional sense.  Let's start our journey with a short trip to ElasticSearch.

## ElasticSearch

ElasticSearch's tag line might be "you know, for search" but it's also a perfectly functional document database in its own right.  To work with ElasticSearch from PHP, we'll use the [elasticsearch](https://github.com/elastic/elasticsearch-php) library.  This is specified in `package.json` already so we can install it with:

```
composer install
```

Now the dependencies are in place, we can go ahead and connect to ElasticSearch.

### Connecting to ElasticSearch

To configure the example application, you will need to:

* Copy `config.template.php` to `config.php`
* Set your own ElasticSearch conection string in `config.php`

Then the code to connect can be found in `index.php`:

```php
$hosts = [$connection_string];

$clientBuilder = new \Elasticsearch\ClientBuilder();
$es = $clientBuilder->setHosts($hosts)->build();
```

Before we start merrily reading from and writing to an index, it's good to check if it exists and create it if necessary.  Here's the code that does that:

```php
$params = ['index' => 'grand_tour'];
try {
    $response = $es->indices()->getSettings($params);
} catch (Exception $e) {
    $response = $es->indices()->create($params);
}
```

With the database connected and the index in existence, we can go ahead and start working with data.

### Reading from ElasticSearch

As the databases name implies, this is all about search.  To get data from the database, we "search" for the results.  In this case, the code will search for all results in the "grand tour" index, before formatting them and then returning them as JSON:

```php
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
header('Content-type: application/json');
echo json_encode($items);
```

ElasticSearch returns the data and the code then iterates over the results, putting the data into the expected structure before it is turned into JSON and returned.  This is lovely, but we probably need to _write_ some data before this code is useful.

### Writing to ElasticSearch

When data is `PUT` to the `/words` endpoint, the code will first read in the body of the request, parse the data that we need, and then write it to the "grand_tour¬ index.  Here's the code that does that:

```php
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
```

The final echo line simply offers us some feedback that the code did what we hoped it would.

With ElasticSearch under our belt, let's proceed.  Our next stop will be Redis.

## Redis

Adapting the example code to Redis is reasonably straight forward, especially after having already seen so many database examples before!  As always, we'll begin by connecting to the data store.  For redis, all we need is to copy the `config.template.php` file to `config.php` and set the connection string to point to our own Redis.

This example uses the predis library, and this dependency is already in `composer.json` so to get set up we only need to run the one command:

```
composer install
```

Here's the code from `example-redis.php` that makes the connection (spoiler: there's not a lot of it!)

```php
$redis = new Predis\Client($connection_string);
```

Once we have the client set up, we can go ahead and start building the rest of the example web app.

### Reading from Redis

Redis is a very lightweight datastore, supporting various datatypes.  This application uses the _hash_ datatype to store a series of namespaced keys (e.g. `word:hello`), containing `word` and `definition` fields in the values.

To fetch the data, we retrieve all keys that are called something like `name:`, then iterate over the data and return it as JSON:

```php
    header('Content-type: application/json');

    $list = $redis->keys("word:*");
    // handle the empty list
    if (count($list) == 0) {
        return json_encode([]);
    }
    $words = [];
    foreach ($list as $item) {
        $row = $redis->hscan($item, 0);
        $words[] = $row[1];
    }

    echo json_encode($words);
```

That's allowing us to fetch data, what about actually storing it in the first place?

### Writing to Redis

By the same token, when a `PUT` request arrives and we parse the data, we'll write a _hash_ data type record with the name and fields according to the incoming data.  Here's the code that does that:

```php
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $w = $put_vars['word'];
        $d = $put_vars['definition'];

        $redis->hmset('word:' . $w, ['word' => $w, 'definition' => $d]); 

        echo 'added word ',$w,' and definition ',$d,' to database';
    }
```

Since the key uses the word as part of it, this approach doesn't allow us to enter duplicate words - a repeated word with a new definition would overwrite the previous record for this word.  This is a common use case for Redis, which is typically used as an auxilliary data store or cache rather than a primary database in its own right.

Moving right along our planned route, let's take in the views at our next destination: RabbitMQ.

## RabbitMQ

RabbitMQ, as the name suggests, isn't strictly a datastore - it's a queue.  As a result, we've adapted the example a bit.  Adding a word creates a `PUT` request to `/messages` and adds the word to the queue (this version doesn't have definitions).  To retrieve the data, make a `GET` request to `/messages` and the next message from the queue will be returned.  Unless there isn't one, in which case you'll get "no message".

Now we're clear on what we're building, let's begin.  This example uses the [php-amqplib](https://packagist.org/packages/php-amqplib/php-amqplib) library, which is already specified in `composer.json` so it can be installed with Composer:

```
composer install
```

To connect to RabbitMQ, we need to format the connection string provided by Compose and configure the SSL aspect of the connection.  The code that does this work is in `config.php` (copied from `config.template.php` and with your xconnection string added), and looks like this:

```php
$url_bits = parse_url($connection_string);
$rabbitmq['host'] = $url_bits['host'];
$rabbitmq['port'] = $url_bits['port'];
$rabbitmq['vhost'] = substr($url_bits['path'], 1);
$rabbitmq['username'] = $url_bits['user'];
$rabbitmq['password'] = $url_bits['pass'];

$ssl_options = [
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
```

Once we're connected, there are some operations that are common to all our operations:
* creating a channel
* declaring the `grand_tour` queue

We'll do these every time, with code like this:

```php
$channel = $connection->channel();
$channel->queue_declare('grand_tour', false, false, false, false);
```

At this point, we're ready to start working with the queue.


### Reading from RabbitMQ

When reading from RabbitMQ, the code will wait for items to appear on the queue and then call a callback function for each item.  In our rather special case, waiting doesn't make sense so this example sets a short timeout of 3 seconds - and if it times out, catches the resulting exception.  Also since we only want to process exactly one item, we take care to have the program exit afterwards.

This code sample shows the `get_word()` callback to be used for each queue item, as well as the command to consume from the queue and the `try/catch` to set up the waiting for a new queue item and handle the timeout:

```php
function get_word($message) {
    $data = json_decode($message->body, true);
    echo $data['word'];
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    // only process one
    exit;
}

$channel->basic_consume('grand_tour', 'web', false, false, false, false, 'get_word');

try {
    $channel->wait(null, false, 3);
} catch(\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
    echo "no messages";
}
```

Next, let's put some words into the queue so that they can be consumed...

### Writing to RabbitMQ

When we add a word, first we can parse the incoming body of the request with `parse_str()`.  This data is added to an `AMQPMessage` object and then published to the channel.  The code snippet from `example-rabbitmq.php` in the GitHub project looks like this:

```php
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['message'])) {
        $word = $put_vars['message'];

        $message = ["word" => $word];
        $amqp_msg = new PhpAmqpLib\Message\AMQPMessage(json_encode($message));
        $channel->basic_publish($amqp_msg, '', 'grand_tour');
    }
```

Add as many words to the queue, then when clicking the "Receive" button the words will be returned in the order in which they were added.  A bit of a variation on the other example applications but a good way to illustrate the basics of working with RabbitMQ.

## Safe Travels!

The examples on our Grand Tour of PHP and Compose databases has shown you some code samples that wcan form the basis of your own applications.  We hope you had a pleasant flight and enjoy your stay, or have a safe onward journey.
