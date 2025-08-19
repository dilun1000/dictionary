<?php

require_once __DIR__ . '/../vendor/autoload.php';


// Load routes
$router = require_once __DIR__ . '/../routes.php';

// Dispatch the request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router->dispatch($method, $uri);