<?php

/**
 * Aligent Consulting
 * Copyright (c) Aligent Consulting (https://www.aligent.com.au)
 */

declare(strict_types=1);

namespace Aligent\AsyncEvents\Model\Indexer\DataProvider;

use Aligent\AsyncEvents\Model\ResourceModel\AsyncEventLog\Collection;
use Aligent\AsyncEvents\Model\ResourceModel\AsyncEventLog\CollectionFactory as AsyncEventLogCollectionFactory;

class AsyncEventSubscriberLogs
{
    /**
     * @var AsyncEventLogCollectionFactory
     */
    private $collectionFactory;

    /**
     * @param AsyncEventLogCollectionFactory $collectionFactory
     */
    public function __construct(
        AsyncEventLogCollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param string $asyncEvent
     * @param array|null $logIds
     * @return Collection
     */
    public function getAsyncEventLogs(string $asyncEvent, array $logIds = null)
    {
        $logCollection = $this->collectionFactory->create();

        $logCollection->getSelect()
            ->join(
                ['ae' => 'async_event_subscriber'],
                'ae.subscription_id = main_table.subscription_id',
                ['event_name']
            )
            ->where('ae.event_name = ?', $asyncEvent);

        if ($logIds !== null) {
            $logCollection->addFieldToFilter('log_id', ['in' => $logIds]);
        }

        return $logCollection;
    }
}
