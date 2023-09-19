<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\base\Purchasable as PurchasableElement;
use craft\commerce\Plugin;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;

/**
 * Purchasable helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.8
 */
class Purchasable
{
    public const TEMPORARY_SKU_PREFIX = '__temp_';

    /**
     * Generates a new temporary SKU.
     *
     * @since 3.2.8
     */
    public static function tempSku(): string
    {
        return static::TEMPORARY_SKU_PREFIX . StringHelper::randomString();
    }

    /**
     * Returns whether the given SKU is temporary.
     *
     * @since 3.2.8
     */
    public static function isTempSku(string $sku): bool
    {
        return str_starts_with($sku, static::TEMPORARY_SKU_PREFIX);
    }

    /**
     * @param PurchasableElement[] $purchasables
     * @param array $config
     * @return string
     * @since 5.0.0
     */
    public static function purchasableCardsHtml(array $purchasables, array $config = []): string
    {
        $config += [
            'id' => sprintf('purchasables%s', mt_rand()),
            'productId' => null,
            'maxVariants' => null,
        ];

        $view = Craft::$app->getView();

        $view->registerJsWithVars(fn($selector, $settings) => <<<JS
new Craft.Commerce.VariantsInput($($selector), $settings);
JS, [
            sprintf('#%s', $view->namespaceInputId($config['id'])),
            [
                'productId' => $config['productId'],
                'maxVariants' => $config['maxVariants'],
            ],
        ]);

        return
            // @TODO remove debug heading
            (!empty($purchasables) ? Html::tag('h3', 'Store: ' . ArrayHelper::firstValue($purchasables)->getStore()->name) : '') .

            Html::beginTag('ul', [
                'id' => $config['id'],
                'class' => 'purchasable-cards',
            ]) .
                implode("\n", array_map(fn(PurchasableElement $purchasable) => static::purchasableCardHtml($purchasable, $config), $purchasables)) .
                Html::beginTag('li') .
                    Html::beginTag('button', [
                        'type' => 'button',
                        'class' => ['btn', 'dashed', 'add', 'icon', 'purchasable-cards__add-btn'],
                    ]) .
                        Html::tag('div', '', [
                            'class' => ['spinner', 'spinner-absolute'],
                        ]) .
                        Html::tag('div', Craft::t('commerce', 'Add'), [
                            'class' => 'label',
                        ]) .
                    Html::endTag('button') . // .add
                Html::endTag('li') .
            Html::endTag('ul'); // .purchasable-cards
    }

    /**
     * @param PurchasableElement $purchasable
     * @param array $config
     * @return string
     * @since 5.0.0
     */
    public static function purchasableCardHtml(PurchasableElement $purchasable, array $config = []): string
    {
        $config += [
            'name' => null,
        ];

        $canDelete = Craft::$app->getElements()->canDelete($purchasable);
        $actionMenuId = sprintf('purchasable-card-action-menu-%s', mt_rand());

        $statusText = match ($purchasable->getStatus()) {
            PurchasableElement::STATUS_ENABLED => Craft::t('app', 'Enabled'),
            default => Craft::t('app', 'Disabled'),
        };

        $title = [
            Html::tag('span', '', [
                'class' => ['status', $purchasable->getStatus()],
                'role' => 'img',
                'aria-label' => Craft::t('app', 'Status:') . ' ' . $statusText,
            ]) . Html::tag('strong', Html::encode($purchasable->title)),
            Html::encode($purchasable->getSku()),
        ];

        if ($purchasable->basePromotionalPrice) {
            $title[] = Html::tag('del', $purchasable->basePriceAsCurrency) . ' ' .
                $purchasable->basePromotionalPriceAsCurrency;
        } else {
            $title[] = $purchasable->basePriceAsCurrency;
        }

        $title = implode(' | ', $title);

        return
            Html::beginTag('li', [
                'class' => 'purchasable-card',
                'data' => [
                    'id' => $purchasable->id,
                    'draftId' => $purchasable->draftId,
                ],
            ]) .
            ($config['name'] ? Html::hiddenInput("{$config['name']}[]", (string)$purchasable->id) : '') .
            Html::beginTag('div', ['class' => 'purchasable-card-header']) .
            Html::tag('div', $title) .
            ($canDelete
                ? Html::beginTag('div', [
                    'class' => 'purchasable-card-header-actions',
                    'data' => [
                        'wrapper' => true,
                    ],
                ]) .
                Html::button('', [
                    'class' => ['btn', 'menubtn'],
                    'title' => Craft::t('app', 'Actions'),
                    'aria' => [
                        'controls' => $actionMenuId,
                        'label' => sprintf('%s %s', $purchasable->title ? Html::encode($purchasable->title) : Craft::t('commerce', 'New'), Craft::t('app', 'Settings')),
                    ],
                    'data' => [
                        'icon' => 'settings',
                        'disclosure-trigger' => true,
                    ],
                ]) .
                Html::beginTag('div', [
                    'id' => $actionMenuId,
                    'class' => ['menu', 'menu--disclosure'],
                ]) .
                Html::beginTag('ul', ['class' => 'padded']) .
                Html::beginTag('li') .
                Html::button(Craft::t('app', 'Edit'), [
                    'class' => 'menu-option',
                    'type' => 'button',
                    'aria' => [
                        'label' => Craft::t('app', 'Edit'),
                    ],
                    'data' => [
                        'icon' => 'edit',
                        'action' => 'edit',
                    ],
                ]) .
                Html::endTag('li') .
                Html::beginTag('li') .
                Html::button(Craft::t('app', 'Delete'), [
                    'class' => 'error menu-option',
                    'type' => 'button',
                    'aria' => [
                        'label' => Craft::t('app', 'Delete'),
                    ],
                    'data' => [
                        'icon' => 'remove',
                        'action' => 'delete',
                    ],
                ]) .
                Html::endTag('li') .
                Html::endTag('ul') .
                Html::endTag('div') . // .menu
                Html::endTag('div') // .purchasable-card-header-actions
                : ''
            ) .
            Html::endTag('div') . // .purchasable-card-header
            Html::endTag('li'); // .purchasable-card
    }

    /**
     * @param int $purchasableId
     * @param int $storeId
     * @param Collection|null $catalogPricing
     * @return string
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public static function catalogPricingRulesTableByPurchasableId(int $purchasableId, int $storeId, ?Collection $catalogPricing = null): string
    {
        $catalogPricing = $catalogPricing ?? Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId);
        $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);

        if ($catalogPricingRules->isEmpty()) {
            return '';
        }

        return Cp::renderTemplate('commerce/prices/_table', [
            'catalogPrices' => $catalogPricing,
            'showPurchasable' => false,
            'removeMargin' => true,
        ]);
    }
}
