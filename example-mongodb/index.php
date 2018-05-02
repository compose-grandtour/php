<?php

// working with /words?
if($_SERVER['REQUEST_URI'] == "/words") {
    require "example.php";
} else {
    // show the web page
    require "templates/index.html";
}
