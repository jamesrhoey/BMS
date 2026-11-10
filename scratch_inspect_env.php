<?php
$path = __DIR__ . '/.env';
var_dump($path);
var_dump(file_exists($path));
var_dump(is_file($path));
var_dump(is_readable($path));
var_dump(file_get_contents($path));

