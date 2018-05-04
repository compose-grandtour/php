# The Grand Tour - PHP

A set of example applications that will add word/definition pairs to a database running on Compose.  Designed for PHP 7 (but will probably work on outdated PHP versions, e.g. PHP 5).

## Running the Example

To run from the command-line:

* Clone this repo to the root of your web server's document directory
* Navigate to the example-<_db_> directory
* Rename the file `example-<_db_>/config.template.php` to `example-<_db_>/config.php`
* In the file `example-<_db_>/config.php`, change the connection variables to match those of your Compose database instance.
* Start the webserver with `php -S localhost:8080`

The application will be served on http://localhost:8080 and can be opened in a browser.
