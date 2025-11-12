<?php

namespace App\Message;

/**
 * Message used for sending emails via the Messenger component.
 * Enables asynchronous email notifications.
 * Processed by the EmailNotificationHandler.
 */
class EmailNotificationMessage
{
    /**
     * Тип email повідомлення
     * Визначає, який шаблон та текст використовувати
     */
    public const TYPE_WELCOME = 'welcome';           // В// Welcome email after order creation
    public const TYPE_SHIPPED = 'shipped';           // Notification about shipment
    public const TYPE_DELIVERED = 'delivered';       // Thank-you email after delivery

    public function __construct(
        private readonly string $to,
        private readonly string $type,
        private readonly array $data = []
    ) {
    }

    // Returns the recipient’s email address.
    public function getTo(): string
    {
        return $this->to;
    }

    // Returns the email message type.
    public function getType(): string
    {
        return $this->type;
    }


     // Отримує дані для шаблону email

    public function getData(): array
    {
        return $this->data;
    }
}
