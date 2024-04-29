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
use craft\commerce\records\Store;
use craft\db\Connection;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\Address;
use craft\elements\conditions\addresses\AdministrativeAreaConditionRule;
use craft\elements\conditions\addresses\CountryConditionRule;
use craft\elements\User;
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
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use Throwable;
use yii\console\ExitCode;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Schema;
use yii\di\Instance;

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

    private static bool $isRunning = false;

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

    private bool $_allowAdminChanges;
    private FieldLayout $_addressFieldLayout;

    private Connection|string $db = 'db';

    /**
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function init(): void
    {
        $this->_allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $this->_addressFieldLayout = Craft::$app->getAddresses()->getLayout();

        $this->db = Instance::ensure($this->db, Connection::class);

        parent::init();
    }

    public static function isRunning(): bool
    {
        return self::$isRunning;
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

        self::$isRunning = true;

        // Make sure Commerce 4 migrations have been run
        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '<')) {
            $this->stderr("You must run the `craft migrate/all` command first.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Make sure all the legacy tables still exist
        foreach ($this->_v3tables as $table) {
            $cleanTableName = str_replace(['{{%', '}}'], '', $table);
            if (!$this->db->tableExists($table)) {
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

            $this->stdout(sprintf("Invalid custom country found: %s (%s)", $country['name'], $country['iso']));
            $this->stdout("We need to map this to a valid country code. (All related addresses and zones will be updated.)");
            $this->stdout('See: ');
            $this->stdout("https://www.iban.com/country-codes", Console::FG_BLUE);
            $country['iso'] = $this->prompt('Enter a valid Alpha-2 country code:', [
                'required' => true,
                'validator' => fn($code) => isset($validCountries[$code]),
                'default' => 'US',
            ]);
            $this->stdout("");
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

        $startTime = DateTimeHelper::currentUTCDateTime();

        try {
            $this->db->transaction(function() {
                $this->stdout("Ensuring we have all the required custom fields…");
                $this->_migrateAddressCustomFields();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating the store location…");
                $this->_migrateStore();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Ensuring a user exists for all customers…");
                $this->_migrateCustomers();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating order histories…");
                $this->_migrateOrderHistoryUser();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating discount uses…");
                $this->_migrateDiscountUses();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating shipping zones…");
                $this->_migrateShippingZones();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating tax zones…");
                $this->_migrateTaxZones();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Migrating address data…");
                $this->_migrateAddresses();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating order addresses…");
                $this->_migrateOrderAddresses();
                $this->stdoutlast('Done.', Console::FG_GREEN);

                $this->stdout("Updating user address books…");
                $this->_migrateUserAddressBook();
                $this->stdoutlast('Done.', Console::FG_GREEN);
            });
        } catch (OperationAbortedException) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Cleaning up tables…");
        foreach ($this->_v3tables as $table) {
            Db::dropAllForeignKeysToTable($table, $this->db);
            $this->db->createCommand()->dropTableIfExists($table)->execute();
        }

        foreach ($this->_v3droppableColumns as ['table' => $table, 'column' => $column]) {
            if ($this->db->columnExists($table, $column)) {
                Db::dropForeignKeyIfExists($table, $column, $this->db);
                Db::dropIndexIfExists($table, $column, db: $this->db);
                $this->db->createCommand()->dropColumn($table, $column)->execute();
            }
        }

        $endTime = DateTimeHelper::currentUTCDateTime();
        $totalTime = $endTime->diff($startTime);
        $this->stdout("Done. Completed in {$totalTime->format('%H:%I:%S')}");

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
                $this->_oldAddressFieldToNewCustomFieldHandle[$oldAttribute] = ElementHelper::fieldColumnFromField($field);
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
            $this->stdout("Let’s create one then.");

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
                        $this->stdout("“{$handle}” is a reserved word.");
                        return false;
                    }
                    if ($fieldsService->getFieldByHandle($handle) !== null) {
                        $this->stdout("A field with the handle “{$handle}” already exists.");
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
                    $this->stdout("Invalid field handle.");
                    return false;
                }
                $field = $fieldsService->getFieldByHandle($handle);
                if (!$field) {
                    $this->stdout("No field exists with that handle.");
                    return false;
                }
                if (!$this->_allowAdminChanges && $this->_addressFieldLayout->getFieldByHandle($handle) === null) {
                    $this->stdout("$field->name isn’t included in the address field layout, and admin changes aren't allowed on this environment.");
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
        Console::endProgress(count($shippingZones) . ' shipping zones migrated.\n');
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
        Console::endProgress(count($taxZones) . ' tax zones migrated.\n');
    }

    /**
     * @return void
     */
    public function _migrateDiscountUses()
    {
        $discountUses = '{{%commerce_customer_discountuses}}';
        $customersTable = '{{%commerce_customers}}';
        $isPsql = $this->db->getIsPgsql();

        // Make all discount uses with their correct user
        if ($isPsql) {
            $sql = <<<SQL
    update $discountUses [[du]]
    set [[customerId]] = [[cu.customerId]]
    from $customersTable [[cu]]
    where [[cu.id]] = [[du.v3customerId]]
SQL;
        } else {
            $sql = <<<SQL
update $discountUses [[du]]
inner join $customersTable [[cu]] on
    [[cu.id]] = [[du.v3customerId]]
set [[du.customerId]] = [[cu.customerId]]
SQL;
        }
        $this->db->createCommand($sql)->execute();
    }

    /**
     * @return void
     */
    public function _migrateOrderHistoryUser()
    {
        $orderHistoriesTable = '{{%commerce_orderhistories}}';
        $customersTable = '{{%commerce_customers}}';
        $isPsql = $this->db->getIsPgsql();

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
        $this->db->createCommand($sql)->execute();
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
        $isPsql = $this->db->getIsPgsql();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();
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
        $isPsql = $this->db->getIsPgsql();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();

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
        $this->db->createCommand($sql)->execute();
    }

    /**
     * @return void
     */
    private function _migrateAddresses()
    {
        $addressesTable = '{{%commerce_addresses}}';
        $ordersTable = '{{%commerce_orders}}';
        $customersAddressesTable = '{{%commerce_customers_addresses}}';

        $sql = <<<SQL
SELECT a.id
FROM $addressesTable AS a
WHERE NOT EXISTS (
  SELECT 1
  FROM $ordersTable AS o1
  WHERE [[o1.v3billingAddressId]] = a.id
)
AND NOT EXISTS (
  SELECT 1
  FROM $ordersTable AS o2
  WHERE [[o2.v3shippingAddressId]] = a.id
)
AND NOT EXISTS (
  SELECT 1
  FROM $ordersTable AS o2
  WHERE [[o2.v3estimatedBillingAddressId]] = a.id
)
AND NOT EXISTS (
  SELECT 1
  FROM $ordersTable AS o2
  WHERE [[o2.v3shippingAddressId]] = a.id
)
AND NOT EXISTS (
  SELECT 1
  FROM $ordersTable AS o2
  WHERE [[o2.v3estimatedShippingAddressId]] = a.id
)
AND NOT EXISTS (
  SELECT 1
  FROM $customersAddressesTable AS ca
  WHERE [[ca.addressId]] = a.id
);
SQL;

        $deletableAddressesIds = $this->db->createCommand($sql)->queryColumn();
        $deleted = (bool)Db::delete($addressesTable, ['id' => $deletableAddressesIds], db: $this->db);

        if ($deleted) {
            $this->stdout("Deleted $deleted addresses that were not used in orders or customer addresses.");
        }

        $addresses = (new Query())
            ->select('*')
            ->from(['a' => '{{%commerce_addresses}}'])
            ->where(['[[isStoreLocation]]' => false])
            ->limit(null);

        $totalAddresses = $addresses->count();
        $done = 0;
        Console::startProgress($done, $totalAddresses);
        foreach ($addresses->batch(500) as $addressRows) {
            $updateAddressParams = [];
            $addressIds = [];

            foreach ($addressRows as $address) {
                $addressElementId = $this->_createAddress($address);
                Console::updateProgress($done++, $totalAddresses);
                $addressIds[] = $address['id'];
                $updateAddressParams['v4addressId'][$address['id']] = $addressElementId;
            }

            $data = $this->_getBatchUpdateQueryWithParams(
                tableName: '{{%commerce_addresses}}',
                byField: 'id',
                fieldValues: $addressIds,
                params: $updateAddressParams
            );

            Craft::$app->db->createCommand($data['sql'], $data['params'])->execute();
        }

        Console::endProgress($totalAddresses . ' addresses migrated.\n');
    }

    /**
     * Creates an Address element from previous address data and returns the ID
     */
    private function _createAddress($data): int
    {
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $dateCreated = Db::prepareDateForDb($data['dateCreated']);
        $dateUpdated = Db::prepareDateForDb($data['dateUpdated']);

        // Insert into elements table
        Db::insert(CraftTable::ELEMENTS, [
            'fieldLayoutId' => $this->_addressFieldLayout->id,
            'type' => Address::class,
            'enabled' => true,
            'archived' => false,
            'dateCreated' => $dateCreated,
            'dateUpdated' => $dateUpdated,
            'uid' => StringHelper::UUID(),
        ], $this->db);
        /** @var int $addressElementId */
        $addressElementId = $this->db->getLastInsertID();

        // Insert into element sites table
        Db::insert(CraftTable::ELEMENTS_SITES, [
            'elementId' => $addressElementId,
            'siteId' => $primarySite->id,
            'enabled' => true,
            'dateCreated' => $dateCreated,
            'dateUpdated' => $dateUpdated,
            'uid' => StringHelper::UUID(),
        ], $this->db);

        $addressContent = [
            'elementId' => $addressElementId,
            'title' => $data['label'] ?: 'Address',
            'siteId' => $primarySite->id,
            'dateCreated' => $dateCreated,
            'dateUpdated' => $dateUpdated,
            'uid' => StringHelper::UUID(),
        ];
        $address = [
            'id' => $addressElementId,
            'addressLine1' => $data['address1'],
            'addressLine2' => $data['address2'],
            'countryCode' => $this->_allCountriesByV3CountryId[$data['countryId']]['iso'] ?? 'US',
            'dateCreated' => $dateCreated,
            'dateUpdated' => $dateUpdated,
        ];

        // Was a stateId supplied, if so look it up in the mapping and
        if ($data['stateId']) {
            $address['administrativeArea'] = $this->_allStatesByV3StateId[$data['stateId']]['abbreviation'] ?? null;
        } else {
            $address['administrativeArea'] = $data['stateName'] ?? null;
        }

        $address['postalCode'] = $data['zipCode'];
        $address['locality'] = $data['city'];
        $address['dependentLocality'] = '';

        if ($data['fullName'] || $data['firstName'] || $data['lastName']) {
            $this->_ensureAddressField(new FullNameField());
            if ($data['fullName']) {
                $address['fullName'] = $data['fullName'];
            } else {
                $address['fullName'] = implode(' ', array_filter([$data['firstName'], $data['lastName']]));
            }
        }

        if ($data['businessName']) {
            $this->_ensureAddressField(new OrganizationField());
            $address['organization'] = $data['businessName'];
        }

        if ($data['businessTaxId']) {
            $this->_ensureAddressField(new OrganizationTaxIdField());
            $address['organizationTaxId'] = $data['businessTaxId'];
        }

        // Set fields that were created and mapped from old data
        foreach ($this->_oldAddressFieldToNewCustomFieldHandle as $oldAttribute => $dbCustomFieldHandle) {
            if ($data[$oldAttribute]) {
                $addressContent[$dbCustomFieldHandle] = $data[$oldAttribute];
            }
        }

        // insert into content table
        Db::insert(CraftTable::CONTENT, $addressContent, $this->db);

        // insert into address table
        Db::insert(CraftTable::ADDRESSES, $address, $this->db);

        return $addressElementId;
    }

    private function _ensureAddressField(BaseField $layoutElement): void
    {
        $attribute = $layoutElement->attribute();
        if ($this->_addressFieldLayout->isFieldIncluded($attribute)) {
            return;
        }

        if (!$this->_allowAdminChanges) {
            $this->stdout("The address field layout doesn't include the `$attribute` field. Admin changes aren't allowed on this environment, so it can't be added automatically.");
            return;
        }

        $firstTab = $this->_addressFieldLayout->getTabs()[0];
        $layoutElements = $firstTab->getElements();
        $layoutElements[] = $layoutElement;
        $firstTab->setElements($layoutElements);
        Craft::$app->getAddresses()->saveLayout($this->_addressFieldLayout);
    }

    /**
     * Migrates all guest customers to users. Prepares and cleans up before and after this process.
     *
     *
     * @return void
     */
    public function _migrateCustomers(): void
    {
        $ordersTable = '{{%commerce_orders}}';
        $orderHistoriesTable = '{{%commerce_orderhistories}}';
        $customersTable = '{{%commerce_customers}}';
        $customersAddressesTable = '{{%commerce_customers_addresses}}';
        $customersDiscountUsesTable = '{{%commerce_customer_discountuses}}';
        $usersTable = '{{%users}}';
        $isPsql = $this->db->getIsPgsql();

        // Find where we have more than one user ID for a customer
        $this->stdout('  Making sure there are no duplicate user IDs in the customer table.');

        $duplicateUserIdInCustomersTable = (new Query())
            ->select(['[[cu.v3userId]]'])
            ->from(['cu' => $customersTable])
            ->groupBy(['cu.v3userId'])
            ->andWhere(['not', ['cu.v3userId' => null]])
            ->having(['>', 'count(*)', 1]);

        $duplicates = (new Query())
            ->select(['[[id]]', '[[v3userId]]'])
            ->from(['cu' => $customersTable])
            ->where(['cu.v3userId' => $duplicateUserIdInCustomersTable])
            ->orderBy(['cu.v3userId' => SORT_ASC, 'cu.id' => SORT_ASC])
            ->all();

        $keyedByv3UserId = collect($duplicates)->groupBy('v3userId')->all();

        foreach ($keyedByv3UserId as $v3userId => $customers) {
            $customerId = null;
            $customerIdsToDelete = [];
            foreach ($customers as $customer) {
                if ($customerId === null) {
                    $customerId = $customer['id'];
                } else {
                    $customerIdsToDelete[] = $customer['id'];
                }
            }

            if (empty($customerIdsToDelete)) {
                continue;
            }

            $totalUsesForCustomerByDiscountId = (new Query())
                ->select([
                    new Expression('SUM(uses) as [[uses]]'),
                    '[[du.discountId]] as [[discountId]]',
                    new Expression($customerId . ' as [[v3customerId]]'),
                ])
                ->from(['du' => $customersDiscountUsesTable])
                ->where(['du.v3customerId' => $customerIdsToDelete])
                ->orWhere(['du.v3customerId' => $customerId])
                ->groupBy(['du.discountId'])
                ->all();


            foreach ($totalUsesForCustomerByDiscountId as $usesByDiscountId) {
                $discountUse = (new Query())->select('id')
                    ->from($customersDiscountUsesTable)
                    ->where(['discountId' => $usesByDiscountId['discountId'], 'v3customerId' => $customerId])
                    ->one();

                if ($discountUse) {
                    Db::update($customersDiscountUsesTable, [
                        'uses' => $usesByDiscountId['uses'],
                    ], ['id' => $discountUse['id']], db: $this->db);
                } else {
                    Db::insert($customersDiscountUsesTable, $usesByDiscountId, $this->db);
                }
            }

            Db::update(
                $customersAddressesTable,
                ['customerId' => $customerId],
                ['customerId' => $customerIdsToDelete],
                db: $this->db,
            );

            Db::update(
                $ordersTable,
                ['v3customerId' => $customerId],
                ['v3customerId' => $customerIdsToDelete],
                db: $this->db,
            );

            Db::update(
                $orderHistoriesTable,
                ['v3customerId' => $customerId],
                ['v3customerId' => $customerIdsToDelete],
                db: $this->db,
            );

            Db::delete(
                $customersTable,
                ['id' => $customerIdsToDelete],
                db: $this->db,
            );
        }
        $this->stdoutlast('  Done.', Console::FG_GREEN);

        $this->stdout('  Purging orphaned customers.');
        $this->_purgeOrphanedCustomers();
        $this->stdoutlast('  Done.', Console::FG_GREEN);


        $this->stdout('  Removing primary address settings for guest customers.');
        Db::update(Table::CUSTOMERS, [
            'v3primaryShippingAddressId' => null,
            'v3primaryBillingAddressId' => null,
        ], ['v3userId' => null], db: $this->db);
        $this->stdoutlast('  Done.', Console::FG_GREEN);


        $this->stdout('  Updating all orders with the email of its real user.');
        if ($isPsql) {
            $sql = <<<SQL
    update $ordersTable [[o1]]
    set [[email]] = [[u.email]]
    from $customersTable [[cu]], $usersTable [[u]], $ordersTable [[o2]]
    where [[o2.v3customerId]] = [[cu.id]]
    and [[cu.v3userId]] = [[u.id]]
SQL;
        } else {
            $sql = <<<SQL
update $ordersTable [[o]]
inner join $customersTable [[cu]] on
    [[cu.id]] = [[o.v3customerId]]
inner join $usersTable [[u]] on
    [[u.id]] = [[cu.v3userId]]
set [[o.email]] = [[u.email]]
SQL;
        }
        $this->db->createCommand($sql)->execute();
        $this->stdoutlast('  Done.', Console::FG_GREEN);


        $this->stdout('  Getting all guest email addresses (no user account with that email address found).');
        /** @var array{string: string} $guestEmails */
        $guestEmails = (new Query())
            ->select(['[[o.email]]'])
            ->from(['o' => $ordersTable])
            ->leftJoin(['u' => $usersTable], 'o.email = u.email')
            ->where(['u.email' => null])
            ->andWhere(['not', ['o.email' => null]])
            ->andWhere(['not', ['o.email' => '']])
            ->groupBy(['[[o.email]]'])
            ->column();
        $this->stdoutlast('  Done. Found ' . count($guestEmails) . ' guest emails.', Console::FG_GREEN);

        // We know we have to make a user for every guest email address
        // We don't use Craft::$app->getUsers()->ensureUserByEmail() since we know it doesn’t exist in the users table already
        $this->stdout('  Creating a inactive user for each guest email.');
        $startTime = microtime(true);
        $totalGuestEmails = count($guestEmails);
        $doneTotalGuestEmails = 0;
        Console::startProgress($doneTotalGuestEmails, $totalGuestEmails);
        foreach ($guestEmails as $guestEmail) {
            $user = new User();
            $user->email = $guestEmail;
            if (!Craft::$app->getElements()->saveElement($user, false, false, false, false)) {
                throw new \yii\base\Exception('Unable to save user for email: ' . $guestEmail . ' : ' . implode(', ', $user->getFirstErrors()));
            }

            Console::updateProgress($doneTotalGuestEmails++, $totalGuestEmails);
        }
        $totalTime = microtime(true) - $startTime;
        $this->stdout('  Updating all customers with their correct user ID.');
        Console::endProgress('Created' . $totalGuestEmails . ' inactive users in ' . $totalTime / 1000 . ' seconds' . PHP_EOL);

        $this->stdout('  Updating all customers with their correct user ID.');
        if ($isPsql) {
            $sql = <<<SQL
    update $customersTable [[cu]]
    set [[customerId]] = [[cu.v3userId]]
    where [[cu.v3userId]] is not null
SQL;
        } else {
            $sql = <<<SQL
update $customersTable [[cu]]
set [[cu.customerId]] = [[cu.v3userId]]
where [[cu.v3userId]] is not null
SQL;
        }
        $this->db->createCommand($sql)->execute();
        $this->stdoutlast('  Done.', Console::FG_GREEN);

        $this->stdout('  Updating all orders with their correct user ID.');
        if ($isPsql) {
            $sql = <<<SQL
    update $ordersTable [[o1]]
    set [[customerId]] = [[u.id]]
    from $usersTable [[u]], $ordersTable [[o2]]
    where [[o2.email]] = [[u.email]]
SQL;
        } else {
            $sql = <<<SQL
update $ordersTable [[o]]
inner join $usersTable [[u]] on
    [[o.email]] = [[u.email]]
set [[o.customerId]] = [[u.id]]
SQL;
        }
        $this->db->createCommand($sql)->execute();
        $this->stdoutlast('  Done.', Console::FG_GREEN);

        // drop all customers without a customerId
        $this->stdout('  Confirming all customers are now related to a user.');
        Db::delete($customersTable, ['customerId' => null], db: $this->db);
    }

    /**
     * @return void
     */
    private function _migrateStore(): void
    {
        $storeLocation = (new Query())
            ->select('*')
            ->from(['{{%commerce_addresses}}'])
            ->where(['[[isStoreLocation]]' => true])
            ->one();

        $store = Store::find()->one();
        if ($store === null) {
            $store = new Store();
            $store->save();
        }

        $storeModel = Plugin::getInstance()->getStore()->getStore();

        if ($storeLocation) {
            $storeModel->locationAddressId = $this->_createAddress($storeLocation);
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

    /**
     * @return void
     * @throws Exception
     */
    private function _purgeOrphanedCustomers(): void
    {
        $orphanedCustomerIds = $this->_getOrphanedCustomerIds();
        // Delete all customers that don't have any orders
        foreach (collect($orphanedCustomerIds)->chunk(999) as $chunk) {
            Db::delete(Table::CUSTOMERS, [
                'id' => $chunk->all(),
            ], db: $this->db);
        }
    }

    /**
     * @inheritDoc
     */
    public function stdout($string)
    {
        $args = func_get_args();

        return parent::stdout($string . PHP_EOL, ...array_slice($args, 1));
    }

    /**
     * @inheritDoc
     */
    public function stdoutlast($string)
    {
        $args = func_get_args();

        return parent::stdout($string . PHP_EOL . PHP_EOL, ...array_slice($args, 1));
    }


    /**
     * @param $tableName
     * @param $byField
     * @param $fieldValues
     * @param $params
     * @return array
     * @throws \yii\base\NotSupportedException
     */
    private function _getBatchUpdateQueryWithParams($tableName, $byField, $fieldValues, $params)
    {
        $str = 'UPDATE ' . $this->db->quoteTableName($this->db->getSchema()->getRawTableName($tableName)) . ' SET ';
        $row = [];
        $bind = [];

        foreach (array_keys($params) as $param) {
            $rowStr = $this->db->quoteColumnName($param) . ' = (CASE ' . $this->db->quoteColumnName($byField) . ' ';
            $cel = [];
            foreach ($fieldValues as $fieldValue) {
                if (array_key_exists($fieldValue, $params[$param])) {
                    $idValue = ':' . $byField . '_' . preg_replace("#[[:punct:]]#", "", $fieldValue);
                    $paramValue = ':' . $param . '_' . preg_replace("#[[:punct:]]#", "", $fieldValue);
                    $bind[$paramValue] = $params[$param][$fieldValue];
                    $cel[] = 'WHEN ' . $idValue . ' THEN ' . $paramValue;
                }
            }
            $rowStr .= implode(' ', $cel);
            $rowStr .= ' ELSE ' . $this->db->quoteColumnName($param) . ' END)';
            $row[] = $rowStr;
        }

        $whereIn = [];
        foreach ($fieldValues as $fieldValue) {
            $paramValue = ':' . $byField . '_' . preg_replace("#[[:punct:]]#", "", $fieldValue);
            $bind[$paramValue] = $fieldValue;
            $whereIn[] = is_string($fieldValue) ? $this->db->quoteValue($fieldValue) : $fieldValue;
        }

        $str .= implode(', ', $row);
        $str .= ' WHERE ' . $this->db->quoteColumnName($byField) . ' IN (' . implode(', ', $whereIn) . ')';
        return ['sql' => $str, 'params' => $bind];
    }
}
