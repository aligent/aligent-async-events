<?php

declare(strict_types=1);

namespace Aligent\AsyncEvents\Controller\Adminhtml\Events;

use Aligent\AsyncEvents\Api\AsyncEventRepositoryInterface;
use Aligent\AsyncEvents\Model\AsyncEvent;
use Aligent\AsyncEvents\Model\ResourceModel\AsyncEvent\Collection;
use Magento\Ui\Component\MassAction\Filter;
use Aligent\AsyncEvents\Model\ResourceModel\AsyncEvent\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class MassDisable extends Action implements HttpPostActionInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var AsyncEventRepositoryInterface
     */
    private $asyncEventRepository;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        AsyncEventRepositoryInterface $asyncEventRepository
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->filter = $filter;
        $this->asyncEventRepository = $asyncEventRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('async_events/events/index');

        $asyncEventCollection = $this->collectionFactory->create();
        $this->filter->getCollection($asyncEventCollection);
        $this->disableAsyncEvents($asyncEventCollection);

        return $resultRedirect;
    }

    private function disableAsyncEvents(Collection $asyncEventCollection)
    {
        $disabled = 0;
        $alreadyDisabled = 0;

        /** @var AsyncEvent $asyncEvent */
        foreach ($asyncEventCollection as $asyncEvent) {
            $alreadyDisabled++;
            if ($asyncEvent->getStatus()) {
                try {
                    $asyncEvent->setStatus(false);
                    $this->asyncEventRepository->save($asyncEvent, false);
                    $alreadyDisabled--;
                    $disabled++;
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            }
        }

        if ($disabled) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 event(s) have been disabled.', $disabled)
            );
        }

        if ($alreadyDisabled) {
            $this->messageManager->addNoticeMessage(
                __('A total of %1 event(s) are already disabled.', $alreadyDisabled)
            );
        }
    }
}
