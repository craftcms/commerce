<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\queue\jobs\CatalogPricing as CatalogPricingJob;
use craft\commerce\records\CatalogPricingQueue as CatalogPricingQueueRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craftcommercetests\fixtures\StoreFixture;
use ReflectionProperty;
use UnitTester;
use yii\queue\ExecEvent;

/**
 * CatalogPricingQueueTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class CatalogPricingQueueTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    private int $_storeId;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'stores' => [
                'class' => StoreFixture::class,
            ],
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        // Clear the catalog pricing queue table before each test
        CatalogPricingQueueRecord::deleteAll();
        $this->_storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
    }

    protected function _after(): void
    {
        parent::_after();
        // Clean up the queue table after each test
        CatalogPricingQueueRecord::deleteAll();
    }

    private function _lastQueueJobId(): int
    {
        return (int)(new Query())->from(CraftTable::QUEUE)->max('[[id]]');
    }

    /**
     * @return array<int, array{id: int|string, priority: int|string}>
     */
    private function _queueJobsSince(int $id): array
    {
        return (new Query())
            ->select(['id', 'priority'])
            ->from(CraftTable::QUEUE)
            ->where(['>', 'id', $id])
            ->all();
    }

    private function _createJob(array $config): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();
        $service->createCatalogPricingJob($config);
        $service->flushBufferedCatalogPricingIds();
    }

    /**
     * Test that queue writes are held back while a transaction is open and written on flush.
     */
    public function testQueueIdsAreBufferedUntilFlushed(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();

        $service->createCatalogPricingJob(['purchasableIds' => [1, 2], 'storeId' => $this->_storeId]);
        $service->createCatalogPricingJob(['purchasableIds' => [2, 3], 'storeId' => $this->_storeId]);

        $this->assertCount(0, CatalogPricingQueueRecord::find()->all());

        $service->flushBufferedCatalogPricingIds();

        $rows = CatalogPricingQueueRecord::find()->all();
        $this->assertCount(1, $rows);
        $this->assertSame([1, 2, 3], $rows[0]->getIds());

        $service->flushBufferedCatalogPricingIds();
        $this->assertCount(1, CatalogPricingQueueRecord::find()->all());
    }

    /**
     * Test that a real commit flushes the buffer and a real rollback discards it.
     */
    public function testBufferedIdsFlushOnCommitAndClearOnRollback(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();
        $db = \Craft::$app->getDb();
        $db->getTransaction()?->rollBack();
        CatalogPricingQueueRecord::deleteAll();
        $lastJobId = $this->_lastQueueJobId();

        try {
            $transaction = $db->beginTransaction();
            $service->createCatalogPricingJob(['purchasableIds' => [1, 2]]);
            self::assertCount(0, CatalogPricingQueueRecord::find()->all());
            $transaction->commit();

            $rows = CatalogPricingQueueRecord::find()->all();
            self::assertCount(1, $rows);
            self::assertSame([1, 2], $rows[0]->getIds());

            CatalogPricingQueueRecord::deleteAll();

            $transaction = $db->beginTransaction();
            $service->createCatalogPricingJob(['purchasableIds' => [3]]);
            $transaction->rollBack();
            self::assertCount(0, CatalogPricingQueueRecord::find()->all());

            $service->flushBufferedCatalogPricingIds();
            self::assertCount(0, CatalogPricingQueueRecord::find()->all());
        } finally {
            CatalogPricingQueueRecord::deleteAll();
            $db->createCommand()->delete(CraftTable::QUEUE, ['>', 'id', $lastJobId])->execute();
        }
    }

    /**
     * Test that a rollback discards only the current transaction's IDs and keeps earlier committed retry IDs.
     */
    public function testRollbackKeepsEarlierRetryIds(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();
        $db = \Craft::$app->getDb();
        $db->getTransaction()?->rollBack();
        CatalogPricingQueueRecord::deleteAll();
        $lastJobId = $this->_lastQueueJobId();

        $retryIds = new ReflectionProperty($service, '_retryQueueIds');
        $retryIds->setValue($service, ['null|purchasable' => ['storeId' => null, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [9], 'priority' => null]]);

        try {
            $transaction = $db->beginTransaction();
            $service->createCatalogPricingJob(['purchasableIds' => [3]]);
            $transaction->rollBack();

            self::assertCount(0, CatalogPricingQueueRecord::find()->all());

            $service->flushBufferedCatalogPricingIds();

            $rows = CatalogPricingQueueRecord::find()->all();
            self::assertCount(1, $rows);
            self::assertSame([9], $rows[0]->getIds());
        } finally {
            $retryIds->setValue($service, []);
            CatalogPricingQueueRecord::deleteAll();
            $db->createCommand()->delete(CraftTable::QUEUE, ['>', 'id', $lastJobId])->execute();
        }
    }

    /**
     * Test that a priority above the default is kept rather than promoted to the default.
     */
    public function testPriorityAboveDefaultIsPreserved(): void
    {
        $lastJobId = $this->_lastQueueJobId();

        $service = Plugin::getInstance()->getCatalogPricing();
        $service->createCatalogPricingJob(['purchasableIds' => [1], 'storeId' => $this->_storeId], 500);
        $service->flushBufferedCatalogPricingIds();

        $jobs = $this->_queueJobsSince($lastJobId);
        self::assertCount(1, $jobs);
        self::assertSame(500, (int)$jobs[0]['priority']);
    }

    /**
     * Test that failed work being retried keeps the priority it was originally requested with.
     */
    public function testRetryEntriesKeepTheirPriority(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();
        $retryIds = new ReflectionProperty($service, '_retryQueueIds');
        $retryIds->setValue($service, ['1|purchasable' => ['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [9], 'priority' => 500]]);
        $lastJobId = $this->_lastQueueJobId();

        $service->flushBufferedCatalogPricingIds();

        $jobs = $this->_queueJobsSince($lastJobId);
        self::assertCount(1, $jobs);
        self::assertSame(500, (int)$jobs[0]['priority']);
        self::assertSame([9], CatalogPricingQueueRecord::find()->one()->getIds());
    }

    /**
     * Test that the lowest requested priority wins when calls are merged.
     */
    public function testLowestPriorityWinsWhenMerged(): void
    {
        $lastJobId = $this->_lastQueueJobId();

        $service = Plugin::getInstance()->getCatalogPricing();
        $service->createCatalogPricingJob(['purchasableIds' => [1], 'storeId' => $this->_storeId], 500);
        $service->createCatalogPricingJob(['purchasableIds' => [2], 'storeId' => $this->_storeId], 50);
        $service->flushBufferedCatalogPricingIds();

        $jobs = $this->_queueJobsSince($lastJobId);
        self::assertCount(1, $jobs);
        self::assertSame(50, (int)$jobs[0]['priority']);
    }

    /**
     * Test that a job processes exactly one row and queues a follow-up job while more rows are waiting.
     */
    public function testJobProcessesOneRowAndRequeuesWhileMoreAreWaiting(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();
        $beforeId = $this->_lastQueueJobId();
        $service->createCatalogPricingJob(['purchasableIds' => [1], 'storeId' => $this->_storeId, 'catalogPricingRuleIds' => [2]], 50);
        $service->flushBufferedCatalogPricingIds();
        self::assertCount(2, CatalogPricingQueueRecord::find()->all());
        $jobs = $this->_queueJobsSince($beforeId);
        self::assertCount(1, $jobs);
        self::assertSame(50, (int)$jobs[0]['priority']);
        $lastJobId = $this->_lastQueueJobId();

        (new CatalogPricingJob(['priority' => 50]))->execute(\Craft::$app->getQueue());

        self::assertCount(1, CatalogPricingQueueRecord::find()->all());
        $followUps = $this->_queueJobsSince($lastJobId);
        self::assertCount(1, $followUps);
        self::assertSame(50, (int)$followUps[0]['priority']);
    }

    /**
     * Test that a job doesn't queue a follow-up once the last row is processed.
     */
    public function testJobDoesNotRequeueWhenNoRowsAreLeft(): void
    {
        $this->_createJob(['purchasableIds' => [1], 'storeId' => $this->_storeId]);
        $lastJobId = $this->_lastQueueJobId();

        (new CatalogPricingJob())->execute(\Craft::$app->getQueue());

        self::assertCount(0, CatalogPricingQueueRecord::find()->all());
        self::assertCount(0, $this->_queueJobsSince($lastJobId));
    }

    /**
     * Serialized form of the job as it was queued before the `priority` property existed.
     */
    private function _legacyJobPayload(string $purchasableIds = 'N;', string $storeId = 'N;'): string
    {
        return 'O:40:"craft\commerce\queue\jobs\CatalogPricing":6:{'
            . 's:11:"description";N;'
            . 's:30:"' . "\0" . 'craft\queue\BaseJob' . "\0" . '_progress";i:0;'
            . 's:35:"' . "\0" . 'craft\queue\BaseJob' . "\0" . '_progressLabel";N;'
            . 's:14:"purchasableIds";' . $purchasableIds
            . 's:21:"catalogPricingRuleIds";N;'
            . 's:7:"storeId";' . $storeId
            . '}';
    }

    private function _handleQueuedPayload(string $payload, int $attempt): bool
    {
        $queue = \Craft::$app->getQueue();
        $id = $queue->push(new CatalogPricingJob());
        $method = new \ReflectionMethod($queue, 'handleMessage');

        return $method->invoke($queue, $id, $payload, 300, $attempt);
    }

    /**
     * @return bool whether the queue will retry the previous-release payload after it failed on the given attempt
     */
    private function _willRetryFailedPayload(string $payload, int $attempt): bool
    {
        $queue = \Craft::$app->getQueue();
        $event = new ExecEvent([
            'id' => $queue->push(new CatalogPricingJob()),
            'job' => unserialize($payload),
            'ttr' => 300,
            'attempt' => $attempt,
            'error' => new \RuntimeException('failed'),
        ]);
        $queue->handleError($event);

        return $event->retry;
    }

    private function _seedRow(string $type, array $ids): void
    {
        $record = new CatalogPricingQueueRecord();
        $record->storeId = $this->_storeId;
        $record->type = $type;
        $record->setIds($ids);
        $record->reserved = false;
        $record->save(false);
    }

    /**
     * Test that a consolidated job queued by the previous release unserializes without a priority and still drains the queue table.
     */
    public function testLegacyConsolidatedPayloadWithoutPriorityStillProcesses(): void
    {
        $job = unserialize($this->_legacyJobPayload());

        self::assertInstanceOf(CatalogPricingJob::class, $job);
        self::assertNull($job->priority);

        $this->_seedRow(CatalogPricingQueueRecord::TYPE_PURCHASABLE, [1]);
        $this->_seedRow(CatalogPricingQueueRecord::TYPE_RULE, [2]);
        $lastJobId = $this->_lastQueueJobId();

        $job->execute(\Craft::$app->getQueue());

        self::assertCount(1, CatalogPricingQueueRecord::find()->all());
        $followUps = $this->_queueJobsSince($lastJobId);
        self::assertCount(1, $followUps);
        self::assertSame(1024, (int)$followUps[0]['priority']);
    }

    /**
     * Test that an explicit-property job queued by the previous release still runs and leaves the queue table alone.
     */
    public function testLegacyExplicitPayloadStillProcesses(): void
    {
        $job = unserialize($this->_legacyJobPayload('a:1:{i:0;i:7;}', 'i:' . $this->_storeId . ';'));

        self::assertSame([7], $job->purchasableIds);
        self::assertNull($job->priority);

        $this->_seedRow(CatalogPricingQueueRecord::TYPE_PURCHASABLE, [1]);
        $lastJobId = $this->_lastQueueJobId();

        $job->execute(\Craft::$app->getQueue());

        self::assertCount(1, CatalogPricingQueueRecord::find()->all());
        self::assertCount(0, $this->_queueJobsSince($lastJobId));
    }

    /**
     * Test that the queue completes a previous-release payload that already has failed attempts.
     */
    public function testQueueCompletesLegacyPayloadWithExistingAttemptCount(): void
    {
        $this->_seedRow(CatalogPricingQueueRecord::TYPE_PURCHASABLE, [1]);

        self::assertTrue($this->_handleQueuedPayload($this->_legacyJobPayload(), 2));
        self::assertCount(0, CatalogPricingQueueRecord::find()->all());
    }

    /**
     * Test that a previous-release payload is retried until the attempt limit.
     */
    public function testQueueRetriesFailingLegacyPayloadUntilAttemptLimit(): void
    {
        $payload = $this->_legacyJobPayload();

        self::assertTrue($this->_willRetryFailedPayload($payload, 1));
        self::assertTrue($this->_willRetryFailedPayload($payload, 2));
        self::assertFalse($this->_willRetryFailedPayload($payload, 3));
    }

    /**
     * @return array<string, array{0: array{purchasableIds?: int[], storeId?: int|null}, 1: bool}>
     */
    public function rulesCacheJobsDataProvider(): array
    {
        return [
            'consolidated job' => [[], true],
            'explicit-ID job' => [['purchasableIds' => [1]], false],
        ];
    }

    /**
     * Test that every kind of job discards memoized rules before regenerating prices.
     *
     * @dataProvider rulesCacheJobsDataProvider
     */
    public function testJobsDiscardMemoizedRulesBeforeProcessing(array $config, bool $seedRow): void
    {
        if ($seedRow) {
            $this->_seedRow(CatalogPricingQueueRecord::TYPE_PURCHASABLE, [1]);
        }

        $rules = Plugin::getInstance()->getCatalogPricingRules();
        $stale = collect(['stale']);
        $cache = new ReflectionProperty($rules, '_allCatalogPricingRules');
        $cache->setValue($rules, [$this->_storeId => $stale]);

        (new CatalogPricingJob($config))->execute(\Craft::$app->getQueue());

        $current = $cache->getValue($rules);
        self::assertTrue($current === null || ($current[$this->_storeId] ?? null) !== $stale);
    }

    /**
     * Test that the job reports the queue's configured TTR and allows two retries.
     */
    public function testJobUsesQueueTtrAndLimitsRetries(): void
    {
        $queue = \Craft::$app->getQueue();
        $originalTtr = $queue->ttr;
        $queue->ttr = 900;

        try {
            $job = new CatalogPricingJob();
            self::assertSame(900, $job->getTtr());
        } finally {
            $queue->ttr = $originalTtr;
        }

        self::assertTrue($job->canRetry(1, new \Exception()));
        self::assertTrue($job->canRetry(2, new \Exception()));
        self::assertFalse($job->canRetry(3, new \Exception()));
    }

    /**
     * Test that unreserved rows are reported until they are reserved.
     */
    public function testHasUnreservedRows(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();
        self::assertFalse($service->hasUnreservedCatalogPricingQueueRows());

        $this->_createJob(['purchasableIds' => [1], 'storeId' => $this->_storeId]);
        self::assertTrue($service->hasUnreservedCatalogPricingQueueRows());

        $service->reserveCatalogPricingQueueRow();
        self::assertFalse($service->hasUnreservedCatalogPricingQueueRows());
    }

    /**
     * Test that a buffered null scope wins over specific IDs.
     */
    public function testBufferedNullIdsExpandScope(): void
    {
        $service = Plugin::getInstance()->getCatalogPricing();

        $service->createCatalogPricingJob(['purchasableIds' => [1], 'storeId' => $this->_storeId]);
        $service->createCatalogPricingJob(['storeId' => $this->_storeId]);
        $service->flushBufferedCatalogPricingIds();

        $rows = CatalogPricingQueueRecord::find()->all();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->getIds());
    }

    /**
     * Test that a single purchasable ID creates a new queue row.
     *
     * @see https://github.com/craftcms/commerce/issues/4277
     */
    public function testCreateQueueRowForSinglePurchasableId(): void
    {
        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $rows = CatalogPricingQueueRecord::find()->all();
        self::assertCount(1, $rows);

        /** @var CatalogPricingQueueRecord $row */
        $row = $rows[0];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_PURCHASABLE, $row->type);
        self::assertEquals($this->_storeId, $row->storeId);
        self::assertEquals([1], $row->getIds());
        self::assertFalse((bool)$row->reserved);
    }

    /**
     * Test that a single rule ID creates a new queue row.
     */
    public function testCreateQueueRowForSingleRuleId(): void
    {
        $this->_createJob([
            'catalogPricingRuleIds' => [5],
            'storeId' => $this->_storeId,
        ]);

        $rows = CatalogPricingQueueRecord::find()->all();
        self::assertCount(1, $rows);

        /** @var CatalogPricingQueueRecord $row */
        $row = $rows[0];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_RULE, $row->type);
        self::assertEquals($this->_storeId, $row->storeId);
        self::assertEquals([5], $row->getIds());
        self::assertFalse((bool)$row->reserved);
    }

    /**
     * Test that purchasable and rule IDs create separate queue rows.
     */
    public function testPurchasableAndRuleTypesAreSeparated(): void
    {
        $this->_createJob([
            'purchasableIds' => [1, 2],
            'catalogPricingRuleIds' => [5, 6],
            'storeId' => $this->_storeId,
        ]);

        /** @var CatalogPricingQueueRecord[] $rows */
        $rows = CatalogPricingQueueRecord::find()->orderBy(['type' => SORT_ASC])->all();
        self::assertCount(2, $rows);

        // First row should be purchasable type
        $purchasableRow = $rows[0];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_PURCHASABLE, $purchasableRow->type);
        self::assertEquals([1, 2], $purchasableRow->getIds());

        // Second row should be rule type
        $ruleRow = $rows[1];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_RULE, $ruleRow->type);
        self::assertEquals([5, 6], $ruleRow->getIds());
    }

    /**
     * Test that different stores create separate queue rows for the same type.
     */
    public function testDifferentStoresCreateSeparateRows(): void
    {
        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $ukStore = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');

        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $primaryStore->id,
        ]);

        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $ukStore->id,
        ]);

        /** @var CatalogPricingQueueRecord[] $rows */
        $rows = CatalogPricingQueueRecord::find()->orderBy(['storeId' => SORT_ASC])->all();
        self::assertCount(2, $rows);

        self::assertEquals($primaryStore->id, $rows[0]->storeId);
        self::assertEquals($ukStore->id, $rows[1]->storeId);
    }

    /**
     * Test that multiple calls to queue IDs for the same store/type merge into one row.
     */
    public function testMultipleQueuesForSameStoreAndTypeMerge(): void
    {
        $this->_createJob([
            'purchasableIds' => [1, 2],
            'storeId' => $this->_storeId,
        ]);

        $this->_createJob([
            'purchasableIds' => [3, 4],
            'storeId' => $this->_storeId,
        ]);

        $rows = CatalogPricingQueueRecord::find()->all();
        self::assertCount(1, $rows, 'Multiple queue calls should merge into a single row');

        $row = $rows[0];
        self::assertEquals([1, 2, 3, 4], $row->getIds(), 'IDs should be merged and sorted');
    }

    /**
     * Test that duplicate IDs in merged rows are deduplicated.
     */
    public function testDuplicateIdsAreDeduplicated(): void
    {
        $this->_createJob([
            'purchasableIds' => [1, 2, 3],
            'storeId' => $this->_storeId,
        ]);

        $this->_createJob([
            'purchasableIds' => [2, 3, 4],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::findOne(['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE]);
        self::assertEquals([1, 2, 3, 4], $row->getIds(), 'Duplicate IDs should be removed and sorted');
    }

    /**
     * Test that null storeId (meaning all stores) is properly handled.
     */
    public function testNullStoreIdRepresentsAllStores(): void
    {
        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => null,
        ]);

        $row = CatalogPricingQueueRecord::findOne(['type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE]);
        self::assertNull($row->storeId, 'storeId should be null to represent all stores');
        self::assertEquals([1], $row->getIds());
    }

    /**
     * Test that merging IDs where one side is null expands scope to null (all stores/ids).
     */
    public function testMergingWithNullIdsExpandsScope(): void
    {
        // First queue specific IDs
        $this->_createJob([
            'purchasableIds' => [1, 2],
            'storeId' => $this->_storeId,
        ]);

        // Then queue with null (meaning all), should merge and expand scope
        $this->_createJob([
            'purchasableIds' => null,
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::findOne(['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE]);
        self::assertNull($row->getIds(), 'IDs should be null (broader scope) when merging specific IDs with null');
    }

    /**
     * Test that reserved rows are not merged into.
     */
    public function testReservedRowsAreNotMergedInto(): void
    {
        // Create and reserve a row
        $record = new CatalogPricingQueueRecord();
        $record->storeId = $this->_storeId;
        $record->type = CatalogPricingQueueRecord::TYPE_PURCHASABLE;
        $record->setIds([1]);
        $record->reserved = true;
        $record->save(false);

        // Try to queue more IDs for the same store/type
        $this->_createJob([
            'purchasableIds' => [2],
            'storeId' => $this->_storeId,
        ]);

        /** @var CatalogPricingQueueRecord[] $rows */
        $rows = CatalogPricingQueueRecord::find()
            ->where(['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE])
            ->orderBy(['reserved' => SORT_DESC])
            ->all();

        self::assertCount(2, $rows, 'A new row should be created instead of merging into the reserved row');

        // One should be reserved with ID 1
        $reservedRow = $rows[0];
        self::assertNotNull($reservedRow);
        self::assertEquals([1], $reservedRow->getIds());

        // One should be unreserved with ID 2
        $unreservedRow = $rows[1];
        self::assertNotNull($unreservedRow);
        self::assertEquals([2], $unreservedRow->getIds());
    }

    /**
     * Test that IDs are sorted numerically in the queue row.
     */
    public function testIdsAreSortedNumerically(): void
    {
        $this->_createJob([
            'purchasableIds' => [100, 5, 50, 1],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::findOne(['storeId' => $this->_storeId]);
        self::assertEquals([1, 5, 50, 100], $row->getIds(), 'IDs should be sorted numerically');
    }

    /**
     * Test that zero and negative IDs are filtered out.
     */
    public function testZeroAndNegativeIdsAreFiltered(): void
    {
        $this->_createJob([
            'purchasableIds' => [1, 0, -5, 2],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::findOne(['storeId' => $this->_storeId]);
        self::assertEquals([1, 2], $row->getIds(), 'Zero and negative IDs should be filtered out');
    }

    /**
     * Test that `areCatalogPricingJobsRunning()` returns true when queue has pending rows.
     */
    public function testAreCatalogPricingJobsRunningReturnsTrueWhenPending(): void
    {
        self::assertFalse(Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(), 'Should be false when queue is empty');

        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        self::assertTrue(Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(), 'Should be true when queue has pending rows');
    }

    /**
     * Test that `reserveCatalogPricingQueueRow()` marks a row as reserved.
     */
    public function testReserveCatalogPricingQueueRowMarksAsReserved(): void
    {
        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        // All rows should be unreserved initially
        self::assertCount(0, CatalogPricingQueueRecord::find()->where(['reserved' => true])->all());

        $reserved = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();

        self::assertNotNull($reserved, 'Should return a reserved row');
        self::assertTrue((bool)$reserved->reserved);
        self::assertEquals([1], $reserved->getIds());

        // Verify in database
        $dbRow = CatalogPricingQueueRecord::findOne($reserved->id);
        self::assertTrue((bool)$dbRow->reserved);
    }

    /**
     * Test that multiple pending rows can be reserved one at a time.
     */
    public function testMultiplePendingRowsCanBeReservedInOrder(): void
    {
        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $this->_createJob([
            'purchasableIds' => [2],
            'storeId' => Plugin::getInstance()->getStores()->getStoreByHandle('ukStore')->id,
        ]);

        $first = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertNotNull($first);
        self::assertEquals([1], $first->getIds());

        $second = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertNotNull($second);
        self::assertEquals([2], $second->getIds());

        $third = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertNull($third, 'Should return null when no pending rows remain');
    }

    /**
     * Test that `releaseCatalogPricingQueueRowById()` marks a reserved row as unreserved.
     */
    public function testReleaseCatalogPricingQueueByIdMarksAsUnreserved(): void
    {
        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $reserved = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertTrue((bool)$reserved->reserved);

        Plugin::getInstance()->getCatalogPricing()->releaseCatalogPricingQueueRowById($reserved->id);

        $released = CatalogPricingQueueRecord::findOne($reserved->id);
        self::assertFalse((bool)$released->reserved);
    }

    /**
     * Test that `deleteCatalogPricingQueueRowById()` removes a row from the queue.
     */
    public function testDeleteCatalogPricingQueueByIdRemovesRow(): void
    {
        $this->_createJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::findOne(['storeId' => $this->_storeId]);
        self::assertNotNull($row);

        Plugin::getInstance()->getCatalogPricing()->deleteCatalogPricingQueueRowById($row->id);

        $deleted = CatalogPricingQueueRecord::findOne($row->id);
        self::assertNull($deleted);
    }

    /**
     * Test complex scenario: multiple stores and types with merging and reservation.
     *
     * @dataProvider complexQueueScenarioDataProvider
     */
    public function testComplexQueueScenario(array $queueCalls, array $expectedRows): void
    {
        $stores = Plugin::getInstance()->getStores()->getAllStores();
        // Execute all queue calls
        foreach ($queueCalls as $call) {
            $call['storeId'] = $stores->firstWhere('handle', $call['storeId'])?->id ?? null;
            $this->_createJob($call);
        }

        // Verify the state of all rows
        /** @var CatalogPricingQueueRecord[] $allRows */
        $allRows = CatalogPricingQueueRecord::find()->all();
        self::assertCount(count($expectedRows), $allRows, 'Should have expected number of rows');

        foreach ($expectedRows as $index => $expected) {
            $expected['storeId'] = $stores->firstWhere('handle', $expected['storeId'])?->id ?? null;
            $row = $allRows[$index] ?? null;
            self::assertNotNull($row, "Row at index $index should exist");
            self::assertEquals($expected['storeId'] ?? null, $row->storeId, "Row $index storeId mismatch");
            self::assertEquals($expected['type'], $row->type, "Row $index type mismatch");
            self::assertEquals($expected['ids'], $row->getIds(), "Row $index IDs mismatch");
        }
    }

    public function complexQueueScenarioDataProvider(): array
    {
        $primaryStoreHandle = 'primary';
        $ukStoreHandle = 'ukStore';

        return [
            'single-store-multiple-types' => [
                [
                    ['purchasableIds' => [1, 2], 'storeId' => $primaryStoreHandle],
                    ['catalogPricingRuleIds' => [10, 11], 'storeId' => $primaryStoreHandle],
                ],
                [
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_RULE, 'ids' => [10, 11]],
                ],
            ],
            'multi-store-same-type-merging' => [
                [
                    ['purchasableIds' => [1], 'storeId' => $primaryStoreHandle],
                    ['purchasableIds' => [2], 'storeId' => $primaryStoreHandle],
                    ['purchasableIds' => [1], 'storeId' => $ukStoreHandle],
                ],
                [
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
                    ['storeId' => $ukStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1]],
                ],
            ],
            'null-store-with-specific-stores' => [
                [
                    ['purchasableIds' => [1, 2], 'storeId' => $primaryStoreHandle],
                    ['purchasableIds' => [3], 'storeId' => null],
                ],
                [
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
                    ['storeId' => null, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [3]],
                ],
            ],
        ];
    }
}
