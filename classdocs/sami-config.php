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
    ->notName('sami.phar')
    ->exclude('resources')
    ->exclude('tests')
    ->exclude('build')
    ->exclude('cache')
    ->exclude('migrations')
    ->exclude('vendor')
    ->exclude('templates')
    ->in(__DIR__.'/../../commerce');

$options = array(
    'title'                => 'Craft Commerce for Craft CMS',
    'build_dir'            => __DIR__.'/build',
    'cache_dir'            => __DIR__.'/cache',
    'default_opened_level' => 2
);

return new Sami($iterator, $options);