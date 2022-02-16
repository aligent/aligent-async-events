<?php

namespace Aligent\AsyncEvents\Api\Data;

interface AsyncEventDisplayInterface
{
    /**
     * @return int
     */
    public function getSubscriptionId(): int;

    /**
     * @return string
     */
    public function getEventName(): string;

    /**
     * @return string
     */
    public function getRecipientUrl(): string;

    /**
     * @return bool
     */
    public function getStatus(): bool;

    /**
     * @return string
     */
    public function getSubscribedAt(): string;
}
