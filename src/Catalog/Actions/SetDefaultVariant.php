<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\t;

class SetDefaultVariant extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return t('Set default variant', category: 'commerce');
    }

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

        HtmlStack::js($js);

        return null;
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        /** @var Variant|null $variant */
        $variant = $query->one();
        if (!$variant) {
            $this->setMessage(t('Unable to find variant.', category: 'commerce'));
            return false;
        }

        $product = $variant->getOwner();
        if (!$product) {
            $this->setMessage(t('Variant has no product.', category: 'commerce'));
            return false;
        }

        DB::table(Table::PRODUCTS)
            ->where('id', $product->id)
            ->update([
                'defaultVariantId' => $variant->id,
                'defaultSku' => $variant->sku,
                'defaultPrice' => $variant->getBasePrice(),
                'defaultHeight' => $variant->height,
                'defaultLength' => $variant->length,
                'defaultWidth' => $variant->width,
                'defaultWeight' => $variant->weight,
            ]);

        if ($product->getIsCanonical()) {
            // Remove previous default
            DB::table(Table::VARIANTS)
                ->where('primaryOwnerId', $product->id)
                ->update(['isDefault' => false]);

            // Add new default
            DB::table(Table::VARIANTS)
                ->where('id', $variant->id)
                ->update(['isDefault' => true]);
        }

        ElementCaches::invalidateForElement($product);
        ElementCaches::invalidateForElement($variant);

        $this->setMessage(t('Default variant updated.', category: 'commerce'));
        return true;
    }
}
