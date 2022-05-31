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
use craft\errors\OperationAbortedException;
use craft\fieldlayoutelements\addresses\OrganizationField;
use craft\fieldlayoutelements\addresses\OrganizationTaxIdField;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\FullNameField;
use craft\fields\PlainText;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use Throwable;
use yii\console\ExitCode;
use yii\db\Exception;
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
        'notes' => 'Notes',
    ];

    /**
     * @return array
     * @throws Exception
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

    private bool $_allowAdminChanges;
    private FieldLayout $_addressFieldLayout;

    public function init(): void
    {
        $this->_allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $this->_addressFieldLayout = Craft::$app->getAddresses()->getLayout();
        parent::init();
    }

    /**
     * Runs the data migration
     *
     * @throws Throwable
     */
    public function actionRun(): int
    {
        if (!$this->interactive) {
            $this->stderr("This command must be run from an interactive shell.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Make sure Commerce 4 migrations have been run
        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '<')) {
            $this->stderr("You must run the `craft migrate/all` command first.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Make sure all the legacy tables still exist
        foreach ($this->_v3tables as $table) {
            $cleanTableName = str_replace(['{{%', '}}'], '', $table);
            if (!Craft::$app->getDb()->tableExists($table)) {
                $this->stderr(sprintf("Unable to proceed with the Commerce 4 migration: the `%s` table no longer exists.\n", $cleanTableName), Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        // Collect all the countries in the system at the moment
        $this->_allCountriesByV3CountryId = (new Query())
            ->from(['{{%commerce_countries}}'])
            ->indexBy('id')
            ->all();

        // Map any invalid country codes to valid ones
        $validCountries = Craft::$app->getAddresses()->getCountryRepository()->getList();

        foreach ($this->_allCountriesByV3CountryId as &$country) {
            // Is it already valid?
            if (isset($validCountries[$country['iso']])) {
                continue;
            }

            $this->stdout(sprintf("Invalid custom country found: %s (%s)\n", $country['name'], $country['iso']));
            $this->stdout("We need to map this to a valid country code. (All related addresses and zones will be updated.)\n");
            $this->stdout('See: ');
            $this->stdout("https://www.iban.com/country-codes\n", Console::FG_BLUE);
            $country['iso'] = $this->prompt('Enter a valid Alpha-2 country code:', [
                'required' => true,
                'validator' => fn($code) => isset($validCountries[$code]),
                'default' => 'US',
            ]);
            $this->stdout("\n");
        }
        unset($country);

        // Collect all the standard states that were set up in v3.
        $this->_allStatesByV3StateId = (new Query())
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

        $db = Craft::$app->getDb();

        try {
            $db->transaction(function() {
                $this->stdout("Ensuring we have all the required custom fields…\n");
                $this->_migrateAddressCustomFields();

                $this->stdout("Creating a user for every customer…\n");
                $this->_migrateCustomers();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Migrating address data…\n");
                $this->_migrateAddresses();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Updating orders…\n");
                $this->_migrateOrderAddresses();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Updating users…\n");
                $this->_migrateUserAddressBook();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Updating the store location…\n");
                $this->_migrateStore();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Updating shipping zones…\n");
                $this->_migrateShippingZones();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Updating tax zones…\n");
                $this->_migrateTaxZones();
                $this->stdout("\nDone.\n\n");

                $this->stdout("Updating order histories…\n");
                $this->_migrateOrderHistoryUser();
                $this->stdout("\nDone.\n\n");
            });
        } catch (OperationAbortedException) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Cleaning up…\n");
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
     * @throws OperationAbortedException
     * @throws Throwable
     */
    private function _migrateAddressCustomFields(): void
    {
        if (!empty($this->neededCustomAddressFields)) {
            // Add custom fields to the address field layout
            $firstTab = $this->_addressFieldLayout->getTabs()[0];
            $layoutElements = $firstTab->getElements();

            $list = implode(array_map(fn($label) => " - $label\n", $this->neededCustomAddressFields));
            $this->stdout(<<<EOL
Customer and order addresses will be migrated to native Craft address elements.
Some of the existing addresses contain data that will need to be stored in custom fields:
$list
EOL
            );

            foreach ($this->neededCustomAddressFields as $oldAttribute => $label) {
                $field = $this->_customField($oldAttribute, $label, 'address');
                $this->_oldAddressFieldToNewCustomFieldHandle[$oldAttribute] = $field->handle;
                if ($this->_allowAdminChanges && !$this->_addressFieldLayout->getFieldByHandle($field->handle)) {
                    $layoutElements[] = new CustomField($field);
                }
            }

            if ($this->_allowAdminChanges) {
                $firstTab->setElements($layoutElements);
                Craft::$app->getAddresses()->saveLayout($this->_addressFieldLayout);
            }
        }
    }

    /**
     * @param string $oldAttribute
     * @param string $label
     * @return FieldInterface
     * @throws OperationAbortedException
     * @throws Throwable
     */
    private function _customField(string $oldAttribute, string $label, ?string $prefix = null): FieldInterface
    {
        $fieldsService = Craft::$app->getFields();
        $handlePattern = sprintf('/^%s$/', HandleValidator::$handlePattern);

        if (
            $this->_allowAdminChanges &&
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
                'default' => $fieldsService->getFieldByHandle($oldAttribute) === null ? StringHelper::toCamelCase("$prefix $oldAttribute") : null,
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
            'validator' => function($handle) use ($handlePattern, $fieldsService) {
                if (!preg_match($handlePattern, $handle)) {
                    $this->stdout("Invalid field handle.\n");
                    return false;
                }
                $field = $fieldsService->getFieldByHandle($handle);
                if (!$field) {
                    $this->stdout("No field exists with that handle.\n");
                    return false;
                }
                if (!$this->_allowAdminChanges && $this->_addressFieldLayout->getFieldByHandle($handle) === null) {
                    $this->stdout("$field->name isn’t included in the address field layout, and admin changes aren't allowed on this environment.\n");
                    return false;
                }
                return true;
            },
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
        $orderHistoriesTable = '{{%commerce_orderhistories}}';
        $customersTable = '{{%commerce_customers}}';
        $isPsql = Craft::$app->getDb()->getIsPgsql();

        // Make all address elements with their correct customer owner ID
        if ($isPsql) {
            $sql = <<<SQL
    update $orderHistoriesTable [[oh]]
    set [[userId]] = [[cu.customerId]]
    from $customersTable [[cu]]
    where [[cu.id]] = [[oh.v3customerId]]
SQL;
        } else {
            $sql = <<<SQL
update $orderHistoriesTable [[oh]]
inner join $customersTable [[cu]] on
    [[cu.id]] = [[oh.v3customerId]]
set [[oh.userId]] = [[cu.customerId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function _migrateUserAddressBook()
    {
        $addressTable = CraftTable::ADDRESSES;
        $previousAddressTable = '{{%commerce_addresses}}';
        $customerAddressTable = '{{%commerce_customers_addresses}}';
        $customersTable = '{{%commerce_customers}}';
        $isPsql = Craft::$app->getDb()->getIsPgsql();

        // Make all address elements with their correct customer owner ID
        if ($isPsql) {
            $sql = <<<SQL
    update $addressTable [[a]]
    set [[ownerId]] = [[cu.customerId]]
    from $customersTable [[cu]], $customerAddressTable [[ca]], $previousAddressTable [[pa]]
    where [[cu.id]] = [[ca.customerId]]
    and [[ca.addressId]] = [[pa.id]]
    and [[pa.v4addressId]] = [[a.id]]
SQL;
        } else {
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
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Migrates the primary billing address ID
        if ($isPsql) {
            $sql = <<<SQL
update $customersTable [[c]]
set [[primaryBillingAddressId]] = [[pa.v4addressId]]
from $previousAddressTable [[pa]]
where [[pa.id]] = [[c.v3primaryBillingAddressId]]
SQL;
        } else {
            $sql = <<<SQL
update $customersTable [[c]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[c.v3primaryBillingAddressId]]
set [[c.primaryBillingAddressId]] = [[pa.v4addressId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Migrates the primary shipping ID
        if ($isPsql) {
            $sql = <<<SQL
update $customersTable [[c]]
set [[primaryShippingAddressId]] = [[pa.v4addressId]]
from $previousAddressTable [[pa]]
where [[pa.id]] = [[c.v3primaryShippingAddressId]]
SQL;
        } else {
            $sql = <<<SQL
update $customersTable [[c]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[c.v3primaryShippingAddressId]]
set [[c.primaryShippingAddressId]] = [[pa.v4addressId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function _migrateOrderAddresses(): void
    {
        $addressTable = CraftTable::ADDRESSES;
        $previousAddressTable = '{{%commerce_addresses}}';
        $ordersTable = Table::ORDERS;
        $isPsql = Craft::$app->getDb()->getIsPgsql();

        // Order Shipping address
        if ($isPsql) {
            $sql = <<<SQL
update $ordersTable [[o]]
set [[shippingAddressId]] = [[pa.v4addressId]]
from $previousAddressTable [[pa]]
where [[pa.id]] = [[o.v3shippingAddressId]]
SQL;
        } else {
            $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3shippingAddressId]]
set [[o.shippingAddressId]] = [[pa.v4addressId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Order Billing address
        if ($isPsql) {
            $sql = <<<SQL
update $ordersTable [[o]]
set [[billingAddressId]] = [[pa.v4addressId]]
from $previousAddressTable [[pa]]
where [[pa.id]] = [[o.v3billingAddressId]]
SQL;
        } else {
            $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3billingAddressId]]
set [[o.billingAddressId]] = [[pa.v4addressId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Order Estimated shipping address
        if ($isPsql) {
            $sql = <<<SQL
update $ordersTable [[o]]
set [[estimatedBillingAddressId]] = [[pa.v4addressId]]
from $previousAddressTable [[pa]]
where [[pa.id]] = [[o.v3estimatedBillingAddressId]]
SQL;
        } else {
            $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3estimatedBillingAddressId]]
set [[o.estimatedBillingAddressId]] = [[pa.v4addressId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Order Estimated billing address
        if ($isPsql) {
            $sql = <<<SQL
update $ordersTable [[o]]
set [[estimatedBillingAddressId]] = [[pa.v4addressId]]
from $previousAddressTable [[pa]]
where [[pa.id]] = [[o.v3estimatedBillingAddressId]]
SQL;
        } else {
            $sql = <<<SQL
update $ordersTable [[o]]
inner join $previousAddressTable [[pa]] on
    [[pa.id]] = [[o.v3estimatedBillingAddressId]]
set [[o.estimatedBillingAddressId]] = [[pa.v4addressId]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Make all order shipping address elements have the owner ID of the order
        if ($isPsql) {
            $sql = <<<SQL
update $addressTable [[a]]
set [[ownerId]] = [[o.id]]
from $ordersTable [[o]]
where [[o.shippingAddressId]] = [[a.id]]
SQL;
        } else {
            $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.shippingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Make all order billing address elements have the owner ID of the order
        if ($isPsql) {
            $sql = <<<SQL
update $addressTable [[a]]
set [[ownerId]] = [[o.id]]
from $ordersTable [[o]]
where [[o.billingAddressId]] = [[a.id]]
SQL;
        } else {
            $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.billingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Make all order estimated billing address elements have the owner ID of the order
        if ($isPsql) {
            $sql = <<<SQL
update $addressTable [[a]]
set [[ownerId]] = [[o.id]]
from $ordersTable [[o]]
where [[o.estimatedBillingAddressId]] = [[a.id]]
SQL;
        } else {
            $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.estimatedBillingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();

        // Make all order estimated shipping address elements have the owner ID of the order
        if ($isPsql) {
            $sql = <<<SQL
update $addressTable [[a]]
set [[ownerId]] = [[o.id]]
from $ordersTable [[o]]
where [[o.estimatedShippingAddressId]] = [[a.id]]
SQL;
        } else {
            $sql = <<<SQL
update $addressTable [[a]]
inner join $ordersTable [[o]] on
    [[o.estimatedShippingAddressId]] = [[a.id]]
set [[a.ownerId]] = [[o.id]]
SQL;
        }
        Craft::$app->getDb()->createCommand($sql)->execute();
    }

    /**
     * @return void
     */
    private function _migrateAddresses()
    {
        $addresses = (new Query())
            ->select('*')
            ->from(['a' => '{{%commerce_addresses}}'])
            ->limit(null);

        $totalAddresses = $addresses->count();
        $done = 0;
        Console::startProgress($done, $totalAddresses);
        foreach ($addresses->each() as $address) {
            $addressElement = $this->_createAddress($address);
            // Save the old ID for later
            Craft::$app->getDb()->createCommand()->update('{{%commerce_addresses}}',
                ['v4addressId' => $addressElement->id],
                ['id' => $address['id']]
            )->execute();
            Console::updateProgress($done++, $totalAddresses);
        }

        Console::endProgress($totalAddresses . ' addresses migrated.');
    }

    /**
     * Creates an Address element from previous address data and returns the ID
     */
    private function _createAddress($data): Address
    {
        $address = new Address();
        $address->title = $data['label'] ?: 'Address';
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

        if ($data['firstName'] || $data['lastName']) {
            $this->_ensureAddressField(new FullNameField());
            $address->fullName = implode(' ', array_filter([$data['firstName'], $data['lastName']]));
        }

        if ($data['businessName']) {
            $this->_ensureAddressField(new OrganizationField());
            $address->organization = $data['businessName'];
        }

        if ($data['businessTaxId']) {
            $this->_ensureAddressField(new OrganizationTaxIdField());
            $address->organizationTaxId = $data['businessTaxId'];
        }

        // Set fields that were created and mapped from old data
        foreach ($this->_oldAddressFieldToNewCustomFieldHandle as $oldAttribute => $customFieldHandle) {
            if ($data[$oldAttribute]) {
                $address->setFieldValue($customFieldHandle, $data[$oldAttribute]);
            }
        }

        $address->dateCreated = DateTimeHelper::toDateTime($data['dateCreated']);
        $address->dateUpdated = DateTimeHelper::toDateTime($data['dateUpdated']);
        Craft::$app->getElements()->saveElement($address, false, false, false);

        return $address;
    }

    private function _ensureAddressField(BaseField $layoutElement): void
    {
        $attribute = $layoutElement->attribute();
        if ($this->_addressFieldLayout->isFieldIncluded($attribute)) {
            return;
        }

        if (!$this->_allowAdminChanges) {
            $this->stdout("The address field layout doesn't include the `$attribute` field. Admin changes aren't allowed on this environment, so it can't be added automatically.\n");
            return;
        }

        $firstTab = $this->_addressFieldLayout->getTabs()[0];
        $layoutElements = $firstTab->getElements();
        $layoutElements[] = $layoutElement;
        $firstTab->setElements($layoutElements);
        Craft::$app->getAddresses()->saveLayout($this->_addressFieldLayout);
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
        $storeModel->setMarketAddressCondition($condition);

        Plugin::getInstance()->getStore()->saveStore($storeModel);
    }
}
