<?php

/**
 * Aligent Consulting
 * Copyright (c) Aligent Consulting (https://www.aligent.com.au)
 */

declare(strict_types=1);

namespace Aligent\Webhooks\Service\Webhook;

use Aligent\Webhooks\Helper\QueueMetadataInterface;
use Magento\Framework\Amqp\ConfigPool;
use Magento\Framework\Amqp\Topology\BindingInstallerInterface;
use Magento\Framework\Amqp\Topology\QueueInstaller;
use Magento\Framework\MessageQueue\Topology\Config\ExchangeConfigItem\BindingFactory;
use Magento\Framework\MessageQueue\Topology\Config\QueueConfigItemFactory;
use Magento\Framework\Serialize\SerializerInterface;

class RetryManager
{
    /**
     * @var ConfigPool
     */
    private ConfigPool $configPool;

    /**
     * @var QueueInstaller
     */
    private QueueInstaller $queueInstaller;

    /**
     * @var BindingInstallerInterface
     */
    private BindingInstallerInterface $bindingInstaller;

    /**
     * @var AmqpPublisher
     */
    private AmqpPublisher $publisher;

    /**
     * @var QueueConfigItemFactory
     */
    private QueueConfigItemFactory $queueConfigItemFactory;

    /**
     * @var BindingFactory
     */
    private BindingFactory $bindingFactory;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @param ConfigPool $configPool
     * @param QueueInstaller $queueInstaller
     * @param BindingInstallerInterface $bindingInstaller
     * @param AmqpPublisher $publisher
     * @param QueueConfigItemFactory $queueConfigItemFactory
     * @param BindingFactory $bindingFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ConfigPool $configPool,
        QueueInstaller $queueInstaller,
        BindingInstallerInterface $bindingInstaller,
        AmqpPublisher $publisher,
        QueueConfigItemFactory $queueConfigItemFactory,
        BindingFactory $bindingFactory,
        SerializerInterface $serializer
    ) {
        $this->queueInstaller = $queueInstaller;
        $this->bindingInstaller = $bindingInstaller;
        $this->configPool = $configPool;
        $this->publisher = $publisher;
        $this->queueConfigItemFactory = $queueConfigItemFactory;
        $this->bindingFactory = $bindingFactory;
        $this->serializer = $serializer;
    }

    /**
     * @param int $subscriptionId
     * @param $data
     */
    public function init(int $subscriptionId, $data): void
    {
        $this->assertDelayQueue(1, QueueMetadataInterface::RETRY_INIT_ROUTING_KEY, QueueMetadataInterface::RETRY_INIT_ROUTING_KEY);
        $this->publisher->publish(QueueMetadataInterface::RETRY_INIT_ROUTING_KEY, [$subscriptionId, 1, $this->serializer->serialize($data)]);
    }

    /**
     * @param int $deathCount
     * @param int $subscriptionId
     * @param $data
     */
    public function place(int $deathCount, int $subscriptionId, $data): void
    {
        $backoff = $this->calculateBackoff($deathCount);
        $queueName = 'webhook.delay.' . $backoff;
        $retryRoutingKey = 'webhook.retry.' . $backoff;

        $this->assertDelayQueue($backoff, $queueName, $retryRoutingKey);
        $this->publisher->publish($retryRoutingKey, [$subscriptionId, $deathCount, $this->serializer->serialize($data)]);
    }

    /**
     * @param int $subscriptionId
     * @param $data
     */
    public function kill(int $subscriptionId, $data): void
    {
        $this->publisher->publish(QueueMetadataInterface::DEAD_LETTER_KILL_KEY, [$subscriptionId, $this->serializer->serialize($data)]);
    }

    /**
     * Asserts the delay queue and binds it to the failover exchange.
     *
     * In RabbitMQ creating a queue is idempotent.
     * https://www.rabbitmq.com/tutorials/tutorial-one-php.html
     *
     * @param int $backoff
     * @param string $queueName
     * @param string $retryRoutingKey
     */
    private function assertDelayQueue(int $backoff, string $queueName, string $retryRoutingKey): void
    {
        $config = $this->configPool->get('amqp');

        $queueConfigItem = $this->queueConfigItemFactory->create();
        $queueConfigItem->setData([
            'name' => $queueName,
            'connection' => 'amqp',
            'durable' => true,
            'autoDelete' => true,
            'arguments' => [
                'x-dead-letter-exchange' => QueueMetadataInterface::FAILOVER_EXCHANGE,
                'x-dead-letter-routing-key' => QueueMetadataInterface::DEAD_LETTER_ROUTING_KEY,
                'x-message-ttl' => $backoff * 1000,
                'x-expires' => $backoff * 1000 * 2
            ]
        ]);

        $this->queueInstaller->install($config->getChannel(), $queueConfigItem);

        $bindingConfig = $this->bindingFactory->create();
        $bindingConfig->setData([
            'id' => 'WebhookRetry' . $backoff . 'Binding',
            'destinationType' => 'queue',
            'destination' => $queueName,
            'arguments' => [],
            'topic' => $retryRoutingKey,
            'disabled' => false
        ]);

        $this->bindingInstaller->install($config->getChannel(), $bindingConfig, QueueMetadataInterface::FAILOVER_EXCHANGE);
    }

    /**
     * Exponential back off. Change the exponent to determine cubical back off or quartic back off
     *
     * @param int $deathCount
     * @return int
     */
    private function calculateBackoff(int $deathCount): int
    {
        return min(60, pow($deathCount, 2));
    }
}
