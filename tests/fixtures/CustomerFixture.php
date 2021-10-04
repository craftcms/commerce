<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\test\fixtures\elements\UserFixture;

/**
 * Class CustomerFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class CustomerFixture extends UserFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/customers.php';

    /**
     * @var array
     */
    private array $_customerUserGroups = [];

    /**
     * @inheritdoc
     */
    public function beforeLoad()
    {
        parent::beforeLoad();

        foreach ($this->loadData($this->dataFile) as $key => $user) {
            if (isset($user['_userGroups'])) {
                $this->_customerUserGroups[$key] = $user['_userGroups'];
            }
        }
    }

    /**
     * @param ElementInterface $element
     * @param array $attributes
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        if (isset($attributes['_userGroups'])) {
            unset($attributes['_userGroups']);
        }

        parent::populateElement($element, $attributes);
    }

    /**
     * @inheritdoc
     */
    public function afterLoad()
    {
        parent::afterLoad();
        $userGroups = Craft::$app->getUserGroups()->getAllGroups();

        foreach ($this->_customerUserGroups as $key => $groups) {
            if (empty($groups)) {
                continue;
            }

            /** @var User $user */
            $user = $this->getElement($key);
            if (!$user) {
                continue;
            }

            Craft::$app->getUsers()->assignUserToGroups($user->id, $groups);
            $customerUserGroups = ArrayHelper::whereIn($userGroups, 'id', $groups);
            $user->setGroups($customerUserGroups);
        }
    }

    /**
     * @inheritdoc
     */
    public function afterUnload()
    {
        parent::afterUnload();

        $this->_customerUserGroups = [];
    }

    /**
     * @inheritdoc
     */
    public $depends = [UserGroupsFixture::class, FieldLayoutFixture::class];
}
