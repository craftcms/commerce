<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\TransferCustomerData;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Customer\Customers;
use Illuminate\Console\Command;
use Override;
use Throwable;

class TransferCustomerDataCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'commerce:transfer-customer-data
        {--from-user= : The email or username of the user that is having their commerce content moved.}
        {--to-user= : The email or username of the user that is having the commerce content moved to.}
    ';

    #[Override]
    protected $description = 'Transfers commerce data from one user to another.';

    #[Override]
    protected $aliases = ['commerce/transfer-customer-data'];

    public function handle(Customers $customers): int
    {
        $this->line('This command will transfer all commerce data from one user to another.');

        $fromUserIdentifier = $this->option('from-user') ?: $this->ask('Move Commerce data from user (email or username)');
        $toUserIdentifier = $this->option('to-user') ?: $this->ask('To user (email or username)');

        if (!$fromUserIdentifier || !$toUserIdentifier) {
            $this->components->error('You must specify both a "to" and "from" user.');

            return self::FAILURE;
        }

        $fromUser = Users::getUserByUsernameOrEmail($fromUserIdentifier);
        $toUser = Users::getUserByUsernameOrEmail($toUserIdentifier);

        if ($fromUser === null) {
            $this->components->error("No user found with a username or email of `$fromUserIdentifier`.");

            return self::FAILURE;
        }

        if ($toUser === null) {
            $this->components->error("No user found with a username or email of `$toUserIdentifier`.");

            return self::FAILURE;
        }

        if ($fromUser->id === $toUser->id) {
            $this->components->error('The transfer must happen between different users.');

            return self::FAILURE;
        }

        if (!$this->confirm("Are you sure you want to move all Commerce data from user: $fromUserIdentifier to user: $toUserIdentifier?")) {
            $this->components->warn('No data will be moved.');

            return self::SUCCESS;
        }

        try {
            $customers->transferCustomerData($fromUser, $toUser);
        } catch (Throwable $e) {
            $this->components->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Done!');

        return self::SUCCESS;
    }
}
