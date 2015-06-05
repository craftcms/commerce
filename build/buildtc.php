<?php

require 'Builder.php';
require 'UtilsHelper.php';

$args = UtilsHelper::parseArgs($argv);
$builder = new Builder($args);
$builder->run();
