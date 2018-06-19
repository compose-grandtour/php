<?php

// working with /words?
if($_SERVER['REQUEST_URI'] == "/message") {
    require "example-rabbitmq.php";
} else {
    // show the web page
    require "templates/index.html";
}
