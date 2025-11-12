<?php


namespace App\DTO;

use App\Entity\Order;
use App\Entity\OrderItem;


class ViewOrder
{
    public int $id;
    public string $customerName;
    public string $customerEmail;
    public string $totalAmount;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    /**
     * Array of order items represented as associative arrays
     * Each element is an array with the following keys: id, product_name, quantity, price, total_price
     */

    public array $items = [];

    /**
     * Creates a DTO from the Order entity
     * Converts a Doctrine entity into a DTO for API response
     */
    public static function fromEntity(Order $order): self
    {
        $dto = new self();
        $dto->id = $order->getId();
        $dto->customerName = $order->getCustomerName();
        $dto->customerEmail = $order->getCustomerEmail();
        $dto->totalAmount = $order->getTotalAmount();
        $dto->status = $order->getStatus()->value;
        $dto->createdAt = $order->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->updatedAt = $order->getUpdatedAt()->format('Y-m-d H:i:s');

        // Convert related OrderItem entities into structured array
        foreach ($order->getItems() as $item) {
            /** @var OrderItem $item */
            $dto->items[] = [
                'id' => $item->getId(),
                'product_name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
                'total_price' => $item->getTotalPrice(),
            ];
        }

        return $dto;
    }


    /**
     * Converts the DTO into a simple associative array
     * Used when returning data as JSON in API responses
     *
     * @return array<string, mixed>
     */

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'total_amount' => $this->totalAmount,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'items' => $this->items,
        ];
    }
}
