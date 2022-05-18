<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\commerce\console\Controller;
use craft\commerce\Plugin;
use craft\helpers\Console;
use Exception;
use yii\console\ExitCode;

/**
 * Allows you to move Commerce data.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.1
 */
class MoveDataController extends Controller
{
    /**
     * @var string|null The User ID of the user that is having their commerce content moved.
     * @since 3.3
     */
    public ?string $fromUser = null;

    /**
     * @var string|null The User ID of the user that is having the commerce content moved to.
     * @since 3.3
     */
    public ?string $toUser = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'fromUser';
        $options[] = 'toUser';
        return $options;
    }

    /**
     * Move Commerce data.
     */
    public function actionIndex(): int
    {
        $this->stdout("This command will transfer all commerce data from one user to another.\n");

        $this->fromUser = $this->prompt('Move Commerce data from User ID:', [
            'required' => true,
            'default' => $this->fromUser,
        ]);

        $this->toUser = $this->prompt('To User ID:', [
            'required' => true,
            'default' => $this->toUser,
        ]);

        if ($this->fromUser === null || $this->toUser === null) {
            $this->stderr("No 'fromUser' or 'toUser' specified.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $fromUser = Craft::$app->getUsers()->getUserById($this->fromUser);
        $toUser = Craft::$app->getUsers()->getUserById($this->toUser);

        if ($fromUser === null) {
            $this->stderr("No user with ID {$this->fromUser} found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($toUser === null) {
            $this->stderr("No user with ID {$this->toUser} found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $confirm = $this->confirm('Are you sure you want to move all Commerce data from User ID: ' . $this->fromUser . ' to User ID: ' . $this->toUser . '? (y/n)');
        if (!$confirm) {
            $this->stdout('Aborting.');
            return ExitCode::OK;
        }

        try {
            Plugin::getInstance()->getCustomers()->moveCustomerDataToCustomer($fromUser, $toUser);
        } catch (Exception $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
