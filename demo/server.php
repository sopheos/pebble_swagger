<?php

$opts = getopt('h:p:', ['host:', 'port:']);
$host = $opts['h'] ?? $opts['host'] ?? 'localhost';
$port = $opts['p'] ?? $opts['port'] ?? 8080;
$root = __DIR__ . '/';

exec("php -S $host:$port -t $root");
