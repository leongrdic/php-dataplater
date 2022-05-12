<?php

require_once 'vendor/autoload.php';

$dp = new Le\Dataplater\Dataplater(
    template: "<!doctype html><html lang=en><body><div data-dp=name></div></body></html>",
);

echo $dp->render([
    'name' => 'John',
    'age' => 30,
]);