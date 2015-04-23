#!/bin/bash

# Teamcity Build Steps

# Build Step 1
cd plugins/market
composer clear-cache
composer install

# Build Step 2
cd plugins/market
./vendor/bin/codecept run

# Build Step 3 (if 2 passes)
cd plugins/market
composer remove --update-no-dev

# Build Step 4
VERSION=`cat plugins/market/VERSION.txt`
BRANCH=`echo $BUILD_BRANCH | sed 's|refs/heads/||g'`
zip -r "/root/builds/$BRANCH/market-$VERSION.$BUILD_NUMBER.zip" plugins exampletemplates -x "plugins/market/composer.json" "plugins/market/composer.lock" "plugins/market/codeception.yml" "plugins/market/tests/*" "*.DS_Store*"