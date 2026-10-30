<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\mutations;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Transfer as TransferElement;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\gql\base\ElementMutationResolver;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Throwable;

/**
 * Class Transfer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Transfer extends ElementMutationResolver
{
    /** @inheritdoc */
    protected array $immutableAttributes = ['id', 'uid', 'details', 'markAsPending'];

    /**
     * Saves an inventory transfer using the passed arguments.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return TransferElement
     * @throws Throwable
     */
    public function saveTransfer(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): TransferElement
    {
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);
        $this->requireSchemaAction('transfers.all', $canIdentify ? 'save' : 'create');

        if ($canIdentify) {
            $query = TransferElement::find()->status(null);
            if (!empty($arguments['uid'])) {
                $query->uid($arguments['uid']);
            } else {
                $query->id($arguments['id']);
            }

            /** @var TransferElement|null $transfer */
            $transfer = $query->one();

            if (!$transfer) {
                throw new Error('No such transfer exists');
            }
        } else {
            $transfer = new TransferElement();
        }

        $isDraft = $transfer->getTransferStatus() === TransferStatusType::DRAFT;

        // Once a transfer is pending, its inventory has been updated, so its locations and items are locked
        if (
            !$isDraft &&
            (isset($arguments['originLocationId']) || isset($arguments['destinationLocationId']) || isset($arguments['details']) || !empty($arguments['markAsPending']))
        ) {
            throw new Error('Only draft transfers’ locations and items can be changed.');
        }

        /** @var TransferElement $transfer */
        $transfer = $this->populateElementWithData($transfer, $arguments, $resolveInfo);

        if (isset($arguments['details'])) {
            $transfer->setDetails(array_map(fn(array $data) => $this->detail($data), $arguments['details']));
        }

        if (!empty($arguments['markAsPending'])) {
            $transfer->setTransferStatus(TransferStatusType::PENDING);
        }

        $transfer = $this->saveElement($transfer);

        /** @var TransferElement */
        return TransferElement::find()->id($transfer->id)->status(null)->one();
    }

    /**
     * Deletes a draft inventory transfer identified by the passed arguments.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return bool
     * @throws Throwable
     */
    public function deleteTransfer(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): bool
    {
        $this->requireSchemaAction('transfers.all', 'delete');

        /** @var TransferElement|null $transfer */
        $transfer = TransferElement::find()->id($arguments['id'])->status(null)->one();

        if (!$transfer) {
            return false;
        }

        if ($transfer->getTransferStatus() !== TransferStatusType::DRAFT) {
            throw new Error('Only draft transfers can be deleted.');
        }

        $this->requireUserAuthorization($transfer, 'delete');

        return Craft::$app->getElements()->deleteElement($transfer, $arguments['hardDelete'] ?? false);
    }

    /**
     * Creates a transfer detail from submitted data.
     *
     * @param array $data
     * @return TransferDetail
     * @throws Error
     */
    private function detail(array $data): TransferDetail
    {
        $inventoryItemId = $data['inventoryItemId'] ?? null;

        if (!$inventoryItemId && !empty($data['purchasableId'])) {
            $purchasable = Craft::$app->getElements()->getElementById($data['purchasableId']);
            if (!$purchasable instanceof Purchasable) {
                throw new Error('No such purchasable exists');
            }
            $inventoryItemId = Plugin::getInstance()->getInventory()->getInventoryItemByPurchasable($purchasable)->id;
        }

        if (!$inventoryItemId) {
            throw new Error('An inventory item ID or purchasable ID is required for each transfer item.');
        }

        return new TransferDetail([
            'inventoryItemId' => $inventoryItemId,
            'quantity' => $data['quantity'],
        ]);
    }
}
