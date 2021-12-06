<?php

namespace Aligent\Webhooks\Model;

use Aligent\Webhooks\Api\Data\AsyncEventDisplayInterface;
use Aligent\Webhooks\Api\Data\AsyncEventInterface;
use Aligent\Webhooks\Api\Data\AsyncEventSearchResultsInterface;
use Aligent\Webhooks\Api\AsyncEventRepositoryInterface;
use Aligent\Webhooks\Model\Config as WebhookConfig;
use Aligent\Webhooks\Model\ResourceModel\AsyncEvent as WebhookResource;
use Aligent\Webhooks\Model\ResourceModel\Webhook\CollectionFactory as WebhookCollectionFactory;
use Aligent\Webhooks\Api\Data\AsyncEventSearchResultsInterfaceFactory as SearchResultsFactory;

use DateTime;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AsyncEventRepository implements AsyncEventRepositoryInterface
{
    /**
     * @var AsyncEventFactory
     */
    private  $webhookFactory;

    /**
     * @var WebhookResource
     */
    private  $webhookResource;

    /**
     * @var WebhookConfig
     */
    private  $webhookConfig;

    /**
     * @var SearchResultsFactory
     */
    private $searchResultsFactory;

    /**
     * @var WebhookCollectionFactory
     */
    private $webhookCollectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @param AsyncEventFactory $webhookFactory
     * @param WebhookResource $webhookResource
     * @param WebhookConfig $webhookConfig
     * @param SearchResultsFactory $searchResultsFactory
     * @param WebhookCollectionFactory $webhookCollectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param EncryptorInterface $encryptor
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        AsyncEventFactory $webhookFactory,
        WebhookResource $webhookResource,
        WebhookConfig $webhookConfig,
        SearchResultsFactory $searchResultsFactory,
        WebhookCollectionFactory $webhookCollectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        EncryptorInterface $encryptor,
        AuthorizationInterface $authorization
    ) {
        $this->webhookFactory = $webhookFactory;
        $this->webhookResource = $webhookResource;
        $this->webhookConfig = $webhookConfig;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->encryptor = $encryptor;
        $this->authorization = $authorization;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $subscriptionId): AsyncEventDisplayInterface
    {
        $webhook = $this->webhookFactory->create();
        $this->webhookResource->load($webhook, $subscriptionId);

        if (!$webhook->getId()) {
            throw new NoSuchEntityException(__('Webhook with subscription ID %1 does not exist', $subscriptionId));
        }

        return $webhook;
    }

    /**
     * {@inheritDoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AsyncEventSearchResultsInterface
    {
        $collection = $this->webhookCollectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $webhooks = [];

        /** @var AsyncEvent $webhookModel */
        foreach ($collection as $webhookModel) {
            $webhooks[] = $webhookModel;
        }

        return $this->searchResultsFactory->create()
            ->setItems($webhooks)
            ->setTotalCount($collection->getSize())
            ->setSearchCriteria($searchCriteria);
    }

    /**
     * {@inheritDoc}
     */
    public function save(AsyncEventInterface $asyncEvent, bool $checkResources = true): AsyncEventDisplayInterface
    {
        if ($checkResources) {
            $this->validateResources($asyncEvent);
        }

        if (!$asyncEvent->getSubscriptionId()) {
            $asyncEvent->setStatus(true);
            $asyncEvent->setSubscribedAt((new DateTime())->format(DateTime::ATOM));
            $secretVerificationToken = $this->encryptor->encrypt($asyncEvent->getVerificationToken());
            $asyncEvent->setVerificationToken($secretVerificationToken);

        } else {
            if ($asyncEvent->getStatus() === null) {
                throw new LocalizedException(__("Status is required"));
            }

            $newStatus = $asyncEvent->getStatus();
            $newMetadata = $asyncEvent->getMetadata();

            $asyncEvent = $this->get($asyncEvent->getSubscriptionId());
            $asyncEvent->setStatus($newStatus);

            if ($newMetadata) {
                $asyncEvent->setMetadata($newMetadata);
            }
        }

        $this->webhookResource->save($asyncEvent);

        return $asyncEvent;
    }

    /**
     * @param AsyncEventInterface $webhook
     * @return void
     * @throws AuthorizationException
     */
    private function validateResources(AsyncEventInterface $webhook)
    {
        $configData = $this->webhookConfig->get($webhook->getEventName());
        $resources = $configData['resources'] ?? [];
        foreach ($resources as $resource) {
            if (!$this->authorization->isAllowed($resource)) {
                throw new AuthorizationException(
                    __(
                        "The consumer isn't authorized to access %resources.",
                        ['resources' => $resources]
                    )
                );
            }
        }
    }

}