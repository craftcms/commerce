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
use craft\commerce\helpers\PaymentForm;
use craft\errors\SiteNotFoundException;
use craft\models\Site;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
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
     * @return null[]
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getGlobals(): array
    {
        $app = Craft::$app;
        $plugins = $app->getPlugins();
        $updates = $app->getUpdates();
        $isInstalled = $app->getIsInstalled() && $plugins->isPluginInstalled('commerce') && $plugins->isPluginEnabled('commerce');

        if ($isInstalled && !$updates->getIsCraftUpdatePending()) {
            /** @var Site|StoreBehavior $currentSite */
            $currentSite = $app->getSites()->getCurrentSite();
            $currentStore = $currentSite->getStore();
        } else {
            $currentStore = null;
        }

        return [
            'currentStore' => $currentStore,
        ];
    }
}
