<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\base\FieldInterface;
use craft\commerce\behaviors\CustomerBehavior;
use craft\commerce\console\Controller;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\records\Customer;
use craft\commerce\records\Store;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\conditions\addresses\AdministrativeAreaConditionRule;
use craft\elements\conditions\addresses\CountryConditionRule;
use craft\elements\User;
use craft\errors\OperationAbortedException;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use craft\validators\HandleValidator;
use yii\console\ExitCode;
use yii\db\Schema;

/**
 * Command to be run once upgraded to Commerce 4
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class MigrateV4Controller extends Controller
{
    /**
     * @var string|null The custom field handle that “Attention” address values should be saved to
     */
    public ?string $attentionField = null;
    /**
     * @var string|null The custom field handle that “Title” address values should be saved to
     */
    public ?string $titleField = null;
    /**
     * @var string|null The custom field handle that “Address 3” address values should be saved to
     */
    public ?string $address3Field = null;
    /**
     * @var string|null The custom field handle that “Business ID” address values should be saved to
     */
    public ?string $businessIdField = null;
    /**
     * @var string|null The custom field handle that “Phone Number” address values should be saved to
     */
    public ?string $phoneField = null;
    /**
     * @var string|null The custom field handle that “Alternative Phone” address values should be saved to
     */
    public ?string $alternativePhoneField = null;
    /**
     * @var string|null The custom field handle that “Custom 1” address values should be saved to
     */
    public ?string $custom1Field = null;
    /**
     * @var string|null The custom field handle that “Custom 2” address values should be saved to
     */
    public ?string $custom2Field = null;
    /**
     * @var string|null The custom field handle that “Custom 3” address values should be saved to
     */
    public ?string $custom3Field = null;
    /**
     * @var string|null The custom field handle that “Custom 4” address values should be saved to
     */
    public ?string $custom4Field = null;
    /**
     * @var string|null The custom field handle that “Notes” address values should be saved to
     */
    public ?string $notesField = null;

    /**
     * @inheritdoc
     */
    public $defaultAction = 'migrate';

    /**
     * @var string[] The list of fields that can be converted to PlainText fields
     */
    public array $neededCustomAddressFields = [
        'attention' => 'Attention',
        'title' => 'Title',
        'address3' => 'Address 3',
        'businessId' => 'Business ID',
        'phone' => 'Phone Number',
        'alternativePhone' => 'Alternative Phone',
        'custom1' => 'Custom 1',
        'custom2' => 'Custom 2',
        'custom3' => 'Custom 3',
        'custom4' => 'Custom 4',
        'notes' => 'Notes',
    ];

    private array $_oldAddressHandleToNewCustomFieldHandle = [];

    /**
     * v3CountryId => countryCode
     */
    private array $_countryCodesByV3CountryId = [];

    /**
     * v3StateId => administrativeArea
     */
    private array $_administrativeAreaByV3StateId = [];

    /**
     * v3AddressId => addressId
     */
    private array $_addressIdByV3AddressId = [];

    /**
     * @var array
     */
    public array $userIdsByv3CustomerId = [];

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        switch ($actionID) {
            case 'migrate':
                $options[] = 'attentionField';
                $options[] = 'titleField';
                $options[] = 'address3Field';
                $options[] = 'businessIdField';
                $options[] = 'phoneField';
                $options[] = 'alternativePhoneField';
                $options[] = 'custom1Field';
                $options[] = 'custom2Field';
                $options[] = 'custom3Field';
                $options[] = 'custom4Field';
                $options[] = 'notesField';
        }

        return $options;
    }


    /**
     * @return void
     */
    public function beforeAction($action): bool
    {
        // Collect all the countries and state that were set up in v3
        $this->_countryCodesByV3CountryId = $this->_countryCodesByV3CountryId();
        $this->_administrativeAreaByV3StateId = $this->_administrativeAreaByV3StateId();

        // Filter out the address columns we don't need to migrate to custom fields
        $this->_filterNeededCustomAddressFields();

        return parent::beforeAction($action);
    }

    /**
     * @return void
     * @see beforeAction();
     */
    private function _filterNeededCustomAddressFields()
    {
        $this->neededCustomAddressFields = array_filter($this->neededCustomAddressFields, function($fieldHandle) {
            $needed = (new Query())
                ->select($fieldHandle)
                ->where(['not', [$fieldHandle => null]])
                ->andWhere(['not', [$fieldHandle => '']])
                ->from(['{{%commerce_addresses}}'])
                ->all();

            return count($needed) > 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Reset Commerce data.
     *
     * @throws \Throwable
     */
    public function actionMigrate(): int
    {
        $this->stdout("This command will move data from previous Commerce 3 tables and columns to Commerce 4.\n");

        /**
         * Check to proceed.
         */
        if ($this->interactive) {
            $proceed = $this->prompt('Do you wish to continue?', [
                'required' => true,
                'default' => 'no',
                'validator' => function($input) {
                    if (!in_array($input, ['yes', 'no'])) {
                        $this->stderr('You must answer either "yes" or "no".' . PHP_EOL, Console::FG_RED);
                        return false;
                    }

                    return true;
                },
            ]);
            if ($proceed != 'yes') {
                $this->stdout('Aborting. No database changes made.' . PHP_EOL, Console::FG_RED);
                return ExitCode::OK;
            }
        }

        /**
         * Check to make sure they are not doing this before migrating the commerce data
         */
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '<')) {
            $this->stdout("You must run `craft migrate` command first.\n" . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        /**
         * Check to make sure they have all tables still around after the migration.
         * These will be deleted after this migration occurs. This is a way to
         * make sure they have not run this more than once also.
         */
        $tablesThatShouldStillExist = [
            '{{%commerce_addresses}}',
            '{{%commerce_customers_addresses}}',
            '{{%commerce_countries}}',
            '{{%commerce_states}}',
            '{{%commerce_shippingzone_countries}}',
            '{{%commerce_shippingzone_states}}',
            '{{%commerce_taxzone_countries}}',
            '{{%commerce_taxzone_states}}',
        ];
        foreach ($tablesThatShouldStillExist as $table) {
            $cleanTableName = str_replace(['{{%', '}}'], '', $table);
            if (!Craft::$app->getDb()->tableExists($table)) {
                $this->stdout('The `' . $cleanTableName . '` table no longer exists, can not proceed with v4 migration.' . PHP_EOL . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout("Migrating extra address fields to address custom fields...\n");
        $this->_migrateAddressCustomFields();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Creating user for every customer...\n");
        $this->_migrateCustomers();
        $this->stdout("Done.\n");

        $this->stdout("Migrating Customer Addresses...\n");
        $this->_migrateAddresses();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Store Location...\n");
        $this->_migrateUserPrimaryAddressIds();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Order Addresses...\n");
        $this->_migrateOrderAddresses();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Store Location...\n");
        $this->_migrateStoreLocation();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Shipping Zones...\n");
        $this->_migrateShippingZones();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Tax Zones...\n");
        $this->_migrateTaxZones();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        // TODO decide whether to drop the old unused tables, and all v3* columns

        return 0;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    private function _migrateAddressCustomFields(): void
    {
        if (!empty($this->neededCustomAddressFields)) {
            // Add custom fields to the address field layout
            $addressesService = Craft::$app->getAddresses();
            $fieldLayout = $addressesService->getLayout();
            $firstTab = $fieldLayout->getTabs()[0];
            $layoutElements = $firstTab->getElements();

            if ($this->interactive) {
                $list = implode(array_map(fn($label) => " - $label\n", $this->neededCustomAddressFields));
                $this->stdout(<<<EOL
Customer and order addresses will be migrated to native Craft address elements.
Some of the existing addresses contain data that will need to be stored in custom fields:
$list
EOL
                );
            }

            foreach ($this->neededCustomAddressFields as $oldAttribute => $label) {
                $field = $this->_customField($oldAttribute, $label);
                $layoutElements[] = new CustomField($field);
                $this->_oldAddressHandleToNewCustomFieldHandle[$oldAttribute] = $field->handle;
            }

            $firstTab->setElements($layoutElements);
            $addressesService->saveLayout($fieldLayout);
        }
    }

    /**
     * @param string $oldAttribute
     * @param string $label
     * @return FieldInterface
     * @throws OperationAbortedException
     * @throws \Throwable
     */
    private function _customField(string $oldAttribute, string $label): FieldInterface
    {
        $fieldsService = Craft::$app->getFields();

        // Was a field handle already specified as an option?
        $option = sprintf('%sField', $oldAttribute);
        if (isset($this->$option)) {
            $field = $fieldsService->getFieldByHandle($this->$option);
            if ($field) {
                return $field;
            }
            if (!$this->interactive) {
                $this->stderr("No custom field exists with the handle “{$this->$option}”.\n");
                throw new OperationAbortedException();
            }
            $this->stdout("No custom field exists with the handle “{$this->$option}”. Ignoring.\n");
        }

        if (!$this->interactive) {
            $this->stderr("Try again with the --$option option set to a valid custom field handle for storing $label data from existing addresses.\n");
            throw new OperationAbortedException();
        }

        $handlePattern = sprintf('/^%s$/', HandleValidator::$handlePattern);

        if (
            Craft::$app->getConfig()->getGeneral()->allowAdminChanges &&
            !$this->confirm("Do you have a custom field for storing $label values?")
        ) {
            $this->stdout("Let’s create one then.\n");
            $field = new PlainText();
            $field->handle = $this->prompt('Field handle:', [
                'required' => true,
                'validator' => function($handle) use ($handlePattern, $fieldsService) {
                    if (!preg_match($handlePattern, $handle)) {
                        return false;
                    }
                    if ($handle == 'title') {
                        $this->stdout("“{$handle}” is a reserved word.\n");
                        return false;
                    }
                    if ($fieldsService->getFieldByHandle($handle) !== null) {
                        $this->stdout("A field with the handle “{$handle}” already exists.\n");
                        return false;
                    }
                    return true;
                },
                'default' => $fieldsService->getFieldByHandle($oldAttribute) === null ? $oldAttribute : null,
            ]);
            $field->name = $this->prompt('Field name:', [
                'required' => true,
                'default' => $label,
            ]);
            $field->columnType = Schema::TYPE_STRING;

            if (!$fieldsService->saveField($field)) {
                $this->stderr(sprintf("Unable to save the field: %s\n", implode(', ', $field->getFirstErrors())));
                throw new OperationAbortedException();
            }

            return $field;
        }

        $handle = $this->prompt("Enter the field handle for storing $label values:", [
            'required' => true,
            'validator' => fn($handle) => (
                preg_match($handlePattern, $handle) &&
                $fieldsService->getFieldByHandle($handle) !== null
            ),
        ]);
        return $fieldsService->getFieldByHandle($handle);
    }

    /**
     * @return void
     */
    private function _migrateShippingZones(): void
    {
        $shippingZones = (new Query())
            ->select(['id', 'v3zipCodeConditionFormula', 'v3isCountryBased'])
            ->from(['{{%commerce_shippingzones}}'])
            ->limit(null)
            ->all();

        $countryIdsByZoneId = (new Query())
            ->select(['countryId'])
            ->from(['{{%commerce_shippingzone_countries}}'])
            ->indexBy('shippingZoneId')
            ->column();

        $stateIdsByZoneId = (new Query())
            ->select(['stateId'])
            ->from(['{{%commerce_shippingzone_states}}'])
            ->indexBy('shippingZoneId')
            ->column();

        $done = 0;
        Console::startProgress();
        foreach ($shippingZones as $shippingZone) {
            $zoneId = $shippingZone['id'];

            // If we have a zone model with that ID (which we should)
            if ($model = Plugin::getInstance()->getShippingZones()->getShippingZoneById($zoneId)) {
                // Get the condition (which will create if none exists)
                $condition = $model->getCondition();
                $newRules = [];

                // do we have a zip code formula
                if ($shippingZone['v3zipCodeConditionFormula']) {
                    $postalCodeCondition = new PostalCodeFormulaConditionRule();
                    $postalCodeCondition->value = $shippingZone['v3zipCodeConditionFormula'];
                    $newRules[] = $postalCodeCondition;
                }

                // do we have a country based zone
                if ($shippingZone['isCountryBased'] ?? false) {
                    $countryIds = $countryIdsByZoneId[$zoneId];
                    $countryCodes = [];
                    foreach ($countryIds as $countryId) {
                        $countryCodes[] = $this->_countryCodesByV3CountryId[$countryId];
                    }

                    $countryCondition = new CountryConditionRule();
                    $countryCondition->values = $countryCodes;
                    $newRules[] = $countryCondition;
                } else {
                    $stateIds = $stateIdsByZoneId[$zoneId];
                    $codes = [];
                    foreach ($stateIds as $stateId) {
                        $codes[] = $this->_administrativeAreaByV3StateId[$stateId];
                    }

                    $administrativeAreaCondition = new AdministrativeAreaConditionRule();
                    $administrativeAreaCondition->values = $codes;
                    $newRules[] = $administrativeAreaCondition;
                }

                $condition->setConditionRules($newRules);
                Plugin::getInstance()->getShippingZones()->saveShippingZone($model, false);
            }
        }
    }

    /**
     * @return void
     */
    private function _migrateTaxZones(): void
    {
        $taxZones = (new Query())
            ->select(['id', 'v3zipCodeConditionFormula', 'v3isCountryBased'])
            ->from(['{{%commerce_taxzones}}'])
            ->limit(null)
            ->all();

        $countryIdsByZoneId = (new Query())
            ->select(['countryId'])
            ->from(['{{%commerce_taxzone_countries}}'])
            ->indexBy('taxZoneId')
            ->column();

        $stateIdsByZoneId = (new Query())
            ->select(['stateId'])
            ->from(['{{%commerce_taxzone_states}}'])
            ->indexBy('taxZoneId')
            ->column();

        $done = 0;
        Console::startProgress();
        foreach ($taxZones as $taxZone) {
            $zoneId = $taxZone['id'];

            // If we have a zone model with that ID (which we should)
            if ($model = Plugin::getInstance()->getTaxZones()->getTaxZoneById($zoneId)) {
                // Get the condition (which will create if none exists)
                $condition = $model->getCondition();
                $newRules = [];

                // do we have a zip code formula
                if ($taxZone['v3zipCodeConditionFormula']) {
                    $postalCodeCondition = new PostalCodeFormulaConditionRule();
                    $postalCodeCondition->value = $taxZone['v3zipCodeConditionFormula'];
                    $newRules[] = $postalCodeCondition;
                }

                // do we have a country based zone
                if ($taxZone['isCountryBased'] ?? false) {
                    $countryIds = $countryIdsByZoneId[$zoneId];
                    $countryCodes = [];
                    foreach ($countryIds as $countryId) {
                        $countryCodes[] = $this->_countryCodesByV3CountryId[$countryId];
                    }

                    $countryCondition = new CountryConditionRule();
                    $countryCondition->values = $countryCodes;
                    $newRules[] = $countryCondition;
                } else {
                    $stateIds = $stateIdsByZoneId[$zoneId];
                    $codes = [];
                    foreach ($stateIds as $stateId) {
                        $codes[] = $this->_administrativeAreaByV3StateId[$stateId];
                    }

                    $administrativeAreaCondition = new AdministrativeAreaConditionRule();
                    $administrativeAreaCondition->values = $codes;
                    $newRules[] = $administrativeAreaCondition;
                }

                $condition->setConditionRules($newRules);
                Plugin::getInstance()->getTaxZones()->saveTaxZone($model, false);
            }
        }
    }

    /**
     * @return array
     */
    private function _countryCodesByV3CountryId(): array
    {
        return (new Query())
            ->select(['iso'])
            ->from(['{{%commerce_countries}}'])
            ->indexBy('id')
            ->column();
    }

    /**
     * @return array
     */
    private function _administrativeAreaByV3StateId(): array
    {
        return (new Query())
            ->select(['abbreviation'])
            ->from(['{{%commerce_states}}'])
            ->indexBy('id')
            ->column();
    }

    /**
     * @return void
     * @throws \yii\db\Exception
     */
    private function _migrateOrderAddresses()
    {
        $orderAddresses = (new Query())
            ->select(['id', 'v3shippingAddressId', 'v3billingAddressId', 'v3estimatedShippingAddressId', 'v3estimatedBillingAddressId'])
            ->from(['{{%commerce_orders}}'])
            ->limit(null);

        $totalAddresses = $orderAddresses->count();
        $done = 0;
        Console::startProgress($done, $totalAddresses);
        foreach ($orderAddresses->each() as $orderAddress) {
            if (!$orderAddress['v3shippingAddressId'] && !$orderAddress['v3billingAddressId'] && !$orderAddress['v3estimatedShippingAddressId'] && !$orderAddress['v3estimatedBillingAddressId']) {
                continue;
            }

            $orderId = $orderAddress['id'];
            $v3shippingAddressId = $orderAddress['v3shippingAddressId'];
            $v3billingAddressId = $orderAddress['v3billingAddressId'];
            $v3estimatedShippingAddressId = $orderAddress['v3estimatedShippingAddressId'];
            $v3estimatedBillingAddressId = $orderAddress['v3estimatedBillingAddressId'];

            $update = [];
            if ($v3shippingAddressId) {
                $update['shippingAddressId'] = $this->_addressIdByV3AddressId[$v3shippingAddressId];
            }

            if ($v3billingAddressId) {
                $update['billingAddressId'] = $this->_addressIdByV3AddressId[$v3billingAddressId];
            }

            if ($v3estimatedShippingAddressId) {
                $update['estimatedShippingAddressId'] = $this->_addressIdByV3AddressId[$v3estimatedShippingAddressId];
            }

            if ($v3estimatedBillingAddressId) {
                $update['estimatedBillingAddressId'] = $this->_addressIdByV3AddressId[$v3estimatedBillingAddressId];
            }

            Craft::$app->getDb()->createCommand()->update(Table::ORDERS,
                $update,
                ['id' => $orderId]
            )->execute();
            Console::updateProgress($done++, $totalAddresses);
        }
        Console::endProgress();
    }

    /**
     * @return void
     */
    private function _migrateAddresses()
    {
        $continue = (new Query())
            ->select('*')
            ->from(['a' => '{{%addresses}}'])
            ->limit(null)
            ->exists();

        if ($continue) {
            $this->_addressIdByV3AddressId = (new Query())
                ->select(['id', 'v4addressId'])
                ->from('{{%commerce_addresses}}')
                ->pairs();
            ArrayHelper::removeValue($this->_addressIdByV3AddressId, null); //  should not be null but just in case.
            return;
        }

        $addresses = (new Query())
            ->select('*')
            ->from(['a' => '{{%commerce_addresses}}'])
            ->limit(null);

        $totalAddresses = $addresses->count();
        $done = 0;
        Console::startProgress($done, $totalAddresses, 'Migrating addresses to elements...');
        foreach ($addresses->each() as $address) {
            $address = $this->_createAddress($address);
            $this->_addressIdByV3AddressId[$address['id']] = $address->id;
            Craft::$app->getDb()->createCommand()->update('{{%commerce_addresses}}',
                ['v4addressId' => $address->id],
                ['id' => $address['id']]
            )->execute();
            Console::updateProgress($done++, $totalAddresses);
        }
        Console::endProgress();
    }

    /**
     * Map all address IDs from
     */
    public function _migrateUserPrimaryAddressIds(): void
    {
        $customerPrimaryAddresses = (new Query())
            ->select(['[[c.id]]', '[[c.v3primaryBillingAddressId]], [[c.v3primaryShippingAddressId]]'])
            ->from(['c' => '{{%commerce_customers}}'])
            ->where(['not', ['v3primaryBillingAddressId' => null]])
            ->orWhere(['not', ['v3primaryShippingAddressId' => null]])
            ->limit(null);

        $done = 0;
        $total = $customerPrimaryAddresses->count();
        Console::startProgress($done, $total, 'Migrating primary addresses...');
        foreach ($customerPrimaryAddresses->each() as $customer) {
            if ($customer['v3primaryShippingAddressId'] && $customer['id'] && $shippingId = $this->_addressIdByV3AddressId[$customer['v3primaryShippingAddressId']]) {
                Craft::$app->getDb()->createCommand()->update(Table::CUSTOMERS,
                    ['primaryShippingAddressId' => $shippingId],
                    ['customerId' => $this->userIdsByv3CustomerId[$customer['id']]] // guest customer has no userId
                )->execute();
            }
            if ($customer['v3primaryBillingAddressId'] && $customer['id'] && $billingId = $this->_addressIdByV3AddressId[$customer['v3primaryBillingAddressId']]) {
                Craft::$app->getDb()->createCommand()->update(Table::CUSTOMERS,
                    ['primaryBillingAddressId' => $billingId],
                    ['customerId' => $this->userIdsByv3CustomerId[$customer['id']]] // guest customer has no userId
                )->execute();
            }
            Console::updateProgress($done++, $total);
        }
        Console::endProgress();
    }

    /**
     * Creates an Address element from previous address data and returns the ID
     */
    private function _createAddress($data, ?int $ownerId = null): Address
    {
        $address = new Address();
        if ($ownerId) {
            $address->ownerId = $ownerId;
        }
        $address->title = $data['label'] ?: 'Address';
        $address->fullName = $data['firstName'] . ' ' . $data['lastName'];
        $address->addressLine1 = $data['address1'];
        $address->addressLine2 = $data['address2'];
        $address->countryCode = $this->_countryCodesByV3CountryId[$data['countryId']] ?? 'US'; //  get from mapping
        $address->administrativeArea = $this->_administrativeAreaByV3StateId[$data['stateId']] ?? null; //  get from mapping
        $address->postalCode = $data['zipCode'];
        $address->locality = $data['city'];
        $address->dependentLocality = '';
        $address->organization = $data['businessName'];
        $address->organizationTaxId = $data['businessTaxId'];

        // Set fields that were created and mapped from old data
        foreach ($this->_oldAddressHandleToNewCustomFieldHandle as $oldAttribute => $customFieldHandle) {
            $address->setFieldValue($customFieldHandle, $data[$oldAttribute] ?: '');
        }

        $address->dateCreated = DateTimeHelper::toDateTime($data['dateCreated']);
        $address->dateUpdated = DateTimeHelper::toDateTime($data['dateUpdated']);
        Craft::$app->getElements()->saveElement($address, false, false, false);

        // Update global mapping.
        // Will be used by primary shipping and billing replacement on customers
        $this->_addressIdByV3AddressId[$data['id']] = $address->id;

        return $address;
    }

    public function _migrateCustomers(): void
    {

        // Skip this if it has already run (the customerId column would not be empty)
        $continue = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['[[orders.customerId]]'])
            ->where(['[[orders.customerId]]' => null])
            ->andWhere(['not', ['[[orders.email]]' => null]])
            ->andWhere(['not', ['[[orders.email]]' => '']])
            ->exists();

        if (!$continue) {

            // Still need to populate this for speed up in other functions
            $this->userIdsByv3CustomerId = (new Query())
                ->select(['id', 'customerId'])
                ->from('{{%commerce_customers}}')
                ->pairs();
            ArrayHelper::removeValue($this->userIdsByv3CustomerId, null); //  should be null but just in case.
            return;
        }

        // This gets customerIds that don't have any orders
        $orphanedCustomerIds = (new Query())->from('{{%commerce_customers}} customers')
            ->select(['[[customers.id]]'])
            ->leftJoin('{{%commerce_orders}} orders', '[[customers.id]] = [[orders.v3customerId]]')
            ->where(['[[orders.v3customerId]]' => null])
            ->column();
        // Delete all customers that don't have any orders
        Craft::$app->getDb()->createCommand()
            ->delete(Table::CUSTOMERS, ['id' => $orphanedCustomerIds])
            ->execute();

        // Remove guest customer's primary address IDs if the customer is not related to a user
        // Guest users no longer have an address book anyway.
        Craft::$app->getDb()->createCommand()->update(Table::CUSTOMERS,
            ['v3primaryShippingAddressId' => null, 'v3primaryBillingAddressId' => null],
            ['v3userId' => null] // guest customer has no userId
        )->execute();

        // This gets us a unique list of order emails
        $allEmails = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['[[orders.email]]'])
            ->distinct()
            ->where(['not', ['[[orders.email]]' => null]])
            ->andWhere(['not', ['[[orders.email]]' => '']]);

        $totalEmails = $allEmails->count();
        $done = 0;
        Console::startProgress($done, $totalEmails, 'Migrating customers...');
        foreach ($allEmails->each() as $email) {
            $email = $email['email'];
            $user = Craft::$app->getUsers()->ensureUserByEmail($email);

            // Get the original customer for this user
            $customerId = (new Query())->from('{{%commerce_customers}} customers')
                ->select(['[[customers.id]]'])
                ->where(['[[customers.v3userId]]' => $user->id])
                ->scalar();

            // No customer for this user? They could have been a guest customer so let's create them a new customer record
            if (!$customerId) {
                $customer = new Customer();
                $customer->customerId = $user->id;
                $customer->save(false);
                $customerId = $customer->id;
            }

            $this->userIdsByv3CustomerId[$customerId] = $user->id;

            Craft::$app->getDb()->createCommand()
                ->update(Table::CUSTOMERS,
                    ['customerId' => $user->id],
                    ['id' => $customerId]
                )->execute();

            Craft::$app->getDb()->createCommand()
                ->update(Table::ORDERS,
                    ['customerId' => $user->id, 'v3customerId' => $customerId],
                    ['email' => $email]
                )->execute();

            Console::updateProgress($done++, $totalEmails);
        }

        // Clear out orphaned customers again now that we have consolidated them to emails
        // This gets customerIds that don't have any orders
        $orphanedCustomerIds = (new Query())->from('{{%commerce_customers}} customers')
            ->select(['[[customers.id]]'])
            ->leftJoin('{{%commerce_orders}} orders', '[[customers.id]] = [[orders.v3customerId]]')
            ->where(['[[orders.v3customerId]]' => null])
            ->column();

        // Delete all orphaned customers addresses
        Craft::$app->getDb()->createCommand()
            ->delete('{{%commerce_customers_addresses}}', ['customerId' => $orphanedCustomerIds])
            ->execute();

        // Delete all customers that don't have any orders
        Craft::$app->getDb()->createCommand()
            ->delete(Table::CUSTOMERS, ['id' => $orphanedCustomerIds])
            ->execute();

        Console::endProgress(false, false);
    }

    /**
     * @return void
     */
    private function _migrateStoreLocation(): void
    {
        $storeLocationQuery = (new Query())
            ->select('*')
            ->from(['{{%commerce_addresses}}'])
            ->where(['isStoreLocation' => true])
            ->one();

        $store = Store::find()->one();
        if ($store === null) {
            $store = new Store();
            $store->save();
        }

        if ($storeLocationQuery) {
            $store->locationAddressId = $this->_createAddress($storeLocationQuery)->id;
            $store->save();
        }
    }
}
