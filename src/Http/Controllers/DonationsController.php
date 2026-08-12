<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class DonationsController
{
    use RespondsWithFlash;

    public function edit(): CpScreenResponse
    {
        $donation = Donation::find()->status(null)->one();

        if ($donation === null) {
            $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
            $donation = new Donation();
            $donation->siteId = Sites::getPrimarySite()->id;
            $donation->sku = 'DONATION-CC5';
            $donation->availableForPurchase = false;
            $donation->taxCategoryId = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
            $donation->shippingCategoryId = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($primaryStore->id)->id;
            Elements::saveElement($donation);
        }

        return new CpScreenResponse()
            ->title(t('Donation Settings', category: 'commerce'))
            ->addCrumb(t('Commerce', category: 'commerce'), 'commerce')
            ->selectedSubnavItem('donations')
            ->action('commerce/donations/save')
            ->submitButtonLabel(t('Save'))
            ->redirectUrl('commerce/donations')
            ->contentTemplate('commerce/donation/_edit.twig', ['donation' => $donation]);
    }

    public function save(Request $request): Response
    {
        $donation = Donation::find()->status(null)->one();

        if ($donation === null) {
            $donation = new Donation();
            $donation->siteId = Sites::getPrimarySite()->id;
        }

        $donation->sku = $request->input('sku');
        $donation->availableForPurchase = (bool)$request->input('availableForPurchase');
        $donation->enabled = (bool)$request->input('enabled');

        if (!Elements::saveElement($donation)) {
            return $this->asModelFailure($donation, t('Couldn\'t save donation settings.', category: 'commerce'), 'donation');
        }

        return $this->asSuccess(t('Donation settings saved.', category: 'commerce'), redirect: 'commerce/donations');
    }
}
