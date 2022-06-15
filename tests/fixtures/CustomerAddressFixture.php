<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\test\fixtures\elements\BaseElementFixture;

/**
 * Class CustomerAddressFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.5
 */
class CustomerAddressFixture extends BaseElementFixture
{
    /**
     * @var string
     */
    public $dataFile = __DIR__ . '/data/customer-addresses.php';

    /**
     * @var string[]
     */
    public $depends = [CustomerFixture::class];

    private array $_primaryAddressByKey = [];

    private array $_ownersByUserId = [];

    /**
     * @inheritdoc
     */
    protected function createElement(): ElementInterface
    {
        return new Address();
    }

    public function beforeLoad()
    {
        parent::beforeLoad();

        foreach ($this->loadData($this->dataFile) as $key => $data) {
            if (isset($data['_primary'])) {
                if (!empty($data['_primary'])) {
                    $this->_primaryAddressByKey[$key] = $data['_primary'];
                }

                unset($data['_primary']);
            }
        }
    }

    /**
     * @param ElementInterface $element
     * @param array $attributes
     * @return void
     */
    public function populateElement(ElementInterface $element, array $attributes): void
    {
        if (isset($attributes['_primary'])) {
            unset($attributes['_primary']);
        }

        if (isset($attributes['_owner'])) {
            if ($owner = Craft::$app->getUsers()->getUserByUsernameOrEmail($attributes['_owner'])) {
                $this->_ownersByUserId[$owner->id] = $owner;
                $element->setAttributes(['ownerId' => $owner->id]);
            }

            unset($attributes['_owner']);
        }

        parent::populateElement($element, $attributes);
    }

    public function load(): void
    {
        parent::load();

        foreach ($this->_primaryAddressByKey as $key => $primaries) {
            /** @var Address $element */
            if (!($element = $this->getElement($key)) || empty($primaries)) {
                continue;
            }

            $user = $this->_ownersByUserId[$element->ownerId] ?? null;
            if (!$user) {
                continue;
            }

            if (in_array('shipping', $primaries, true)) {
                Plugin::getInstance()->getCustomers()->savePrimaryShippingAddressId($user, $element->id);
            }

            if (in_array('billing', $primaries, true)) {
                Plugin::getInstance()->getCustomers()->savePrimaryBillingAddressId($user, $element->id);
            }
        }
    }
}
