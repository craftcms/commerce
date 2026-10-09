<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\TaxCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class DonationsController
{
    use RespondsWithFlash;

    public function __construct(
        private UiResolver $formResolver,
    ) {
    }

    public function edit(): CpScreenResponse
    {
        $donation = Donation::find()->status(null)->one();

        if ($donation === null) {
            $primaryStore = app(Stores::class)->getPrimaryStore();
            $donation = new Donation();
            $donation->siteId = Sites::getPrimarySite()->id;
            $donation->sku = 'DONATION-CC5';
            $donation->availableForPurchase = false;
            $donation->taxCategoryId = app(TaxCategories::class)->getDefaultTaxCategory()->id;
            $donation->shippingCategoryId = app(ShippingCategories::class)->getDefaultShippingCategory($primaryStore->id)->id;
            Elements::saveElement($donation);
        }

        $values = $this->initialValues($donation);

        return new CpScreenResponse()
            ->title(t('Donation Settings', category: 'commerce'))
            ->crumbs([
                new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
                new ActionItem()->label(t('Donations', category: 'commerce')),
            ])
            ->selectedSubnavItem('donations')
            ->action('commerce/donations/save')
            ->redirectUrl('commerce/donations')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve($this->buildForm($values), new UiContext(values: $values, refreshable: true)),
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => action([self::class, 'renderForm']),
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form for the values in progress on the client, so the
     * fields that depend on the lightswitches show and hide as they're toggled.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        // Only the values of rendered controls are posted, so a field about to be revealed
        // falls back to the donation's own value.
        $donation = Donation::find()->status(null)->one() ?? new Donation();
        $values = array_replace($this->initialValues($donation), $request->input('values'));

        return new JsonResponse([
            'form' => $this->formResolver->resolve($this->buildForm($values), new UiContext(values: $values, refreshable: true)),
        ]);
    }

    public function save(Request $request): Response
    {
        $donation = Donation::find()->status(null)->one();

        if ($donation === null) {
            $donation = new Donation();
            $donation->siteId = Sites::getPrimarySite()->id;
        }

        // A hidden field isn't posted, and keeps the value it had.
        $donation->enabled = $request->boolean('enabled');
        $donation->availableForPurchase = $request->boolean('availableForPurchase', $donation->availableForPurchase);
        if ($request->has('sku')) {
            // Not null, which would be swapped for a temporary SKU and pass validation.
            $donation->sku = (string)$request->input('sku');
        }

        if (!Elements::saveElement($donation)) {
            return $this->asModelFailure($donation, t('Couldn’t save donation settings.', category: 'commerce'), 'donation');
        }

        return $this->asSuccess(t('Donation settings saved.', category: 'commerce'));
    }

    /** @return array{enabled: bool, availableForPurchase: bool, sku: ?string} */
    private function initialValues(Donation $donation): array
    {
        return [
            'enabled' => $donation->enabled,
            'availableForPurchase' => $donation->availableForPurchase,
            'sku' => $donation->sku,
        ];
    }

    /**
     * The purchase settings only apply to an enabled donation, and the SKU only to one that's
     * available for purchase.
     *
     * @param array<string, mixed> $values
     */
    private function buildForm(array $values): Ui
    {
        $enabled = (bool)($values['enabled'] ?? false);
        $availableForPurchase = (bool)($values['availableForPurchase'] ?? false);

        return Ui::make([
            Field::make(t('Enabled', category: 'commerce'), Lightswitch::make('enabled')->reactive()),
            Field::make(t('Available for purchase?', category: 'commerce'), Lightswitch::make('availableForPurchase')->reactive())
                ->visible($enabled),
            Field::make(t('SKU', category: 'commerce'), Text::make('sku')->monospace())
                ->instructions(t('The unique SKU of the donation purchasable.', category: 'commerce'))
                ->required()
                ->visible($enabled && $availableForPurchase),
        ]);
    }
}
