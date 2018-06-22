# PHP, PostgreSQL, MySQL and MongoDB: the Compose Grand Tour

Here begins the first PHP leg of the Compose Grand Tour.  The aim of this series is to visit as many databases and langauges as possible, to provide you with working examples to use in your own applications.  _Warning: examples prepared by a Linux user. Mac and Windows users should approach with due caution and provide pull requests as appropriate_

To give you a working example to look at, these examples have a little working web application that you can run yourself if you'd like to.  The web application is the same for all the examples, simply allowing you to input a word and a definition and then displaying the list of words added so far.

The application is a static page with some JavaScript; this is shared between all the Grand Tour examples for this and other languages (see also our NodeJS, Python and Golang tours). It also has support for `GET` and `PUT` on a `/words` endpoint so that we can send and receive JSON to store and retrieve words.

To keep it really clear which parts of the code are for a specific datbase and which are part of the app, we've also built the web application as an example without any external storage.  This version just uses the built in session storage.  Inside the application there's an `index.php` and an `example.php` as well as the files needed to serve the application.  We'll run the PHP application from the command line so `index.php` will serve the static assets as appropriate.  The interesting bit is inside `example.php`, which has the parts that deal with storing and loading the words:

```php

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    header('Content-type: application/json');
    echo json_encode($words);
} else if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $item = array(
            "_id" => (date(DateTime::RFC2822)),
            "word" => $put_vars['word'], 
            "definition" => $put_vars['definition']
        );
        array_push($_SESSION['items'], $item);
    }
}
```

