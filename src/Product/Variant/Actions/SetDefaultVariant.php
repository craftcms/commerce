<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
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
        HtmlStack::jsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: false,
    });
})();
JS, [static::class]);

        return null;
    }

    #[\Override]
    public static function supportsBulk(): bool
    {
        return false;
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
