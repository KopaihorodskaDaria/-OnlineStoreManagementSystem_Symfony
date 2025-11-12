<?php

namespace App\Event;

use App\Entity\Order;
use App\Entity\OrderStatus;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * OrderStatusChangedEvent
 *
 * This event is dispatched when an order's status changes.
 * It can be used to trigger actions such as sending notification emails
 * to inform customers about the status update.
 */
class OrderStatusChangedEvent extends Event
{
    public const NAME = 'order.status_changed';

    public function __construct(
        private readonly Order $order,
        private readonly OrderStatus $oldStatus,
        private readonly OrderStatus $newStatus
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getOldStatus(): OrderStatus
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): OrderStatus
    {
        return $this->newStatus;
    }
}


