#!/usr/bin/env bash

pathToIniFile="/Applications/MAMP/bin/php/php5.6.1/conf/php.ini"
pathToTestDir="/Users/lh/Code/Projects/craftdevstabletrack/craft/plugins/stripey/tests"
pathToPhpUnit="/Users/lh/Code/Projects/craftdevstabletrack/craft/app/vendor/phpunit/phpunit/phpunit"
pathToBootstrap="/Users/lh/Code/Projects/craftdevstabletrack/craft/app/tests/bootstrap.php"
pathToConfigFile="/Users/lh/Code/Projects/craftdevstabletrack/craft/app/tests/phpunit.xml"

php -c $pathToIniFile $pathToPhpUnit --bootstrap $pathToBootstrap --configuration $pathToConfigFile $pathToTestDir