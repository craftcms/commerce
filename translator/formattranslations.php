<?php

require 'Formatter.php';
require '../build/UtilsHelper.php';

$args = UtilsHelper::parseArgs($argv);
$translator = new Formatter($args);
$translator->init();
$translator->run();
