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
use craft\commerce\records\Store;
use craft\db\Query;
use craft\elements\Address;
use craft\fieldlayoutelements\TextField;
use craft\fields\PlainText;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
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
     * @var string[] The list of fields that could be converted to PlainText fields
     */
    public $customAddressFields = [
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

    // Custom field options for each field type
    // ['fieldHandle => ['skip'= bool]]
    public $customAddressFieldMigrateOptions = [];

    /**
     * @return void
     * @throws \Throwable
     */
    private function _migrateAddressCustomFields(): void
    {
        $addressFieldLayout = Craft::$app->getFields()->getLayoutByType(Address::class);
        $existingCustomFields = Collection::make($addressFieldLayout->getCustomFields());
        foreach ($this->customAddressFields as $fieldHandle) {
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

            $existingField = $existingCustomFields->first(function($field, $key) use ($fieldHandle) {
                /** @var FieldInterface $field */
                return $field->handle == $fieldHandle;
            });

            if (!$existingField) {
                $this->stdout("There is no custom field with handle \"$fieldHandle\", creating field...\n");
                $field = new PlainText([
                    'name' => $fieldHandle,
                    'handle' => $fieldHandle,
                    'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                ]);

                if ($fieldHandle == 'notes') {
                    $field->multiline = true;
                }

                $field = Craft::$app->getFields()->saveField($field);
            }
        }
    }

    /**
     * v3CountryId => countryCode
     */
    private $_countryCodesByV3CountryId = [];

    /**
     * v3StateId => administrativeArea
     */
    private $_administrativeAreaByV3StateId = [];

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();
        $this->_countryCodesByV3CountryId = $this->_countryCodesByV3CountryId();
        $this->administrativeAreaByV3StateId = $this->_administrativeAreaByV3StateId();
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


        $this->stdout("Migrating extra address field to custom fields...\n");
        $this->_migrateAddressCustomFields();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Migrating Store Location...\n");
        $this->_migrateStoreLocation();
        $this->stdout("Done.\n");
        $this->stdout("\n");

        $this->stdout("Creating user for every customer...\n");
        // $this->_migrateOrderCustomerId(); // TODO
        $this->stdout("Done.\n");
        return 0;
    }

    /**
     * Creates an Address element from previous address data and returns the ID
     */
    public function _createAddress($data, ?int $ownerId = null): int
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
        $address->administrativeArea = $data['stateId'] ?: $data['stateName']; // get from mapping
        $address->postalCode = $data['zipCode'];
        $address->locality = $data['city'];
        $address->dependentLocality = '';
        $address->organization = $data['businessName'];
        $address->organizationTaxId = $data['businessTaxId'];

        // TODO determine if there is data in the column, then migrate to custom field as asked.
        // Optional custom field addressLine3
        $address->addressLine3 = $data['address3'];
        $address->attention = $data['attention'];
        $address->title = $data['title'];
        $address->phone = $data['phone'];
        $address->alternativePhone = $data['alternativePhone'];
        $address->notes = $data['notes'];
        $address->businessId = $data['businessId'];
        $address->custom1 = $data['custom1'];
        $address->custom2 = $data['custom2'];
        $address->custom3 = $data['custom3'];
        $address->custom4 = $data['custom4'];

        $address->dateCreated = DateTimeHelper::toDateTime($data['dateCreated']);
        $address->dateUpdated = DateTimeHelper::toDateTime($data['dateUpdated']);
        Craft::$app->getElements()->saveElement($address);

        return $address->id;
    }

    public function _migrateOrderCustomerId(): void
    {
        $allCustomers = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['email', '[[customers.userId]] as userId', 'customerId as oldCustomerId'])
            ->innerJoin('{{%commerce_customers}} customers', '[[customers.id]] = [[orders.customerId]]')
            ->where(['not', ['email' => null]])
            ->andWhere(['not', ['email' => '']])
            ->indexBy('customerId')
            ->orderBy('customerId ASC')
            ->distinct()
            ->all();

        $userIdsByEmail = [];
        $done = 0;
        Console::startProgress($done, count($allCustomers), 'Ensuring users exist for each customer...');

        foreach ($allCustomers as $key => $customer) {
            Console::updateProgress($done++, count($allCustomers));

            // Do they have a user ID already? If so, we're good.
            if ($customer['userId']) {
                $userIdsByEmail[$customer['email']] = $customer['userId'];
                continue;
            }

            // No user lets get the right user ID for this email
            $user = Craft::$app->getUsers()->ensureUserByEmail($customer['email']);
            $userIdsByEmail[$customer['email']] = $user->id;
            $allCustomers[$key]['userId'] = $user->id;

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

        $store->locationAddressId = $this->_createAddress($storeLocationQuery);
        $store->save();
    }
}