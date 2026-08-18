<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\FieldLayout\LayoutElements\Addresses\AddressField;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormHtmlRenderer;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpModalResponse;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Inventory\Models\DeactivateInventoryLocation;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

readonly class InventoryLocationsController
{
    use RespondsWithFlash;

    public function index(): CpScreenResponse
    {
        $inventoryLocations = app(InventoryLocations::class)->getAllInventoryLocations();
        $currentUser = currentUser();

        $screen = new CpScreenResponse()
            ->title(t('Inventory Locations', category: 'commerce'))
            ->addCrumb(t('Commerce', category: 'commerce'), 'commerce')
            ->selectedSubnavItem('inventory-locations')
            ->contentTemplate('commerce/inventory-locations/_index', []);

        $locationCount = count($inventoryLocations);
        $showNewButton = $locationCount < Plugin::EDITION_PRO_STORE_LIMIT;
        $userCanCreate = $currentUser?->can('commerce-createLocations');

        if ($userCanCreate && $showNewButton) {
            $button = Html::a(
                t('New location', category: 'commerce'),
                'commerce/inventory-locations/new',
                ['class' => 'btn submit add icon']
            );
            $screen->additionalButtonsHtml($button);
        }

        return $screen;
    }

    public function edit(?int $inventoryLocationId = null): CpScreenResponse
    {
        if ($inventoryLocationId !== null) {
            $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById($inventoryLocationId);
            abort_if(!$inventoryLocation, 404, 'Inventory location not found');

            $title = trim((string)$inventoryLocation->getUiLabel()) ?: t('Edit Inventory Location');
        } else {
            $inventoryLocation = new InventoryLocation();
            $title = t('Create a new inventory location');
        }

        InputNamespace::set('inventoryLocationAddress');

        $address = $inventoryLocation->getAddress();
        $fieldLayout = $address->getFieldLayout();

        // The legacy FieldLayout::createForm()/FieldLayoutForm API is gone — form building now
        // goes through FieldLayoutCompiler (produces an immutable FormPayload) + FormHtmlRenderer
        // (renders that payload to HTML/tab data), matching cms-6's own EditElementController::
        // prepareEditor(). There's no more tabIdPrefix (namespace alone drives both input names
        // and DOM ids), and the payload can't be mutated the way $form->tabs used to be.
        // TODO: strip the address field layout's own title/LabelField (currently just rendered
        // alongside our own explicit Name field below) via CraftCms\Cms\FieldLayout\Events\
        // FieldLayoutFormResolving once that's worth the complexity — it fires inside
        // FieldLayoutCompiler::form() with a mutable Form the listener can filter nodes from.
        $payload = app(FieldLayoutCompiler::class)->compile(
            $fieldLayout,
            $address,
            new FormContext(
                namespace: 'inventoryLocationAddress',
                errors: $address->errors()->getMessages(),
                mode: ControlMode::Editable,
                refreshable: true,
            ),
        );
        $renderer = app(FormHtmlRenderer::class);
        $form = $renderer->render($payload);
        $tabs = $renderer->tabMenu($payload);

        // These used to be injected directly into the compiled form's first tab; they're rendered
        // ahead of the form's own HTML instead now, namespaced to match (see _edit.twig).
        $extraFieldsHtml =
            Html::hiddenInput('inventoryLocationId', (string)$inventoryLocationId) .
            \craft\helpers\Cp::textFieldHtml([
                'name' => 'name',
                'id' => 'name',
                'value' => $inventoryLocation->name,
                'required' => true,
                'label' => t('Name', category: 'commerce'),
                'errors' => $inventoryLocation->getErrors('name'),
            ]) .
            \craft\helpers\Cp::textFieldHtml([
                'name' => 'handle',
                'id' => 'handle',
                'value' => $inventoryLocation->handle,
                'required' => true,
                'label' => t('Handle', category: 'commerce'),
                'errors' => $inventoryLocation->getErrors('handle'),
            ]) .
            Html::hiddenInput('id', (string)$address->id) .
            Html::tag('hr');

        $variables = [
            'inventoryLocationId' => $inventoryLocationId,
            'inventoryLocation' => $inventoryLocation,
            'typeName' => t('Inventory Location', category: 'commerce'),
            'lowerTypeName' => t('inventory location', category: 'commerce'),
            'locationFieldHtml' => '',
            'addressField' => new AddressField(),
            'extraFieldsHtml' => $extraFieldsHtml,
            'form' => $form,
            'countries' => Addresses::getCountryRepository()->getList(\Craft::$app->language),
        ];

        return new CpScreenResponse()
            ->title($title)
            ->tabs($tabs)
            ->addCrumb(t('Commerce', category: 'commerce'), 'commerce')
            ->addCrumb(t('Inventory Locations', category: 'commerce'), 'commerce/inventory-locations')
            ->action('commerce/inventory-locations/save')
            ->redirectUrl('commerce/inventory-locations')
            ->selectedSubnavItem('inventory-locations')
            ->contentTemplate('commerce/inventory-locations/_edit', $variables);
    }

    public function save(Request $request): ?Response
    {
        // find the inventory location or make a new one
        $inventoryLocationId = $request->input('inventoryLocationAddress.inventoryLocationId');
        $inventoryLocation = null;

        if ($inventoryLocationId) {
            $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById((int)$inventoryLocationId);
        }

        $inventoryLocation ??= new InventoryLocation();

        $inventoryLocation->name = $request->input('inventoryLocationAddress.name');
        $inventoryLocation->handle = $request->input('inventoryLocationAddress.handle');

        // Pre-validate the inventory location so that we don't save the address if the rest isn't valid
        // This is to avoid orphaned addresses
        $isValid = $inventoryLocation->validate();

        if ($inventoryLocationAddress = $request->input('inventoryLocationAddress')) {
            // Remove the non-address fields from the post data
            unset($inventoryLocationAddress['name'], $inventoryLocationAddress['handle'], $inventoryLocationAddress['inventoryLocationId']);

            $inventoryLocationAddress['title'] = $inventoryLocation->name;
            if ($isValid) {
                $addressId = $inventoryLocationAddress['id'] ?: null;
                $address = $addressId ? Elements::getElementById((int)$addressId, Address::class) : new Address();

                $address->id = $addressId;
            } else {
                $address = new Address();
            }

            $address->setAttributes($inventoryLocationAddress, false);

            if (isset($inventoryLocationAddress['fields'])) {
                $address->setFieldValues($inventoryLocationAddress['fields']);
            }

            // Only try and save if the inventory location is valid
            $hasAddressErrors = false;
            if ($isValid && !Elements::saveElement($address)) {
                $hasAddressErrors = $address->hasErrors();
            } else {
                // If we aren't saving the address let's validate it to show any potential errors
                if (!$address->validate()) {
                    $hasAddressErrors = $address->hasErrors();
                }
            }

            if ($hasAddressErrors) {
                $inventoryLocation->addModelErrors($address, 'address');
            }

            $inventoryLocation->setAddress($address);
        }

        $inventoryLocation->addressId = $inventoryLocation->getAddress()->id;

        if ($inventoryLocation->hasErrors() || !app(InventoryLocations::class)->saveInventoryLocation($inventoryLocation)) {
            return $this->asModelFailure(
                model: $inventoryLocation,
                message: t('Couldn\'t save inventory location.', category: 'commerce'),
                modelName: 'inventoryLocation'
            );
        }

        return $this->asModelSuccess(
            model: $inventoryLocation,
            message: t('Inventory location saved.', category: 'commerce'),
            modelName: 'inventoryLocation'
        );
    }

    public function inventoryLocationsTableData(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocations = app(InventoryLocations::class)->getAllInventoryLocations();

        $data = [];
        foreach ($inventoryLocations as $inventoryLocation) {
            $id = $inventoryLocation->id;
            $deleteButtonId = sprintf("deleteButton-$id-%s", mt_rand());

            $deleteButton = Html::a('', '#', [
                'role' => 'button',
                'title' => t('Delete', category: 'commerce'),
                'class' => 'delete icon',
                'id' => $deleteButtonId,
            ]);

            HtmlStack::jsWithVars(fn($id, $settings) => <<<JS
\$('#' + $id).on('click', (e) => {
	e.preventDefault();
	const slideout = new Craft.CpModal('commerce/inventory-locations/prepare-delete-modal', $settings);
	slideout.on('close', (e) => {
	  window.InventoryLocationsAdminTable.reload();
	});
});
JS, [
                $deleteButtonId,
                ['params' => ['inventoryLocationId' => $id]],
            ]);

            /** @var InventoryLocation $inventoryLocation */
            $data[] = [
                'id' => $inventoryLocation->id,
                'title' => $inventoryLocation->getUiLabel(),
                'handle' => $inventoryLocation->handle,
                'address' => Html::encode($inventoryLocation->getAddressLine()),
                'url' => $inventoryLocation->getCpEditUrl(),
                'delete' => $inventoryLocations->count() > 1 ? $deleteButton : '',
            ];
        }

        return response()->json([
            'data' => $data,
            'headHtml' => HtmlStack::headHtml(),
            'bodyHtml' => HtmlStack::bodyHtml(),
        ]);
    }

    public function prepareDeleteModal(Request $request): CpModalResponse
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocationId = $request->input('inventoryLocationId');
        abort_if(!$inventoryLocationId, 400, 'Missing inventoryLocationId');

        $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById((int)$inventoryLocationId);
        $allInventoryLocations = app(InventoryLocations::class)->getAllInventoryLocations();

        $destinationInventoryLocations = $allInventoryLocations
            ->filter(fn($location) => $location->id != $inventoryLocation->id);

        $destinationInventoryLocationsOptions = $destinationInventoryLocations
            ->map(fn($location) => ['value' => $location->id, 'label' => $location->getUiLabel()])->all();

        abort_if(empty($destinationInventoryLocationsOptions), 400, 'Can not delete last inventory location.');

        $deactivateInventoryLocation = new DeactivateInventoryLocation([
            'inventoryLocation' => $inventoryLocation,
            'destinationInventoryLocation' => $destinationInventoryLocations->first(),
        ]);

        return new CpModalResponse()
            ->action('commerce/inventory-locations/deactivate')
            ->submitButtonLabel(t('Delete'))
            ->errorSummary('Can not delete inventory location.')
            ->contentTemplate('commerce/inventory-locations/_deleteModal', [
                'deactivateInventoryLocation' => $deactivateInventoryLocation,
                'inventoryLocationOptions' => $destinationInventoryLocationsOptions,
            ]);
    }

    public function deactivate(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocationId = $request->input('inventoryLocation');
        $destinationInventoryLocationId = $request->input('destinationInventoryLocation');
        abort_if(!$inventoryLocationId || !$destinationInventoryLocationId, 400, 'Missing inventoryLocation or destinationInventoryLocation');

        $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById((int)$inventoryLocationId);
        $destinationInventoryLocation = app(InventoryLocations::class)->getInventoryLocationById((int)$destinationInventoryLocationId);

        $deactivateInventoryLocation = new DeactivateInventoryLocation([
            'inventoryLocation' => $inventoryLocation,
            'destinationInventoryLocation' => $destinationInventoryLocation,
        ]);

        if (!app(InventoryLocations::class)->executeDeactivateInventoryLocation($deactivateInventoryLocation)) {
            return $this->asFailure(t('Inventory was not updated.', category: 'commerce'), [
                'errors' => $deactivateInventoryLocation->getErrors(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
