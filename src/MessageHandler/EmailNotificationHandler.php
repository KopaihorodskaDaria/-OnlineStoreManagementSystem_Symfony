<?php

namespace App\MessageHandler;

use App\Message\EmailNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;


  // Sends email messages asynchronously via the message queue.
  // Logs all sent emails for tracking and debugging purposes.

#[AsMessageHandler]
class EmailNotificationHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Handles incoming email notification messages.
     * Automatically invoked by Messenger when an EmailNotificationMessage is received from the queue.
     */
    public function __invoke(EmailNotificationMessage $message): void
    {
        try {
            // Create the email message
            $email = (new Email())
                ->from('no-reply@example.com')
                ->to($message->getTo())
                ->subject($this->getSubject($message->getType()))
                ->text($this->getTextContent($message->getType(), $message->getData()));

            // Send the email
            $this->mailer->send($email);

            // Log successful delivery
            // Logs are written to var/log/email.log via Monolog configuration
            $logData = [
                'to' => $message->getTo(),
                'type' => $message->getType(),
                'subject' => $this->getSubject($message->getType()),
                'order_id' => $message->getData()['order_id'] ?? null,
                'customer_name' => $message->getData()['customer_name'] ?? null,
            ];
            $this->logger->info('Email notification sent successfully', $logData);

            // Also output to console for the worker
            if (php_sapi_name() === 'cli') {
                echo sprintf(
                    "[%s] Email sent: %s to %s (Order #%s)\n",
                    date('Y-m-d H:i:s'),
                    $message->getType(),
                    $message->getTo(),
                    $logData['order_id'] ?? 'N/A'
                );
            }
        } catch (\Exception $e) {
            // Log the sending error
            $this->logger->error('Failed to send email notification', [
                'to' => $message->getTo(),
                'type' => $message->getType(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow the exception so Messenger can retry
            throw $e;
        }
    }

    /**
     * Returns the email subject based on the message type.
     *
     * @param string $type
     * @return string
     */
    private function getSubject(string $type): string
    {
        return match ($type) {
            EmailNotificationMessage::TYPE_WELCOME => 'Thank you for your order!',
            EmailNotificationMessage::TYPE_SHIPPED => 'Your order has been shipped',
            EmailNotificationMessage::TYPE_DELIVERED => 'Your order has been delivered',
            default => 'Order Notification',
        };
    }


     // Returns the plain text content of the email based on the message type.
    private function getTextContent(string $type, array $data): string
    {
        $orderId = $data['order_id'] ?? 'N/A';
        $customerName = $data['customer_name'] ?? 'Dear Customer';
        $totalAmount = $data['total_amount'] ?? '0.00';

        return match ($type) {
            EmailNotificationMessage::TYPE_WELCOME => sprintf(
                "Hello, %s!\n\n" .
                "Thank you for your order #%s.\n" .
                "Total amount: %s USD.\n\n" .
                "We will process your order shortly.\n\n" .
                "Best regards,\nThe Online Store Team",
                $customerName,
                $orderId,
                $totalAmount
            ),
            EmailNotificationMessage::TYPE_SHIPPED => sprintf(
                "Hello, %s!\n\n" .
                "Your order #%s has been shipped.\n" .
                "You will receive it soon.\n\n" .
                "Best regards,\nThe Online Store Team",
                $customerName,
                $orderId
            ),
            EmailNotificationMessage::TYPE_DELIVERED => sprintf(
                "Hello, %s!\n\n" .
                "Your order #%s has been successfully delivered!\n\n" .
                "Thank you for choosing our store. We look forward to serving you again!\n\n" .
                "Best regards,\nThe Online Store Team",
                $customerName,
                $orderId
            ),
            default => 'Order Notification',
        };
    }
}