You can see this and all the rest of the code in the project in the (example repository at compose-grandtour/php)[https://github.com/compose-grandtour/php].

You can run this yourself by changing into the `/example` directory and running `php -S localhost:8080 index.php` and then accessing http://localhost:8080 in your browser.  Once you have an idea of what the application does, let's move on and start adding in the databases.

## PostgreSQL and MySQL

PHP has an extension called PHP Data Objects, or PDO, that makes it super easy to connect to relational-type databases.  Even better: it's included with PHP my default.  As a result, the code for connecting PHP to PostgreSQL and to MySQL is only different in one place: the connection string.

In both cases, Compose supplies us a single connection string but our database driver expects the data to be given as separate values of host, port, username, etc.  Handily, PHP's `parse_url()` function makes dealing with this fairly easy.  If you check the contents of `config-template.php` in either `example-postgres` or `example-mysql`, you'll find something like this:

```php
$connection_string = 'postgres://username:password@servername.com:port/compose';

$db = parse_url($connection_string);

$db['dbname'] = substr($db['path'],1);
unset($db['path']); // not needed
```

With the `$db` variable now set up and ready, it's possible to connect to our database.  This is the one place where the code does differ between PostgreSQL and MySQL.

Here's the connection string for PostgreSQL:

```php
$dbh = new PDO("pgsql:host=" . $db['host'] . ";dbname=" . $db['dbname'] . ";port=" . $db['port'], 
    $db['user'], $db['pass'], array(PDO::ATTR_PERSISTENT => true));
```

And for MySQL it's very similar:

```php
$dbh = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'] . ";port=" . $db['port'], 
    $db['user'], $db['pass'], array(PDO::ATTR_PERSISTENT => true));
```

Did you spot the difference?  In fact it's only the initial "pgsql:" or "mysql:" string at the start of the DSN (Data Source Name, the first parameter to the PDO constructor) that needs to change.

### Reading from PostgreSQL/MySQL

In `example-postgres.php` (or `example-mysql.php`), the code checks whether this is a `GET` or `POST` request.  When it's `GET`, then the code fetches the words and definitions that have already been added to the database, formats this as JSON and returns it.

Here's the code that fetches the data, since there are no placeholders we can simply go ahead and use the `query()` method rather than wanting to prepare the statement beforehand:

```php
    $sql = "SELECT * FROM words";
    $stmt = $dbh->query($sql);
    if ($stmt===false){
        echo "<pre>Error executing the query: $sql</pre>";
        die();
    }
```

Once the data has been fetched, the code iterates over the result set and builds the data structure that we will want to return:

```php
    $items = array();
    foreach ($stmt as $row){
        $item = array(
            "word" => $row['name'], 
            "definition" => $row['definition']
        );
        array_push($items, $item);
    }
```

Finally it is sent (with the appropriate headers) to the client:

```php
    header('Content-type: application/json');
    echo json_encode($items);
```

The JSON arrives at the browser and is understood by our client-side JavaScript, and the words list appears on the page.

### Writing to PostgreSQL/MySQL

The other HTTP verb supported by the application is `PUT` which is used when we create data.  When a `PUT` request arrives, the code will parse the body of the incoming request, store the data into the database and then give us a little debug output.

To read the form data that comes in, the PHP function `parse_str()` is used, and the result stored in `$put_vars`.  Beware that the second parameter to `parse_str()` was optional in older versions of PHP.  Without it, the data was simply extracted into your global namespace which is not at all recommended!  The example below uses the `$put_vars` variable to make it clear where the data came from:

```php
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $w = $put_vars['word'];
        $d = $put_vars['definition'];
```

At this point we could inspect our brilliantly-named `$w` and `$d` variables and check that they do indeed contain a word and a definition as we expect.  Then we'll write some SQL to store the data into the database.  The example here uses placeholders and prepares the statement before binding values to it and running the SQL to actually insert the data.  This approach protectes us from SQL injectsion and can also give a performance improvement in some scenarios.

```php
        $sql = 'INSERT INTO words(name, definition) VALUES(:w, :d)';
        $stmt = $dbh->prepare($sql);
        $stmt->bindValue(':w', $w);
        $stmt->bindValue(':d', $d);
        $stmt->execute();
        if ($stmt===false){
            echo "<pre>Error executing the query: $sql</pre>";
            die();
        }
        echo 'added word ',$w,' and definition ',$d,' to database';
```

After the data has been inserted, the final line of the example above has the small debug output that we added to confirm that we received and inserted the data.

Now we've written data to PostgreSQL or MySQL and retrieved it again successfully, let's move on and look at another datbase ....

## MongoDB

Moving away from the relational databases, next up is a NoSQL option: MongoDB.  Support for MongoDB isn't built in to PHP so first there are some dependencies that need to be satisfied.

### Dependencies

MongoDB requires both an extension and a userland library.  There are detailed instructions in the [MongoDB Documentation](https://docs.mongodb.com/ecosystem/drivers/php/) for both parts.

First, check the documentation for details of how to install a PECL extension onto your operating system (the instructions will differ between platforms).  You can check that this has been successful by running `php -m` from the command line to list all the currently-loaded modules in PHP.  If you see "mongodb" in the output, your extension is successfully installed and loaded.

The library is much easier because we can use [Composer](https://getcomposer.org/) to handle this for us.  If you aren't already using Composer then the [setup instructions are here](https://getcomposer.org/doc/00-intro.md) but most PHP platforms will have it available already.  The dependency is configured in the `/example-mongodb/composer.json` file and we can install with a single command:

```
composer install
```

With the dependencies in place, let's move on to the more interesting part: the code!

### Connecting to MongoDB

The connection to MongoDB needs both a connection string and a certificate, and these are configured in the `config.php` file that you create by copying `config.template.php` and editing it to meet your needs.  The connection string is just a copy and paste from your Compose settings page into this file.

The certificate should be added into the project as a file in the `/example-mongodb` directory - mine is called simply `cert`.  This may be provided as a file, or might be part of the connection details for your database in which case it will be [base64 encoded](https://en.wikipedia.org/wiki/Base64).  If it was base64 encoded, decode it and store the results into a file (the PHP function `base64_decode()` may help you here).

Once you have copied `config.template.php` into `config.php`, added your connection string, and given the path to the certificate then we can go ahead and connect.  We can do that with code like this:

```php
$driver_options = ["cafile" => $mongo_certfile];
$client = new \MongoDB\Client($mongo_url, [], $driver_options);
```

Now we're connected, we can fetch the data from MongoDB.

### Reading from MongoDB

To fetch the data from the MongoDB collection, the code needs to specify which collection, then fetch from it.  The results can be returned as JSON.  Here's the code that does that:

```php
    $coll = $client->compose->words;
    foreach($coll->find() as $w ) {
        $words[] = $w; 
    }
    echo json_encode($words);
```

Calling `find()` on the collection without an arguments will find all the records in the collection.  Once we have the data in an array, this can be `json_encode`ed and then returned to the client so the list of words can be displayed in the web application.

### Writing to MongoDB

How about writing the words into the collection so we can fetch them?  The code for that looks something like:

```php
    $coll = $client->compose->words;
    parse_str(file_get_contents("php://input"), $put_vars);
    if (isset($put_vars['word'])) {
        $item = array(
            "word" => $put_vars['word'], 
            "definition" => $put_vars['definition']
        );
        $coll->insertOne($item);
        echo 'added word ',$put_vars['word'],' and definition ',$put_vars['definition'],' to database';
    }
```

Using the `parse_str()` function to read the body of the incoming `PUT` request exactly as we did for the PostgreSQL and MySQL examples, the data is then marshalled into the correct structure for storage.  The `insertOne()` method puts the data into the collection that we defined, and finally the code outputs a little debug message in case it's useful.

## Next Stop

With these code samples, you can go ahead and include these databases from Compose in your own PHP applications.  We'll be back with the next leg of the tour showing around some other PHP and database goodness.






