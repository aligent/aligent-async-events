<?php

/**
 * Aligent Consulting
 * Copyright (c) Aligent Consulting (https://www.aligent.com.au)
 */

declare(strict_types=1);

namespace Aligent\AsyncEvents\Model\Adapter\BatchDataMapper;

use Magento\Elasticsearch\Model\Adapter\BatchDataMapperInterface;
use Magento\Elasticsearch\Model\Adapter\Document\Builder;
use Magento\Framework\Serialize\SerializerInterface;

class AsyncEventLogMapper implements BatchDataMapperInterface
{

    /**
     * @var Builder
     */
    private $builder;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        Builder $builder,
        SerializerInterface $serializer
    ) {
        $this->builder = $builder;
        $this->serializer = $serializer;
    }

    public function map(array $documentData, $storeId, array $context = [])
    {
        $documents = [];

        foreach ($documentData as $asyncEventLogId => $indexData) {
            $this->builder->addField('log_id', $indexData['log_id']);
            $this->builder->addField('uuid', $indexData['uuid']);
            $this->builder->addField('success', $indexData['success']);
            $this->builder->addField('created', $indexData['created']);

            $this->builder->addField(
                'data',
                $this->serializer->unserialize($indexData['serialized_data'])
            );

            $documents[$asyncEventLogId] = $this->builder->build();
        }

        return $documents;
    }
}
