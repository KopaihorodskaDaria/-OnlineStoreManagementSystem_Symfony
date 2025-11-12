<?php

namespace App\EventSubscriber;

use App\Event\OrderCreatedEvent;
use App\Event\OrderStatusChangedEvent;
use App\Message\EmailNotificationMessage;
use App\Entity\OrderStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Event Subscriber for handling order-related events.
 *
 * Listens to Order events and sends messages to the Messenger queue
 * for asynchronous email processing.
 *
 * Implements an event-driven architecture for sending email notifications.
 */
class OrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Defines which events this subscriber listens to.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            OrderCreatedEvent::NAME => 'onOrderCreated',
            OrderStatusChangedEvent::NAME => 'onOrderStatusChanged',
        ];
    }

    /**
     * Handles the order creation event.
     * Sends a welcome email to the customer after a successful order creation.
     *
     * @param OrderCreatedEvent $event
     * @return void
     */
    public function onOrderCreated(OrderCreatedEvent $event): void
    {
        $order = $event->getOrder();

        // Create a message for sending the welcome email
        $message = new EmailNotificationMessage(
            to: $order->getCustomerEmail(),
            type: EmailNotificationMessage::TYPE_WELCOME,
            data: [
                'order_id' => $order->getId(),
                'customer_name' => $order->getCustomerName(),
                'total_amount' => $order->getTotalAmount(),
            ]
        );

        // Dispatch the message to the asynchronous queue
        try {
            $this->messageBus->dispatch($message);
        } catch (\Exception $e) {
            // Log the error but do not throw it further to avoid interrupting order creation
            if ($this->logger) {
                $this->logger->error('Failed to dispatch email notification message', [
                    'error' => $e->getMessage(),
                    'order_id' => $order->getId(),
                ]);
            }
        }
    }

    /**
     * Handles the order status change event.
     * Sends an email notification depending on the new status.
     *
     * @param OrderStatusChangedEvent $event
     * @return void
     */
    public function onOrderStatusChanged(OrderStatusChangedEvent $event): void
    {
        $order = $event->getOrder();
        $newStatus = $event->getNewStatus();

        // Determine the email type based on the new order status
        $emailType = match ($newStatus) {
            OrderStatus::SHIPPED => EmailNotificationMessage::TYPE_SHIPPED,
            OrderStatus::DELIVERED => EmailNotificationMessage::TYPE_DELIVERED,
            default => null, // No email is sent for other statuses
        };

        // If the email type is defined, send the message
        if ($emailType !== null) {
            $message = new EmailNotificationMessage(
                to: $order->getCustomerEmail(),
                type: $emailType,
                data: [
                    'order_id' => $order->getId(),
                    'customer_name' => $order->getCustomerName(),
                    'total_amount' => $order->getTotalAmount(),
                ]
            );

            // Відправка повідомлення в асинхронну чергу
            // Dispatch the message to the asynchronous queue
            try {
                $this->messageBus->dispatch($message);
            } catch (\Exception $e) {

                if ($this->logger) {
                    $this->logger->error('Failed to dispatch email notification message', [
                        'error' => $e->getMessage(),
                        'order_id' => $order->getId(),
                    ]);
                }
            }
        }
    }
}


