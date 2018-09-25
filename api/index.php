<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE');
header('Content-Type: application/json');

if (filter_input(INPUT_SERVER, "REQUEST_METHOD") === 'OPTIONS') {
  header('Access-Control-Allow-Headers: Content-Type');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__."/../core/bootstrap.php";