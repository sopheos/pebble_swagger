<?php

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');

setlocale(LC_ALL, 'fr_FR.utf8');
setlocale(LC_NUMERIC, 'C');
ini_set('date.timezone', 'Europe/Paris');

require __DIR__ . '/../vendor/autoload.php';

function loadRessources()
{
    $include = function ($path) {
        include_once $path;
    };

    $dir_iterator = new \RecursiveDirectoryIterator(__DIR__ . '/ressources');
    $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $include($file->getPathname());
        }
    }
};

loadRessources(__DIR__ . '/ressources');
