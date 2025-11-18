# Work In Progress Changelog

## Unreleased

### Store Management
- PDF download URLs are now generated with expirable tokens.
- Anonymous users attempting to download a PDF with an expired or missing token are now shown an email verification form.
- Added a new system message for customizing PDF download emails.

### Administration
- Added the “Link Duration” setting to PDF settings.

### Extensibility
- Added `craft\commerce\controllers\DownloadsController::actionEmailChallenge()`.
- Added `craft\commerce\controllers\DownloadsController::actionPdfChallenge()`.
- Added `craft\commerce\controllers\DownloadsController::actionPdfSent()`.
- Added `craft\commerce\elements\Order::getMaskedEmail()`.
- Added `craft\commerce\models\Pdf::$linkExpiry`.
- Added `craft\commerce\services\Pdfs::getPdfUrl()` now generates secure tokenized URLs with expiry timestamps.
