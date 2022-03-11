<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\base\FieldInterface;
use craft\commerce\console\Controller;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\addresses\PostalCodeFormulaConditionRule;
use craft\commerce\Plugin;
use craft\commerce\records\Customer;
use craft\commerce\records\Store;
use craft\db\Query;
use craft\db\Table as CraftTable;
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
use craft\helpers\Db;
use craft\helpers\MigrationHelper;
use craft\validators\HandleValidator;
use yii\console\ExitCode;
use yii\db\Schema;

/**
 * Command to be run once upgraded to Commerce 4.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class UpgradeController extends Controller
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
    public $defaultAction = 'run';

    /**
     * The list of fields that can be converted to PlainText fields
     *
     * @var array<string, string>
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
        'notes' => 'Notes'
    ];

    /**
     * @return array
     * @throws \yii\db\Exception
     */
    private function _getOrphanedCustomerIds(): array
    {
        // This gets customerIds that don't have any orders
        return (new Query())->from('{{%commerce_customers}} customers')
            ->select(['[[customers.id]]'])
            ->leftJoin('{{%commerce_orders}} orders', '[[customers.id]] = [[orders.v3customerId]]')
            ->where(['[[orders.v3customerId]]' => null])
            ->column();
    }


    /**
     * These columns are needed for the migration and can be dropped after
     *
     * @var array<array{table: string, column: string}>
     */
    private array $_v3droppableColumns = [
        ['table' => '{{%commerce_taxzones}}', 'column' => 'v3isCountryBased'],
        ['table' => '{{%commerce_shippingzones}}', 'column' => 'v3isCountryBased'],
        ['table' => '{{%commerce_taxzones}}', 'column' => 'v3zipCodeConditionFormula'],
        ['table' => '{{%commerce_shippingzones}}', 'column' => 'v3zipCodeConditionFormula'],

        ['table' => '{{%commerce_orders}}', 'column' => 'v3customerId'],
        ['table' => '{{%commerce_orders}}', 'column' => 'v3billingAddressId'],
        ['table' => '{{%commerce_orders}}', 'column' => 'v3shippingAddressId'],
        ['table' => '{{%commerce_orders}}', 'column' => 'v3estimatedBillingAddressId'],
        ['table' => '{{%commerce_orders}}', 'column' => 'v3estimatedShippingAddressId'],

        ['table' => '{{%commerce_customers}}', 'column' => 'v3userId'],
        ['table' => '{{%commerce_customers}}', 'column' => 'v3primaryBillingAddressId'],
        ['table' => '{{%commerce_customers}}', 'column' => 'v3primaryShippingAddressId'],

        ['table' => '{{%commerce_customer_discountuses}}', 'column' => 'v3customerId'],
        ['table' => '{{%commerce_orderhistories}}', 'column' => 'v3customerId'],
        ['table' => '{{%commerce_customer_discountuses}}', 'column' => 'v3customerId'],
    ];

    private array $_v3tables = [
        '{{%commerce_addresses}}',
        '{{%commerce_customers_addresses}}',
        '{{%commerce_countries}}',
        '{{%commerce_states}}',
        '{{%commerce_shippingzone_countries}}',
        '{{%commerce_shippingzone_states}}',
        '{{%commerce_taxzone_countries}}',
        '{{%commerce_taxzone_states}}',
    ];

    /**
     * This stores the mapping from old field to new custom field handle
     *
     * @var array<string, string>
     */
    private array $_oldAddressFieldToNewCustomFieldHandle = [];

    /**
     * All countries
     *
     * @var array<int, array{
     *     id: string,
     *     name: string,
     *     iso: string,
     *     isStateRequired: string,
     *     sortOrder: string,
     *     enabled: string,
     *     dateCreated: string,
     *     dateUpdated: string,
     *     uid: string
     *     }> $_allCountriesByV3CountryId
     */
    private array $_allCountriesByV3CountryId = [];

    /**
     * @var array<int, array{
     *     id: string,
     *     countryId: string,
     *     name: string,
     *     abbreviation: string,
     *     sortOrder: string,
     *     enabled: string,
     *     dateCreated: string,
     *     dateUpdated: string,
     *     uid: string
     *     }>
     */
    private array $_allStatesByV3StateId = [];
    private array $_addressIdByV3AddressId = [];
    private array $_userIdsByv3CustomerId = [];

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
     * @param $action
     * @return bool
     */
    public function beforeAction($action): bool
    {
        /**
         * Check to make sure they are not doing this before running standard migrations.
         */
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '<')) {
            $this->stdout("You must run `craft migrate/all` command first.\n" . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        /**
         * Check to make sure they have all tables still around after the migration.
         * These will be deleted after this migration occurs. This is also a way to
         * make sure they have not run this more than once.
         */
        foreach ($this->_v3tables as $table) {
            $cleanTableName = str_replace(['{{%', '}}'], '', $table);
            if (!Craft::$app->getDb()->tableExists($table)) {
                $this->stdout('The `' . $cleanTableName . '` table no longer exists, can not proceed with v4 migration.' . PHP_EOL . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        // List of countries in the system at the moment.
        // Note: This list will contain the custom countries, but we will replace the 'iso' codes in this array
        // when we prompt the user for the mapping to real iso codes.
        $this->_allCountriesByV3CountryId = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_countries}}'])
            ->indexBy('id')
            ->all();

        // Collect all custom countries that were set up that are not in the standard country repository list.
        $customCountriesByV3CountryId = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_countries}}'])
            ->where(['not', ['iso' => array_keys(Craft::$app->getAddresses()->getCountryRepository()->getList())]])
            ->indexBy('id')
            ->all();


        // After this process we should have not custom countries in out countries list.
        foreach ($customCountriesByV3CountryId as $customCountry) {
            $this->stdout(sprintf("Found invalid custom country: %s (%s)\n", $customCountry['name'], $customCountry['iso']));
            $this->stdout("We need to map this to a real country. All addresses and zones will be updated.\n");
            $this->stdout("See: https://www.iban.com/country-codes\n");
            $validCountries = array_keys(Craft::$app->getAddresses()->getCountryRepository()->getList());
            $countryCode = $this->prompt('Please enter a valid Alpha-2 Country code:', [
                'required' => true,
                'validator' => function($countryCode) use ($validCountries) {
                    if (in_array($countryCode, $validCountries, false)) {
                        return true;
                    }
                    $this->stdout("Not a valid Alpha-2 Country code.\n");
                    return false;
                },
                'default' => 'US',
            ]);
            // Update our list of countries, replacing the old custom ISO with the new valid ISO.
            $this->_allCountriesByV3CountryId[$customCountry['id']]['iso'] = strtoupper($countryCode);
            $this->stdout("\n\n");
        }

        // Collect all the standard states that were set up in v3.
        $this->_allStatesByV3StateId = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_states}}'])
            ->indexBy('id')
            ->all();

        // Filter out the address columns we don't need to migrate to custom fields
        $this->neededCustomAddressFields = array_filter($this->neededCustomAddressFields, static function($fieldHandle) {
            return (new Query())
                ->select($fieldHandle)
                ->where(['not', [$fieldHandle => null]])
                ->andWhere(['not', [$fieldHandle => '']])
                ->from(['{{%commerce_addresses}}'])
                ->exists();
        }, ARRAY_FILTER_USE_KEY);

        return parent::beforeAction($action);
    }

    /**
     * Runs the data migration
     *
     * @throws \Throwable
     */
    public function actionRun(): int
    {
        $db = Craft::$app->getDb();

        $this->stdout("\nEnsuring address data migration field locations...\n");
        $this->_migrateAddressCustomFields();

        $this->stdout("Creating a user for every customer...\n");
        $this->_migrateCustomers();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating Addresses...\n");
        $this->_migrateAddresses();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating Order Addresses...\n");
        $this->_migrateOrderAddresses();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating User Addresses Books...\n");
        $this->_migrateUserAddressBook();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating Store Location...\n");
        $this->_migrateStore();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating Shipping Zones...\n");
        $this->_migrateShippingZones();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating Shipping Zones...\n");
        $this->_migrateTaxZones();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating Tax Zones...\n");
        $this->_migrateTaxZones();
        $this->stdout("\nDone.\n\n");

        $this->stdout("Migrating order history user...\n");
        $this->_migrateOrderHistoryUser();
        $this->stdout("\nDone.\n\n");


        $this->stdout("Cleaning up...\n");
        foreach ($this->_v3tables as $table) {
            Db::dropAllForeignKeysToTable($table);
            MigrationHelper::dropAllForeignKeysOnTable($table);
            Craft::$app->getDb()->createCommand()->dropTableIfExists($table)->execute();
        }

        foreach ($this->_v3droppableColumns as ['table' => $table, 'column' => $column]) {
            if ($db->columnExists($table, $column)) {
                Craft::$app->getDb()->createCommand()->dropColumn($table, $column)->execute();
            }
        }
        $this->stdout("\nDone.\n\n");


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
                $this->_oldAddressFieldToNewCustomFieldHandle[$oldAttribute] = $field->handle;
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
            $field->groupId = ArrayHelper::firstValue(Craft::$app->getFields()->getAllGroups())->id;
            $field->columnType = Schema::TYPE_STRING;
            $field->handle = $this->prompt('Field handle:', [
                'required' => true,
                'validator' => function($handle) use ($handlePattern, $fieldsService) {
                    if (!preg_match($handlePattern, $handle)) {
                        return false;
                    }
                    if (in_array($handle, ['ancestors', 'archived', 'attributeLabel', 'attributes', 'behavior', 'behaviors', 'canSetProperties', 'children', 'contentTable', 'dateCreated', 'dateUpdated', 'descendants', 'enabled', 'enabledForSite', 'error', 'errors', 'errorSummary', 'fieldValue', 'fieldValues', 'hasMethods', 'id', 'language', 'level', 'localized', 'lft', 'link', 'localized', 'name', 'next', 'nextSibling', 'owner', 'parent', 'parents', 'postDate', 'prev', 'prevSibling', 'ref', 'rgt', 'root', 'scenario', 'searchScore', 'siblings', 'site', 'slug', 'sortOrder', 'status', 'title', 'uid', 'uri', 'url', 'username'])) {
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
     *
     */
    private function _migrateShippingZones(): void
    {
        $shippingZones = (new Query())
            ->select(['id', 'v3zipCodeConditionFormula', 'v3isCountryBased'])
            ->from(['{{%commerce_shippingzones}}'])
            ->limit(null)
            ->all();

        $done = 0;
        Console::startProgress($done, count($shippingZones));
        foreach ($shippingZones as $shippingZone) {
            $zoneId = $shippingZone['id'];

            // If we have a zone model with that ID (which we should)
            if ($model = Plugin::getInstance()->getShippingZones()->getShippingZoneById((int)$zoneId)) {
                // Get the condition (which will create if none exists)
                $condition = $model->getCondition();
                $newRules = [];

                // do we have a zip code formula
                if ($shippingZone['v3zipCodeConditionFormula']) {
                    $postalCodeCondition = new PostalCodeFormulaConditionRule();
                    $postalCode = str_replace('zipCode', 'postalCode', $shippingZone['v3zipCodeConditionFormula']);
                    $postalCodeCondition->value = $postalCode;
                    $newRules[] = $postalCodeCondition;
                }

                // do we have a country based zone
                if ($shippingZone['v3isCountryBased'] ?? false) {
                    $countryIds = (new Query())
                        ->select(['countryId'])
                        ->from(['{{%commerce_shippingzone_countries}}'])
                        ->where(['shippingZoneId' => $zoneId])
                        ->column();

                    $countryCodes = [];
                    foreach ($countryIds as $countryId) {
                        $countryCodes[] = $this->_allCountriesByV3CountryId[$countryId]['iso'];
                    }

                    $countryCondition = new CountryConditionRule();
                    $countryCondition->values = $countryCodes;
                    $newRules[] = $countryCondition;
                } else {
                    $statesIds = (new Query())
                        ->select(['stateId'])
                        ->from(['{{%commerce_shippingzone_states}}'])
                        ->where(['shippingZoneId' => $zoneId])
                        ->column();

                    $codes = [];
                    foreach ($statesIds as $stateId) {
                        $codes[] = $this->_allStatesByV3StateId[$stateId]['abbreviation'];
                    }

                    $administrativeAreaCondition = new AdministrativeAreaConditionRule();
                    $administrativeAreaCondition->setValues($codes);
                    $newRules[] = $administrativeAreaCondition;
                }

                $condition->setConditionRules($newRules);
                $model->setCondition($condition);
                Plugin::getInstance()->getShippingZones()->saveShippingZone($model, false);
            }
            Console::updateProgress($done++, count($shippingZones));
        }
        Console::endProgress(count($shippingZones) . ' shipping zones migrated.');
    }

    /**
     * @return void
     *
     */
    private function _migrateTaxZones(): void
    {
        $taxZones = (new Query())
            ->select(['id', 'v3zipCodeConditionFormula', 'v3isCountryBased'])
            ->from(['{{%commerce_taxzones}}'])
            ->limit(null)
            ->all();

        $done = 0;
        Console::startProgress($done, count($taxZones));
        foreach ($taxZones as $taxZone) {
            $zoneId = $taxZone['id'];

            // If we have a zone model with that ID (which we should)
            if ($model = Plugin::getInstance()->getTaxZones()->getTaxZoneById((int)$zoneId)) {
                // Get the condition (which will create if none exists)
                $condition = $model->getCondition();
                $newRules = [];

                // do we have a zip code formula
                if ($taxZone['v3zipCodeConditionFormula']) {
                    $postalCodeCondition = new PostalCodeFormulaConditionRule();
                    $postalCode = str_replace('zipCode', 'postalCode', $taxZone['v3zipCodeConditionFormula']);
                    $postalCodeCondition->value = $postalCode;
                    $newRules[] = $postalCodeCondition;
                }

                // do we have a country based zone
                if ($taxZone['v3isCountryBased'] ?? false) {
                    $countryIds = (new Query())
                        ->select(['countryId'])
                        ->from(['{{%commerce_taxzone_countries}}'])
                        ->where(['taxZoneId' => $zoneId])
                        ->column();

                    $countryCodes = [];
                    foreach ($countryIds as $countryId) {
                        $countryCodes[] = $this->_allCountriesByV3CountryId[$countryId]['iso'];
                    }

                    $countryCondition = new CountryConditionRule();
                    $countryCondition->values = $countryCodes;
                    $newRules[] = $countryCondition;
                } else {
                    $statesIds = (new Query())
                        ->select(['stateId'])
                        ->from(['{{%commerce_taxzone_states}}'])
                        ->where(['taxZoneId' => $zoneId])
                        ->column();

                    $codes = [];
                    foreach ($statesIds as $stateId) {
                        $codes[] = $this->_allStatesByV3StateId[$stateId]['abbreviation'];
                    }

                    $administrativeAreaCondition = new AdministrativeAreaConditionRule();
                    $administrativeAreaCondition->setValues($codes);
                    $newRules[] = $administrativeAreaCondition;
                }

                $condition->setConditionRules($newRules);
                $model->setCondition($condition);
                Plugin::getInstance()->getTaxZones()->saveTaxZone($model, false);
            }
            Console::updateProgress($done++, count($taxZones));
        }
        Console::endProgress(count($taxZones) . ' tax zones migrated.');
    }

    /**
     * @return void
     */
    public function _migrateOrderHistoryUser()
    {
        $orderHistories = (new Query())
            ->select(['id', 'v3customerId'])
            ->from(['{{%commerce_orderhistories}}'])
            ->limit(null)
            ->all();

        $done = 0;
        Console::startProgress($done, count($orderHistories));
        foreach ($orderHistories as $history) {
            $userId = null;
            $v3customerId = $history['v3customerId'];
            if ($v3customerId) {
                $userId = $this->_userIdsByv3CustomerId[$v3customerId] ?? null;
            }
            // Customer may have been deleted, if so, use the admin user as the history record
            if ($userId === null) {
                $userId = User::find()->admin(true)->one()->id;
            }

            Craft::$app->getDb()->createCommand()->update(Table::ORDERHISTORIES,
                ['userId' => $userId],
                ['id' => $history['id']]
            )->execute();

            Console::updateProgress($done++, count($orderHistories));
        }
        Console::endProgress(count($orderHistories) . ' order history user migrated.');
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
    private function _migrateUserAddressBook()
    {
        $addressTable = CraftTable::ADDRESSES;
        $previousAddressTable = '{{%commerce_addresses}}';
        $customerAddressTable = '{{%commerce_customers_addresses}}';
        $customersTable = '{{%commerce_customers}}';

        /**
         * Make all address elements have the owner ID of the user
         */
        $sql = <<<SQL
update $addressTable [[a]]
inner join $previousAddressTable [[pa]] on
    [[pa.v4addressId]] = [[a.id]]
inner join $customerAddressTable [[ca]] on
    [[ca.addressId]] = [[pa.id]]
inner join $customersTable [[cu]] on
    [[cu.id]] = [[ca.customerId]]
set [[a.ownerId]] = [[cu.customerId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();


        /**
         * Migrates the primary billing address ID
         */
        $sql = <<<SQL
update $customersTable [[c]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[c.v3primaryBillingAddressId]]
set [[c.primaryBillingAddressId]] = [[pa.v4addressId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        /**
         * Migrates the primary shipping ID
         */
        $sql = <<<SQL
update $customersTable [[c]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[c.v3primaryShippingAddressId]]
set [[c.primaryShippingAddressId]] = [[pa.v4addressId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();
    }

    /**
     * @return void
     * @throws \yii\db\Exception
     */
    private function _migrateOrderAddresses(): void
    {
        $addressTable = CraftTable::ADDRESSES;
        $previousAddressTable = '{{%commerce_addresses}}';
        $ordersTable = Table::ORDERS;

        // Order Shipping address
        $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3shippingAddressId]]
set [[o.shippingAddressId]] = [[pa.v4addressId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Order Billing address
        $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3billingAddressId]]
set [[o.billingAddressId]] = [[pa.v4addressId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Order Estimated shipping address
        $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3estimatedBillingAddressId]]
set [[o.estimatedBillingAddressId]] = [[pa.v4addressId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Order Estimated billing address
        $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3estimatedBillingAddressId]]
set [[o.estimatedBillingAddressId]] = [[pa.v4addressId]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();


        /**
         * Make all order shipping address elements have the owner ID of the order
         */
        $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.shippingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        /**
         * Make all order billing address elements have the owner ID of the order
         */
        $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.billingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        /**
         * Make all order estimated billing address elements have the owner ID of the order
         */
        $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.estimatedBillingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();

        /**
         * Make all order estimated shipping address elements have the owner ID of the order
         */
        $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.estimatedShippingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        Craft::$app->getDb()->createCommand($sql)->execute();
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
        Console::startProgress($done, $totalAddresses);
        foreach ($addresses->each() as $address) {
            $address = $this->_createAddress($address);
            Console::updateProgress($done++, $totalAddresses);
        }
        foreach ($this->_addressIdByV3AddressId as $v3AddressId => $addressId) {
            Craft::$app->getDb()->createCommand()->update('{{%commerce_addresses}}',
                ['v4addressId' => $addressId],
                ['id' => $v3AddressId]
            )->execute();
        }

        Console::endProgress($totalAddresses . ' addresses migrated.');
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
        $address->countryCode = $this->_allCountriesByV3CountryId[$data['countryId']]['iso'] ?? 'US';

        // Was a stateId supplied, if so look it up in the mapping and
        if ($data['stateId']) {
            $address->administrativeArea = $this->_allStatesByV3StateId[$data['stateId']]['abbreviation'] ?? null;
        } else {
            $address->administrativeArea = $data['stateName'] ?? null;
        }
        $address->postalCode = $data['zipCode'];
        $address->locality = $data['city'];
        $address->dependentLocality = '';
        $address->organization = $data['businessName'];
        $address->organizationTaxId = $data['businessTaxId'];

        // Set fields that were created and mapped from old data
        foreach ($this->_oldAddressFieldToNewCustomFieldHandle as $oldAttribute => $customFieldHandle) {
            if ($data[$oldAttribute]) {
                $address->setFieldValue($customFieldHandle, $data[$oldAttribute]);
            }
        }

        $address->dateCreated = DateTimeHelper::toDateTime($data['dateCreated']);
        $address->dateUpdated = DateTimeHelper::toDateTime($data['dateUpdated']);
        Craft::$app->getElements()->saveElement($address, false, false, false);

        // Update global mapping.
        // Will be used by primary shipping and billing replacement on customers
        $this->_addressIdByV3AddressId[$data['id']] = $address->id;

        return $address;
    }

    /**
     * @return void
     */
    public function _migrateCustomers(): void
    {
        // Skip this if it has already run (the customerId column would not be empty)
        $exists = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['[[orders.customerId]]'])
            ->where(['[[orders.customerId]]' => null])
            ->andWhere(['not', ['[[orders.email]]' => null]])
            ->andWhere(['not', ['[[orders.email]]' => '']])
            ->exists();

        if (!$exists) {
            // Still need to populate this as it is used by other functions
            $this->_userIdsByv3CustomerId = (new Query())
                ->select(['id', 'customerId'])
                ->from('{{%commerce_customers}}')
                ->pairs();
            return;
        }

        $orphanedCustomerIds = $this->_getOrphanedCustomerIds();
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
        Console::startProgress($done, $totalEmails);
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

            $this->_userIdsByv3CustomerId[$customerId] = $user->id;

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

        $orphanedCustomerIds = $this->_getOrphanedCustomerIds();
        // Clear out orphaned customers again now that we have consolidated them to emails
        Craft::$app->getDb()->createCommand()
            ->delete('{{%commerce_customers_addresses}}', ['customerId' => $orphanedCustomerIds])
            ->execute();
        // Delete all customers that don't have any orders
        Craft::$app->getDb()->createCommand()
            ->delete(Table::CUSTOMERS, ['id' => $orphanedCustomerIds])
            ->execute();

        Console::endProgress($totalEmails . ' customers migrated.');
    }

    /**
     * @return void
     */
    private function _migrateStore(): void
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

        $storeModel = Plugin::getInstance()->getStore()->getStore();

        if ($storeLocationQuery) {
            $storeModel->locationAddressId = $this->_createAddress($storeLocationQuery)->id;
        }

        $storeModel->countries = (new Query())
            ->select(['iso'])
            ->from(['{{%commerce_countries}}'])
            ->where(['enabled' => true])
            ->andWhere(['iso' => array_keys(Craft::$app->getAddresses()->getCountryRepository()->getList())])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->column();

        $condition = $storeModel->getMarketAddressCondition();
        $rule = new CountryConditionRule();
        $rule->values = $storeModel->countries;
        $condition->addConditionRule($rule);
        $storeModel->setMarketAddressCondition($condition);

        Plugin::getInstance()->getStore()->saveStore($storeModel);
    }

    /**
     * @param mixed $state
     * @return string
     */
    private function getStateValueString(mixed $state): string
    {
        return $state['countryIso'] . '-' . $state['stateIso'];
    }
}
