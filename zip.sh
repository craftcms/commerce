#!/bin/bash

zip -r "market.zip" plugins exampletemplates -x "plugins/market/composer.json" "plugins/market/composer.lock" "plugins/market/codeception.yml" "plugins/market/tests/*" "*.DS_Store*"