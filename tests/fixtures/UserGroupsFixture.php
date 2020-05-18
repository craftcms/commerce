<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\records\UserGroup;
use craft\services\UserGroups;
use craft\test\Fixture;
use yii\base\Exception;

/**
 * Class UserGroupsFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class UserGroupsFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $modelClass = UserGroup::class;

    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/user-groups.php';

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function load()
    {
        parent::load();

        Craft::$app->set('userGroups', new UserGroups());
    }
}
