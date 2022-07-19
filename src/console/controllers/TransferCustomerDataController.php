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
 * Transfers customer data from one user to another
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.1.0
 */
class TransferCustomerDataController extends Controller
{
    /**
     * @var string|null The User email or username of the user that is having their commerce content moved.
     */
    public ?string $fromUser = null;

    /**
     * @var string|null The User email or username of the user that is having the commerce content moved to.
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

        $this->fromUser = $this->prompt('Move Commerce data from user (email or username):', [
            'required' => true,
            'default' => $this->fromUser ?? '',
        ]);

        $this->toUser = $this->prompt('To user (email or username):', [
            'required' => true,
            'default' => $this->toUser ?? '',
        ]);

        if ($this->fromUser === '' || $this->toUser === '') {
            $this->stderr("No 'fromUser' or 'toUser' specified.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $fromUser = Craft::$app->getUsers()->getUserByUsernameOrEmail($this->fromUser);
        $toUser = Craft::$app->getUsers()->getUserByUsernameOrEmail($this->toUser);

        if ($fromUser === null) {
            $this->stderr("No user with {$this->fromUser} found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($toUser === null) {
            $this->stderr("No user with {$this->toUser} found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $confirm = $this->confirm('Are you sure you want to move all Commerce data from user: ' . $this->fromUser . ' to user: ' . $this->toUser . '? (y/n)');
        if (!$confirm) {
            $this->stdout('Aborting.');
            return ExitCode::OK;
        }

        try {
            Plugin::getInstance()->getCustomers()->transferCustomerData($fromUser, $toUser);
        } catch (Exception $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
