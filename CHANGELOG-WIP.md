# Release Notes for Craft Commerce 5.4 (WIP)

### Store Management

### Development

### Extensibility
- Added `craft\commerce\models\Email::$renderSiteId`.
- Added `craft\commerce\models\Email::getRenderSite()`.
- Added `craft\commerce\records\Email::$renderSiteId`.

### System
- Fixed a bug where order emails weren’t always getting rendered for the correct site.