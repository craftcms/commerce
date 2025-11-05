# Work In Progress Changelog

## Unreleased

### Store Management
- PDF download links now include time-limited security tokens that expire after a configurable duration (default 24 hours).
- Anonymous users attempting to download a PDF with an expired or missing token are now shown an email verification form with a masked email address (e.g., `j***@example.com`).
- PDF link expiry duration can now be configured per PDF in Settings → PDFs.
- Logged-in users who own the order or have appropriate permissions bypass the email verification flow and can download PDFs directly.
- Added a new system email message for sending PDF download links to customers.
- 
### Administration
- Added "Link Expiry" field to PDF settings, allowing administrators to configure how long download links remain valid.

### Extensibility
- Added `craft\commerce\elements\Order::getMaskedEmail()`.
- Added `craft\commerce\models\Pdf::$linkExpiry`.
- Added `craft\commerce\services\Pdfs::getPdfUrl()` now generates secure tokenized URLs with expiry timestamps.
- Added `craft\commerce\controllers\DownloadsController::actionEmailChallenge()`.
- Added `craft\commerce\controllers\DownloadsController::actionPdfChallenge()`.
- Added `craft\commerce\controllers\DownloadsController::actionPdfSent()`.

