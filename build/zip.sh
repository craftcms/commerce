#!/bin/bash

zip -r "market.zip" market templates -x "market/composer.json" "market/composer.lock" "market/codeception.yml" "market/tests/*" "*.DS_Store*"
