<?php

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

// $srcDir = __DIR__ . '/../sumish-framework/src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/../sumish-framework/src');

return new Doctum($iterator, [
    'title' => 'Sumish Framework API',
    'build_dir' => __DIR__ . '/../docs/api',
    'cache_dir' => __DIR__ . '/../docs/cache',
]);
