<?php

use Pebble\Swagger\Parser;

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');

setlocale(LC_ALL, 'fr_FR.utf8');
setlocale(LC_NUMERIC, 'C');
ini_set('date.timezone', 'Europe/Paris');

$repBase = dirname(__DIR__);

require __DIR__ . '/../tests/bootstrap.php';

$doc = Parser::create(__DIR__ . '/../tests/ressources/Controllers')
    ->parser('App/Results')
    ->title("Title")
    ->servers('http://localhost:8080')
    ->version(date('y.n.j'))
    ->run();

header("expires", "Mon, 26 Jul 1990 05:00:00 GMT");
header("last-modified", "" . gmdate("D, d M Y H:i:s") . " GMT");
header("cache-control", "no-store, no-cache, must-revalidate");
header("cache-control", "post-check=0, pre-check=0", false);
header("pragma", "no-cache");
header("content-type", "application/json; charset=UTF-8");
echo json_encode($doc, JSON_PRETTY_PRINT);
