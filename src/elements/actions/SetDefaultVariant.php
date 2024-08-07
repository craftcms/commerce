<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;

/**
 * Class Set Default Variant
 *
 * @property null|string $triggerHtml the action’s trigger HTML
 * @property string $triggerLabel the action’s trigger label
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class SetDefaultVariant extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('commerce', 'Set default variant');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        $type = Json::encode(static::class);

        $js = <<<EOT
(function()
{
    new Craft.ElementActionTrigger({
        type: $type,
        batch: false,
    });
})();
EOT;

        Craft::$app->getView()->registerJs($js);

        return null;
    }

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        /** @var Variant|null $variant */
        $variant = $query->one();
        if (!$variant) {
            $this->setMessage(Craft::t('commerce', 'Unable to find variant.'));
            return false;
        }

        $product = $variant->getOwner();
        if (!$product) {
            $this->setMessage(Craft::t('commerce', 'Variant has no product.'));
            return false;
        }

        // Update product row
        Craft::$app->getDb()->createCommand()->update(
            Table::PRODUCTS,
            [
                'defaultVariantId' => $variant->id,
                'defaultSku' => $variant->sku,
                'defaultPrice' => $variant->getBasePrice(),
                'defaultHeight' => $variant->height,
                'defaultLength' => $variant->length,
                'defaultWidth' => $variant->width,
                'defaultWeight' => $variant->weight,
            ],
            ['id' => $product->id]
        )->execute();

        if ($product->getIsCanonical()) {
            // Remove previous default
            Craft::$app->getDb()->createCommand()->update(
                Table::VARIANTS,
                ['isDefault' => false],
                ['primaryOwnerId' => $product->id]
            )->execute();

            // Add new default
            Craft::$app->getDb()->createCommand()->update(
                Table::VARIANTS,
                ['isDefault' => true],
                ['id' => $variant->id]
            )->execute();
        }

        $this->setMessage(Craft::t('commerce', 'Default variant updated.'));
        return true;
    }
}
