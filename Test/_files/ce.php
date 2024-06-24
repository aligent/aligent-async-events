<?php

/**
 * Swap out Config for TestConfig during queue consumer run so `example.event` is available in Integration tests.
 */

return [
    \Aligent\AsyncEvents\Model\Config::class => \Aligent\AsyncEvents\Test\Integration\TestConfig::class
];