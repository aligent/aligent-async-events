<?php

declare(strict_types=1);

namespace Aligent\AsyncEvents\Model;

use Aligent\AsyncEvents\Api\AsyncEventRepositoryInterface;
use Aligent\AsyncEvents\Helper\NotifierResult;
use Aligent\AsyncEvents\Service\AsyncEvent\NotifierFactoryInterface;
use Aligent\AsyncEvents\Service\AsyncEvent\RetryManager;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Serialize\SerializerInterface;

class RetryHandler
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var AsyncEventRepositoryInterface
     */
    private $asyncEventRepository;

    /**
     * @var NotifierFactoryInterface
     */
    private $notifierFactory;

    /**
     * @var AsyncEventLogFactory
     */
    private $asyncEventLogFactory;

    /**
     * @var AsyncEventLogRepository
     */
    private $asyncEventLogRepository;

    /**
     * @var RetryManager
     */
    private $retryManager;

    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param AsyncEventRepositoryInterface $asyncEventRepository
     * @param NotifierFactoryInterface $notifierFactory
     * @param AsyncEventLogFactory $asyncEventLogFactory
     * @param AsyncEventLogRepository $asyncEventLogRepository
     * @param RetryManager $retryManager
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        SearchCriteriaBuilder         $searchCriteriaBuilder,
        AsyncEventRepositoryInterface $asyncEventRepository,
        NotifierFactoryInterface      $notifierFactory,
        AsyncEventLogFactory          $asyncEventLogFactory,
        AsyncEventLogRepository       $asyncEventLogRepository,
        RetryManager                  $retryManager,
        SerializerInterface           $serializer,
        ScopeConfigInterface          $scopeConfig
    ) {
        $this->asyncEventRepository = $asyncEventRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->notifierFactory = $notifierFactory;
        $this->asyncEventLogFactory = $asyncEventLogFactory;
        $this->asyncEventLogRepository = $asyncEventLogRepository;
        $this->retryManager = $retryManager;
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param array $message
     */
    public function process(array $message)
    {
        $subscriptionId = $message[RetryManager::SUBSCRIPTION_ID];
        $deathCount = $message[RetryManager::DEATH_COUNT];
        $data = $message[RetryManager::CONTENT];
        $uuid = $message[RetryManager::UUID];

        $subscriptionId = (int) $subscriptionId;
        $deathCount = (int) $deathCount;
        $retryLimit = (int) $this->scopeConfig->getValue('system/async_events/death_count');

        $data = $this->serializer->unserialize($data);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('status', 1)
            ->addFilter('subscription_id', $subscriptionId)
            ->create();

        $asyncEvents = $this->asyncEventRepository->getList($searchCriteria)->getItems();

        foreach ($asyncEvents as $asyncEvent) {
            $handler = $asyncEvent->getMetadata();
            $notifier = $this->notifierFactory->create($handler);
            $response = $notifier->notify($asyncEvent, [
                'data' => $data
            ]);
            $response->setUuid($uuid);
            $this->log($response);

            if (!$response->getSuccess()) {
                if ($deathCount < $retryLimit) {
                    $this->retryManager->place($deathCount + 1, $subscriptionId, $data, $uuid);
                } else {
                    $this->retryManager->kill($subscriptionId, $data);
                }
            }
        }
    }

    /**
     * @param NotifierResult $response
     * @return void
     */
    private function log(NotifierResult $response)
    {
        /** @var AsyncEventLog $asyncEventLog */
        $asyncEventLog = $this->asyncEventLogFactory->create();
        $asyncEventLog->setSuccess($response->getSuccess());
        $asyncEventLog->setSubscriptionId($response->getSubscriptionId());
        $asyncEventLog->setResponseData($response->getResponseData());
        $asyncEventLog->setUuid($response->getUuid());
        $asyncEventLog->setSerializedData($response->getAsyncEventData());

        try {
            $this->asyncEventLogRepository->save($asyncEventLog);
        } catch (AlreadyExistsException $exception) {
            // Do nothing because a log entry can never already exist
        }
    }
}
