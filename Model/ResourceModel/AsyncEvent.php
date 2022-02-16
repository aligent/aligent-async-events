<?php

declare(strict_types=1);

namespace Aligent\AsyncEvents\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AsyncEvent extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('async_event_subscriber', 'subscription_id');
    }
}
