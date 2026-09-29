<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Separator;
use CraftCms\Cms\Form\Nodes\Tab;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Commerce\Inventory\Data\DeactivateInventoryLocation;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\InventoryLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

readonly class InventoryLocationsController
{
    use RespondsWithFlash;

    /** The Address element's geographic fields, which the Address control nests under `address`. */
    private const array ADDRESS_CONTROL_FIELDS = [
        'addressLine1',
        'addressLine2',
        'addressLine3',
        'administrativeArea',
        'locality',
        'dependentLocality',
        'postalCode',
        'sortingCode',
    ];

    /** The Address element's other native attributes an address field layout can edit. */
    private const array ADDRESS_NATIVE_FIELDS = [
        'countryCode',
        'organization',
        'organizationTaxId',
        'fullName',
        'firstName',
        'lastName',
        'latitude',
        'longitude',
    ];

    public function __construct(
        private FormResolver $formResolver,
    ) {
    }

    public function index(): CpScreenResponse
    {
        $inventoryLocations = app(InventoryLocations::class)->getAllInventoryLocations();
        $canDelete = $inventoryLocations->count() > 1;

        $rows = $inventoryLocations->map(fn(InventoryLocation $inventoryLocation) => [
            'id' => $inventoryLocation->id,
            '_sort' => ['name' => $inventoryLocation->getUiLabel()],
            'name' => ['html' => Html::a(Html::encode($inventoryLocation->getUiLabel()), $inventoryLocation->getCpEditUrl(), ['class' => 'cell-bold'])],
            'handle' => $inventoryLocation->handle,
            'address' => $inventoryLocation->getAddressLine(),
        ])->values()->all();

        $nodes = [
            Table::make('inventory-locations')
                ->columns([
                    ['key' => 'name', 'label' => t('Name', category: 'commerce'), 'sortable' => true],
                    ['key' => 'handle', 'label' => t('Handle', category: 'commerce')],
                    ['key' => 'address', 'label' => t('Address')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No inventory locations exist yet.', category: 'commerce'))
                ->when(
                    app(InventoryLocations::class)->canCreateInventoryLocation(),
                    fn(Table $table) => $table
                        ->createAction(t('New location', category: 'commerce'), cp_url('commerce/inventory-locations/new'))
                        ->createActionInPageHeader(),
                )
                ->when($canDelete, fn(Table $table) => $table->deletable(
                    action([self::class, 'deactivate']),
                    modalUrl: action([self::class, 'prepareDeleteModal']),
                )),
        ];

        return new CpScreenResponse()
            ->title(t('Inventory Locations', category: 'commerce'))
            ->crumbs($this->crumbs())
            ->selectedSubnavItem('inventory-locations')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function edit(?int $inventoryLocationId = null): CpScreenResponse
    {
        $inventoryLocation = $this->resolveInventoryLocation($inventoryLocationId);
        $address = $inventoryLocation->getAddress();

        $title = $inventoryLocation->id
            ? (trim($inventoryLocation->getUiLabel()) ?: t('Edit Inventory Location'))
            : t('Create a new inventory location');

        $formatter = app(Formatter::class);
        $metadataHtml = $inventoryLocation->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($inventoryLocation->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($inventoryLocation->dateUpdated, 'short'),
        ]) : null;

        $form = $this->formResolver->resolve(
            $this->buildForm($inventoryLocation, $address),
            new FormContext(values: $this->initialValues($inventoryLocation, $address), refreshable: true),
        );

        return new CpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs(...($inventoryLocation->id ? [['label' => $title]] : [])))
            ->action('commerce/inventory-locations/save')
            ->redirectUrl('commerce/inventory-locations')
            ->selectedSubnavItem('inventory-locations')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => action([self::class, 'renderForm']),
                'metadataHtml' => $metadataHtml,
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form tree for the values currently in progress on the
     * client, so changing the country can reveal the right set of country-specific address
     * fields without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $inventoryLocationId = ($values['inventoryLocationId'] ?? null) ? (int)$values['inventoryLocationId'] : null;
        $inventoryLocation = $this->resolveInventoryLocation($inventoryLocationId);
        $address = $inventoryLocation->getAddress();

        if (!empty($values['countryCode'])) {
            $address->countryCode = $values['countryCode'];
        }

        $values = array_replace($this->initialValues($inventoryLocation, $address), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($inventoryLocation, $address),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    public function save(Request $request): Response
    {
        $inventoryLocation = $this->resolveInventoryLocation($request->integer('inventoryLocationId') ?: null);

        if (!$inventoryLocation->id && !app(InventoryLocations::class)->canCreateInventoryLocation()) {
            return $this->asFailure(t('The maximum number of inventory locations for this edition has been reached.', category: 'commerce'));
        }

        $inventoryLocation->name = (string)$request->input('name');
        $inventoryLocation->handle = (string)$request->input('handle');

        // Validated before the address is saved, so an invalid location never orphans an address.
        $isValid = $inventoryLocation->validate();

        $addressId = $request->integer('addressId') ?: null;
        /** @var Address|null $address */
        $address = $isValid && $addressId ? Elements::getElementById($addressId, Address::class) : null;
        $address ??= new Address();

        $this->populateAddress($address, $request);
        $address->title = $inventoryLocation->name;

        if ($isValid) {
            Elements::saveElement($address);
        } else {
            $address->validate();
        }

        $inventoryLocation->setAddress($address);

        if (
            $inventoryLocation->errors()->isNotEmpty() ||
            $address->errors()->isNotEmpty() ||
            !app(InventoryLocations::class)->saveInventoryLocation($inventoryLocation)
        ) {
            return $this->asModelFailure(
                model: $inventoryLocation,
                message: t('Couldn’t save inventory location.', category: 'commerce'),
                modelName: 'inventoryLocation',
                data: ['errors' => [
                    ...$inventoryLocation->errors()->getMessages(),
                    ...$this->addressErrors($address),
                ]],
            );
        }

        return $this->asModelSuccess(
            model: $inventoryLocation,
            message: t('Inventory location saved.', category: 'commerce'),
            modelName: 'inventoryLocation',
        );
    }

    /**
     * The Form shown by the index Table's delete modal, asking where the location's stock
     * should move to. Its values are posted to {@see deactivate()} along with the row `id`.
     */
    public function prepareDeleteModal(Request $request): JsonResponse
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocationId = $request->integer('id');
        abort_if(!$inventoryLocationId, 400, 'Missing id');

        $inventoryLocation = $this->resolveInventoryLocation($inventoryLocationId);

        $destinationOptions = app(InventoryLocations::class)->getAllInventoryLocations()
            ->reject(fn(InventoryLocation $location) => $location->id === $inventoryLocation->id)
            ->map(fn(InventoryLocation $location) => ['value' => (string)$location->id, 'label' => $location->getUiLabel()])
            ->values()
            ->all();

        abort_if(empty($destinationOptions), 400, 'Can not delete last inventory location.');

        $form = Form::make([
            Field::make(t('Destination Inventory Location', category: 'commerce'), Choice::make('destinationInventoryLocation')->options($destinationOptions))
                ->instructions(t('Choose the destination inventory location for the existing on hand stock.', category: 'commerce'))
                ->required(),
        ]);

        return new JsonResponse([
            'form' => $this->formResolver->resolve($form, new FormContext(values: [
                'destinationInventoryLocation' => $destinationOptions[0]['value'],
            ])),
            'title' => t('Deleting the {location} location.', ['location' => $inventoryLocation->name], category: 'commerce'),
            'submitLabel' => t('Delete'),
        ]);
    }

    public function deactivate(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocationId = $request->integer('id');
        $destinationInventoryLocationId = $request->integer('destinationInventoryLocation');
        abort_if(!$inventoryLocationId || !$destinationInventoryLocationId, 400, 'Missing id or destinationInventoryLocation');

        $deactivateInventoryLocation = new DeactivateInventoryLocation([
            'inventoryLocation' => $this->resolveInventoryLocation($inventoryLocationId),
            'destinationInventoryLocation' => $this->resolveInventoryLocation($destinationInventoryLocationId),
        ]);

        if (!app(InventoryLocations::class)->executeDeactivateInventoryLocation($deactivateInventoryLocation)) {
            $errors = $deactivateInventoryLocation->errors();

            return $this->asFailure(
                implode(' ', [t('Inventory was not updated.', category: 'commerce'), ...$errors->get('inventoryLocation')]),
                ['errors' => $errors->getMessages()],
            );
        }

        return $this->asSuccess();
    }

    /**
     * Builds "Commerce / Inventory Locations[ / ...$trail]". The last crumb never links.
     *
     * @param array{label: string, url?: ?string} ...$trail
     * @return list<array{label: string, href: ?string}>
     */
    private function crumbs(array ...$trail): array
    {
        $crumbs = [
            ['label' => t('Commerce', category: 'commerce'), 'href' => cp_url('commerce')],
            ['label' => t('Inventory Locations', category: 'commerce'), 'href' => cp_url('commerce/inventory-locations')],
            ...array_map(fn(array $crumb) => ['label' => $crumb['label'], 'href' => $crumb['url'] ?? null], $trail),
        ];

        $crumbs[array_key_last($crumbs)]['href'] = null;

        return $crumbs;
    }

    private function resolveInventoryLocation(?int $inventoryLocationId): InventoryLocation
    {
        if ($inventoryLocationId === null) {
            return new InventoryLocation();
        }

        $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById($inventoryLocationId);
        abort_if(!$inventoryLocation, 404, 'Inventory location not found');

        return $inventoryLocation;
    }

    /** @return array{inventoryLocationId: ?int, addressId: ?int, name: string, handle: string} */
    private function initialValues(InventoryLocation $inventoryLocation, Address $address): array
    {
        return [
            'inventoryLocationId' => $inventoryLocation->id,
            'addressId' => $address->id,
            'name' => $inventoryLocation->name,
            'handle' => $inventoryLocation->handle,
        ];
    }

    /**
     * The location's own fields followed by the Address element's field layout, so custom
     * address fields and the admin-configured layout carry over.
     */
    private function buildForm(InventoryLocation $inventoryLocation, Address $address): Form
    {
        $handle = Handle::make('handle');
        if (!$inventoryLocation->id) {
            $handle->source('name');
        }

        $ownNodes = [
            ...($inventoryLocation->id ? [HiddenField::make('inventoryLocationId')] : []),
            HiddenField::make('addressId'),
            Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())->required(),
            Field::make(t('Handle', category: 'commerce'), $handle)->required(),
            Separator::make('address-separator'),
        ];

        $addressNodes = $this->prepareAddressNodes(app(FieldLayoutCompiler::class)->form(
            $address->getFieldLayout(),
            $address,
            new FormContext(refreshable: true),
        )->nodes());

        if (($addressNodes[0] ?? null) instanceof Tab) {
            $addressNodes[0]->prepend(...$ownNodes);

            return Form::make($addressNodes);
        }

        return Form::make([...$ownNodes, ...$addressNodes]);
    }

    /**
     * Drops the layout's Label (`title`) field, since the location's name is used as the
     * address title, and makes the country select reactive so the address fields follow it.
     *
     * @param list<Node> $nodes
     * @return list<Node>
     */
    private function prepareAddressNodes(array $nodes): array
    {
        $prepared = [];

        foreach ($nodes as $node) {
            if ($node instanceof Tab) {
                $prepared[] = Tab::make($node->uid(), $node->props()['label'], $this->prepareAddressNodes($node->children()));
                continue;
            }

            $control = $node->getControl();
            $path = $control ? (array)$control->path() : [];

            if ($path === ['title']) {
                continue;
            }

            if ($control && $path === ['countryCode']) {
                $control->reactive();
            }

            $prepared[] = $node;
        }

        return $prepared;
    }

    private function populateAddress(Address $address, Request $request): void
    {
        $address->setAttributes([
            ...$request->only(self::ADDRESS_NATIVE_FIELDS),
            ...array_intersect_key((array)$request->input('address'), array_flip(self::ADDRESS_CONTROL_FIELDS)),
        ]);

        $fields = $request->input('fields');
        if (is_array($fields)) {
            $address->setFieldValues($fields);
        }
    }

    /**
     * Maps the address's validation errors onto the control paths they render at.
     *
     * @return array<string, list<string>>
     */
    private function addressErrors(Address $address): array
    {
        $customFieldHandles = array_map(fn($field) => $field->handle, $address->getFieldLayout()->getCustomFields());
        $errors = [];

        foreach ($address->errors()->getMessages() as $attribute => $messages) {
            $root = explode('.', (string)$attribute)[0];
            $path = match (true) {
                in_array($root, self::ADDRESS_CONTROL_FIELDS, true) => "address.$attribute",
                in_array($root, $customFieldHandles, true) => "fields.$attribute",
                default => (string)$attribute,
            };
            $errors[$path] = $messages;
        }

        return $errors;
    }
}
