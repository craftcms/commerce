# Upgrading from Market Commerce

Craft Commerce is the successor to Market Commerce. To upgrade from Market Commerce to Craft Commerce, follow these instructions.

## Preparations

Before you perform the upgrade, make sure you are prepared.

* Ensure you are running [Market 0.8.09](http://buildwithmarket.com/downloads/market-0.8.09.zip). (Do NOT uninstall Market Commerce!)
* Ensure you are running [Craft 2.5.2715 Beta](http://buildwithcraft.com/beta#build2715) or later.
* Backup your database.

## Upgrade Instructions

To perform the upgrade, follow these instructions.

1. Upload the ‘commerce’ folder to ‘craft/plugins’.
2. Go to Settings → Plugins within the Control Panel.
3. Click on the “Install” button beside “Craft Commerce”.
4. Ensure that Craft Commerce is now installed, and Market Commerce is uninstalled.
5. Delete your ‘craft/plugins/market’ folder.

## Updating your Templates

Updating your front end templates for Craft Commerce should be as simple as performing a series of find-and-replace operations based on the following table:

Find | Replace
-----|--------
craft.market. | craft.commerce.
cart.shippingMethodId | cart.shippingMethodHandle
\|marketCurrency | \|commerceCurrency
name="action" value="market/cart/setEmail" | name="action" value="commerce/cart/updateCart"
name="action" value="market/cartAddress/chooseAddresses" | name="action" value="commerce/cart/updateCart"
name="action" value="market/cart/setShippingMethod" | name="action" value="commerce/cart/updateCart"
name="action" value="market/cart/add" | name="action" value="commerce/cart/updateCart"
name="action" value="market/cart/remove" | name="action" value="commerce/cart/removeLineItem"
name="action" value="market/cart/removeAll" | name="action" value="commerce/cart/removeAllLineItems"
name="action" value="market/cart/applyCoupon" | name="action" value="commerce/cart/updateCart"

The `product.implicitVariant`, in all cases, has been replaced with a `product.defaultVariant`, but there is no longer the need to check if the product type has multiple variants before accessing it. For non variant products, the first variant is always the default. For products with multiple variants, the variant selected with the 'default' toggle is the defaultVariant.