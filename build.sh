
#test script
cwd=$(pwd)
cd plugins/market
composer install
./vendor/bin/codecept run
cd $cwd


# if the above passes  deploy:

#deploy script
cwd=$(pwd)
cd plugins/market
composer install --no-dev
cd $cwd
buildnumber="9999999"
version=$(<plugins/market/VERSION.txt)
zip -r "market-${version}.${buildnumber}.zip" plugins exampletemplates -x "plugins/market/composer.json" "plugins/market/composer.lock" "plugins/market/codeception.yml" "plugins/market/tests/*" "*.DS_Store*" 

#sftp zip file to location I specify