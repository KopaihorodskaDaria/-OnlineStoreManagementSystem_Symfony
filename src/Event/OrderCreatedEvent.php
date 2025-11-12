<?php

namespace App\Event;

use App\Entity\Order;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * OrderCreatedEvent
 *
 * This event is dispatched after a new order has been successfully created.
 * It can be used to trigger additional actions such as sending a confirmation email to the customer.
 */
class OrderCreatedEvent extends Event
{
    public const NAME = 'order.created';

    public function __construct(
        private readonly Order $order
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}

