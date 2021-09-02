<?php

namespace Aligent\Webhooks\Model;

use Aligent\Webhooks\Model\Config as WebhookConfig;
use Aligent\Webhooks\Service\Webhook\EventDispatcher;
use Exception;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Psr\Log\LoggerInterface;

class WebhookTriggerHandler
{
    /**
     * @var EventDispatcher
     */
    private EventDispatcher $dispatcher;

    /**
     * @var Json
     */
    private Json $json;

    /**
     * @var ServiceOutputProcessor
     */
    private ServiceOutputProcessor $outputProcessor;

    /**
     * @var Config
     */
    private WebhookConfig $webhookConfig;

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var ServiceInputProcessor
     */
    private ServiceInputProcessor $inputProcessor;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param EventDispatcher $dispatcher
     * @param ServiceOutputProcessor $outputProcessor
     * @param ObjectManagerInterface $objectManager
     * @param Config $webhookConfig
     * @param ServiceInputProcessor $inputProcessor
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        EventDispatcher $dispatcher,
        ServiceOutputProcessor $outputProcessor,
        ObjectManagerInterface $objectManager,
        WebhookConfig $webhookConfig,
        ServiceInputProcessor $inputProcessor,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->dispatcher = $dispatcher;
        $this->json = $json;
        $this->outputProcessor = $outputProcessor;
        $this->webhookConfig = $webhookConfig;
        $this->objectManager = $objectManager;
        $this->inputProcessor = $inputProcessor;
        $this->logger = $logger;
    }

    /**
     * @param array $queueMessage
     */
    public function process(array $queueMessage)
    {
        try {
            $eventName = $queueMessage[0];
            $output = $this->json->unserialize($queueMessage[1]);

            $configData = $this->webhookConfig->get($eventName);
            $serviceClassName = $configData['class'];
            $serviceMethodName = $configData['method'];
            $service = $this->objectManager->create($serviceClassName);
            $inputParams = $this->inputProcessor->process($serviceClassName, $serviceMethodName, $output);

            $outputData = call_user_func_array([$service, $serviceMethodName], $inputParams);

            $outputData = $this->outputProcessor->process(
                $outputData,
                $serviceClassName,
                $serviceMethodName
            );

            $this->dispatcher->dispatch($eventName, $outputData);
        } catch (Exception $exception) {
            $this->logger->critical(
                __('Error when processing %hook webhook', [
                    'hook' => $eventName
                ]),
                [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString()
                ]
            );
        }
    }
}
