<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\twig;

use Craft;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\LineItem;
use craft\commerce\helpers\PaymentForm;
use craft\errors\SiteNotFoundException;
use craft\models\Site;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use yii\base\InvalidConfigException;

/**
 * Class CommerceTwigExtension
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Extension extends AbstractExtension implements GlobalsInterface
{
    public function getName(): string
    {
        return 'Craft Commerce Twig Extension';
    }

    /**
     * @inheritdoc
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('commerceCurrency', [Currency::class, 'formatAsCurrency']),
            new TwigFilter('commercePaymentFormNamespace', [PaymentForm::class, 'getPaymentFormNamespace']),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('commerceCustomLineItem', [LineItem::class, 'generateCustomLineItemHash']),
        ];
    }

    /**
     * @return null[]
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getGlobals(): array
    {
        $currentStore = null;

        /** @var Site|StoreBehavior $currentSite */
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        if ($currentSite->getBehavior('commerce:store') !== null) {
            $currentStore = $currentSite->getStore();
        }

        return [
            'currentStore' => $currentStore,
        ];
    }
}
