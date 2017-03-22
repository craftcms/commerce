# Craft Commerce

This README is designed to be consumed by developers of Craft Commerce, not end users.

## Code License
Use of this software is subject to the License Agreement located at https://craftcommerce.com/license.

## Test Suite

Included is the basic framework for adding acceptence (integration) tests using 
selenium and codeception.
Tests are automations of the web app that replay the UI interactions and simulate 
workflows showing the expected behavior.

1) Install selenium server standalone. Suggest using [Homebrew](http://brew.sh/) on OSX.
```bash
brew install selenium-server-standalone
```

To have launchd start selenium-server-standalone now and restart at login:
```bash
brew services start selenium-server-standalone
```
Or, if you don't want/need a background service you can just run:
```bash
  selenium-server -p 4444
```

2) Install the codeception phar. [Codeception instructions](http://codeception.com/quickstart)

```bash
cd tests
wget http://codeception.com/codecept.phar
```

2) Run tests from tests directory

```bash
php codecept.phar run
```