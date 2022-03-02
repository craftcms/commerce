<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\commerce\console\Controller;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\records\Store;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\conditions\addresses\AddressCondition;
use craft\elements\conditions\addresses\AdministrativeAreaConditionRule;
use craft\elements\conditions\addresses\CountryConditionRule;
use craft\elements\conditions\addresses\PostalCodeFormulaConditionRule;
use craft\elements\User;
use craft\fields\PlainText;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use Illuminate\Support\Collection;
use yii\console\ExitCode;

/**
 * Command to be run once upgraded to Commerce 4
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class MigrateCommerce4 extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'migrate';

    /**
     * @var string[] The list of fields that can be converted to PlainText fields
     */
    public array $customAddressFields = [
        'addressLine3',
        'attention',
        'title',
        'phone',
        'alternativePhone',
        'notes',
        'businessId',
        'custom1',
        'custom2',
        'custom3',
        'custom4',
    ];

    // Do we migrate the data
    // ['fieldHandle => ['skip'= bool]]
    public array $customAddressFieldMigrateOptions = [];

    /**
     * v3CountryId => countryCode
     */
    private array $_countryCodesByV3CountryId = [];

    /**
     * v3StateId => administrativeArea
     */
    private array $_administrativeAreaByV3StateId = [];

    /**
     * @var array
     */
    public array $userIdsByEmail = [];

    /**
     * @var array
     */
    public array $userIdsByv3CustomerId = [];

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        // Collect all the countries and state that were set up in v3
        $this->_countryCodesByV3CountryId = $this->_countryCodesByV3CountryId();
        $this->_administrativeAreaByV3StateId = $this->_administrativeAreaByV3StateId();
    }

    /**
     * Reset Commerce data.
     */
    public function actionMigrate(): int
    {
        $this->stdout("This command will move data from previous Commerce 3 tables and columns to Commerce 4.\n");

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
            $this->stdout('Aborting.' . PHP_EOL, Console::FG_RED);
            return ExitCode::OK;
        }


        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '>=')) {
            $this->stdout("You must run the `craft migrate` command first.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

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
                $this->stdout('The `' . $cleanTableName . '` table no longer exists, can not proceed.' . PHP_EOL . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout("Creating user for every customer...\n");
        // In addition to creating users if non exists, we also populate the $this->userIdsByEmail and $this->$userIdsByv3CustomerId
        $this->_createUserIfNoneExists();
        $this->stdout("Done.\n");

        $this->stdout("Migrating extra address field to custom fields...\n");
        $this->_migrateAddressCustomFields();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Customer Addresses...\n");
        $this->_migrateUserAddresses();
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

        $this->stdout("Migrating Tax Zones...\n");
        $this->_migrateShippingZones();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Tax Zones...\n");
        $this->_migrateTaxZones();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        return 0;
    }

    /**
     * @return void
     */
    private function _migrateShippingZones(): void
    {

        $shippingZones = (new Query())
            ->select(['id', 'v3zipCodeConditionFormula', 'isCountryBased'])
            ->from(['{{%commerce_shippingzones}}'])
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
            ->select(['id', 'v3zipCodeConditionFormula', 'isCountryBased'])
            ->from(['{{%commerce_taxzones}}'])
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
     * @return void
     * @throws \Throwable
     */
    private function _migrateAddressCustomFields(): void
    {
        $addressFieldLayout = Craft::$app->getFields()->getLayoutByType(Address::class);
        $existingFieldsInAddressLayout = Collection::make($addressFieldLayout->getCustomFields());

        foreach ($this->customAddressFields as $fieldHandle) {

            // Does a field with the same handle exist anywhere?
            $currentField = Craft::$app->getFields()->getFieldByHandle($fieldHandle, false);
            $isFieldInAddressFieldLayout = (bool)$existingFieldsInAddressLayout->first(function($field, $key) use ($fieldHandle) {
                /** @var FieldInterface $field */
                return $field->handle == $fieldHandle;
            });

            // Defaults
            $this->customAddressFieldMigrateOptions[$fieldHandle] = [
                'skip' => true,
                'newFieldHandle' => '',
            ];

            $dataExists = (new Query())
                ->select($fieldHandle)
                ->where(['not', [$fieldHandle => null]])
                ->andWhere(['not', [$fieldHandle => '']])
                ->from(['{{%commerce_addresses}}'])
                ->exists();

            $this->customAddressFieldMigrateOptions[$fieldHandle]['skip'] = !$dataExists;

            if (!$currentField || !$isFieldInAddressFieldLayout) {
                $this->stdout("There is no custom field with handle \"$fieldHandle\", creating field...\n");

                if (!$currentField) {
                    $currentField = new PlainText([
                        'name' => $fieldHandle,
                        'handle' => $fieldHandle,
                        'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                    ]);
                }

                if ($fieldHandle == 'notes') {
                    $currentField->multiline = true;
                }

                $field = Craft::$app->getFields()->saveField($currentField);
                // TODO putting field into layout.
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
            ->from(['{{commerce_countries}}'])
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

    private function _migrateOrderAddresses()
    {

        $addressesQuery = (new Query())
            ->select([
                'addresses.id',
                'addresses.attention',
                'addresses.title',
                'addresses.firstName',
                'addresses.lastName',
                'addresses.fullName',
                'addresses.countryId',
                'addresses.stateId',
                'addresses.address1',
                'addresses.address2',
                'addresses.address3',
                'addresses.city',
                'addresses.zipCode',
                'addresses.phone',
                'addresses.alternativePhone',
                'addresses.label',
                'addresses.notes',
                'addresses.businessName',
                'addresses.businessTaxId',
                'addresses.businessId',
                'addresses.stateName',
                'addresses.custom1',
                'addresses.custom2',
                'addresses.custom3',
                'addresses.custom4',
                'addresses.isEstimated',
                'addresses.isStoreLocation',
                'addresses.dateCreated',
                'addresses.dateUpdated',
                'o.id as orderId',
            ])
            ->limit(null)
            ->from(['{{%commerce_addresses}}' . ' addresses']);

        $shippingAddresses = $addressesQuery
            ->innerJoin(['o' => '{{%commerce_orders}}'], '[[addresses.id]] = [[o.v3shippingAddressId]]')
            ->all();

        $this->stdout("Found: " . count($shippingAddresses) . " shipping addresses to migrate...\n");
        $done = 0;
        Console::startProgress($done, count($shippingAddresses), 'Saving address element...');
        foreach ($shippingAddresses as $address) {
            $addressElement = $this->_createAddress($address, $address['orderId']);
            Craft::$app->getDb()->createCommand()->update(Table::ORDERS,
                ['shippingAddressId' => $addressElement->id],
                ['id' => $address['orderId']]
            )->execute();
            Console::updateProgress($done++, count($shippingAddresses));
        }
        Console::endProgress();

        $billingAddresses = $addressesQuery
            ->innerJoin(['o' => '{{%commerce_orders}}'], '[[addresses.id]] = [[o.v3billingAddressId]]')
            ->all();

        $this->stdout("Found: " . count($billingAddresses) . " billing addresses to migrate...\n");
        $done = 0;
        Console::startProgress($done, count($billingAddresses), 'Saving address element...');
        foreach ($billingAddresses as $address) {
            $addressElement = $this->_createAddress($address, $address['orderId']);
            Craft::$app->getDb()->createCommand()->update(Table::ORDERS,
                ['billingAddressId' => $addressElement->id],
                ['id' => $address['orderId']]
            )->execute();
            Console::updateProgress($done++, count($billingAddresses));
        }
        Console::endProgress();
    }

    private function _migrateUserAddresses()
    {
        foreach ($this->userIdsByv3CustomerId as $v3customerId => $userId) {
            $user = User::find()->id($userId)->one();
            if ($user) {
                $addresses = (new Query())->select('*')
                    ->from(['a' => '{{%commerce_addresses}}'])
                    ->innerJoin(['ca' => '{{%commerce_customers_addresses}}'], '[[a.id]] = [[ca.addressId]]')
                    ->andWhere(['[[ca.customerId]]' => $v3customerId])
                    ->all();

                foreach ($addresses as $address) {
                    $this->_createAddress($address, $user->id); // setting the owner will make it this users address
                }
            }
        }
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
        $address->title = $data['label'] ?? 'Address';
        $address->fullName = $data['firstName'] . ' ' . $data['lastName'];
        $address->addressLine1 = $data['address1'] ?? '';
        $address->addressLine2 = $data['address2'] ?? '';
        $address->countryCode = $this->_countryCodesByV3CountryId[$data['countryId']] ?? 'US'; //  get from mapping
        $address->administrativeArea = $this->_administrativeAreaByV3StateId[$data['stateId']] ?? $data['stateName']; //  get from mapping
        $address->postalCode = $data['zipCode'];
        $address->locality = $data['city'];
        $address->dependentLocality = '';
        $address->organization = $data['businessName'];
        $address->organizationTaxId = $data['businessTaxId'];


        // Populate the custom field based on $this->customAddressFieldMigrateOptions
        foreach ($this->customAddressFields as $fieldName) {
            if (!$this->customAddressFieldMigrateOptions[$fieldName]['skip']) {
                $address->setFieldValue($fieldName, $data[$fieldName]);
            }
        }

        $address->dateCreated = DateTimeHelper::toDateTime($data['dateCreated']);
        $address->dateUpdated = DateTimeHelper::toDateTime($data['dateUpdated']);
        Craft::$app->getElements()->saveElement($address, false);

        return $address;
    }

    public function _createUserIfNoneExists(): void
    {
        $allCustomers = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['email', '[[customers.userId]] as userId', 'v3customerId as v3CustomerId'])
            ->innerJoin('{{%commerce_customers}} customers', '[[customers.id]] = [[orders.customerId]]')
            ->where(['not', ['email' => null]])
            ->andWhere(['not', ['email' => '']])
            ->indexBy('v3customerId')
            ->distinct()
            ->all();

        $this->userIdsByEmail = [];
        $this->userIdsByv3CustomerId = [];
        $done = 0;
        Console::startProgress($done, count($allCustomers), 'Ensuring users exist for each customer...');

        foreach ($allCustomers as $v3CustomerId => $customer) {
            Console::updateProgress($done++, count($allCustomers));

            // Do they have a user ID already? If so, we're good.
            if ($customer['userId']) {
                $this->userIdsByEmail[$customer['email']] = $customer['userId'];
                $this->userIdsByv3CustomerId[$v3CustomerId] = $customer['userId'];
                continue;
            }

            // No user lets get the right user ID for this email
            $user = Craft::$app->getUsers()->ensureUserByEmail($customer['email']);
            $this->userIdsByEmail[$customer['email']] = $user->id;
            $this->userIdsByv3CustomerId[$v3CustomerId] = $user->id;

            Console::endProgress(false, false);
        }
    }


    /**
     * @param array $customers
     * @param string $table
     * @param string $updateColumn
     */
    private function _batchUpdateUserId(array $customers, string $table, string $updateColumn = 'userId'): void
    {
        $batches = array_chunk($customers, 500, true);
        foreach ($batches as $batch) {
            $cases = '';
            foreach ($batch as $customerId => $userId) {
                $cases .= '
                WHEN [[customerId]] = ' . $customerId . ' THEN ' . $userId;
            }
            $cases .= '';

            $this->update($table, [
                $updateColumn => new Expression('CASE ' . $cases . ' END')
            ], ['customerId' => array_keys($batch)]);
        }
    }

    /**
     * @return void
     */
    private function _migrateStoreAddress(): void
    {
        $storeLocationData = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_addresses}}' . ' addresses'])
            ->where(['isStoreLocation' => true])
            ->one();

        if ($storeLocationData) {
            $address = new Address();
            Craft::$app->getElement()->saveElement($address, false);
        } else {

        }
    }

    /**
     * @return void
     */
    private function _migrateStoreLocation(): void
    {
        $storeLocationQuery = (new Query())
            ->select('*')
            ->from(['{{%v3commerce_addresses}}'])
            ->where(['isStoreLocation' => true])
            ->one();

        $store = Store::find()->one();
        if ($store === null) {
            $store = new Store();
            $store->save();
        }

        $store->locationAddressId = $this->_createAddress($storeLocationQuery)->id;
        $store->save();
    }
}