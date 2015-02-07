<?php

/*
 * Sami Documentation config
 *
*/

use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('resources')
    ->exclude('tests')
    ->exclude('migrations')
    ->exclude('vendor')
    ->in(__DIR__.'/../plugins/market');

$options = array(
    'title'                => 'Market Plugin for Craft CMS',
    'build_dir'            => __DIR__.'/build',
    'cache_dir'            => __DIR__.'/cache',
    'default_opened_level' => 2
);

return new Sami($iterator, $options);