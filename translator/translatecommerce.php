<?php

require 'Translator.php';
require '../build/UtilsHelper.php';

$args = UtilsHelper::parseArgs($argv);
$translator = new Translator($args);
$translator->init();
$translator->run();
