# Connecting to MongoDB from a PHP App

For this example, we need both the [MongoDB](https://pecl.php.net/package/mongodb) PHP extension from PECL and the [MongoDB PHP library](https://github.com/mongodb/mongo-php-library) from Composer.  Install the extension and then run `composer install` to get the dependencies required.

## Connection Details

To connect to MongoDB we need two things to be configured in `config.php` (copy `config.template.php` to get started):

* `mongo_url` The full URL of your mongo installation in the form `mongodb:// ...`
* `mongo_certfile` Filename of where you stored your certificate.

**Tip:** if the certificate is base64-encoded, use `base64_decode()` to decode it - the certfile should have first and last lines that have dashes and `BEGIN/END CERTIFICATE` on them.



