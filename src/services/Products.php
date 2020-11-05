<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\User;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use yii\base\Component;

/**
 * Product service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Products extends Component
{
    /**
     * Get a product by ID.
     *
     * @param int $id
     * @param int $siteId
     * @return Product|null
     */
    public function getProductById(int $id, $siteId = null)
    {
        /** @var Product $product */
        $product = Craft::$app->getElements()->getElementById($id, Product::class, $siteId);

        return $product;
    }

    /**
     * Handle a Site being saved.
     *
     * @param SiteEvent $event
     */
    public function afterSaveSiteHandler(SiteEvent $event)
    {
        $queue = Craft::$app->getQueue();
        $siteId = $event->oldPrimarySiteId;
        $elementTypes = [
            Product::class,
        ];

        foreach ($elementTypes as $elementType) {
            $queue->push(new ResaveElements([
                'elementType' => $elementType,
                'criteria' => [
                    'siteId' => $siteId,
                    'status' => null,
                    'enabledForSite' => false
                ]
            ]));
        }
    }


    public function hasPermission(User $user, Product $product, $checkPermissionName = null): bool
    {

        if ($user->admin == true) {
            return true;
        }

        $permissions = Craft::$app->getUserPermissions()->getPermissionsByUserId($user->id);

        $suffix = ':' . $product->getType()->uid;

        // Required for create and delete permission.
        $editProductType = strtolower('commerce-editProductType' . $suffix);

        if (!in_array($editProductType, $permissions) || ($checkPermissionName !== null && !in_array(strtolower($checkPermissionName), $permissions))) {
            return false;
        }

        return true;
    }
}
