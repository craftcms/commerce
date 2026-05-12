# Release Notes for Craft Commerce 4.11

### Store Management

- Cart load URLs are now generated with time-limited security tokens, requiring a valid token or authenticated cart ownership to load a cart.
- Anonymous users attempting to load a cart with an expired or missing token are now shown a cart recovery form, which sends a new recovery link to the cart's email address.
- Added a new `commerce_cart_recovery` system message for customizing cart recovery emails.
- The "Share cart…" element action now generates a secure tokenized URL.

### Administration

- Added the `cartLoadUrlExpiry` setting, for controlling how long cart load links remain valid (default: 7 days).

### Extensibility

- Added `craft\commerce\controllers\OrdersController::actionGetLoadCartUrl()`.
- Added `craft\commerce\controllers\CartController::actionEmailChallenge()`.
- Added `craft\commerce\controllers\CartController::actionCartChallenge()`.
- Added `craft\commerce\controllers\CartController::actionCartSent()`.
- Added `craft\commerce\services\Carts::getLoadCartUrl()`.
- `craft\commerce\elements\Order::getLoadCartUrl()` now returns a secure tokenized URL.
- `commerce/cart/load-cart` now returns JSON responses for `Accept: application/json` requests, including a `challengeUrl` on failure.
