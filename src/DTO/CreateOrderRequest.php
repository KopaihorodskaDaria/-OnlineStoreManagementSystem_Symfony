<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating an order
 * Used for validation and data transfer when creating an order via API
 */
class CreateOrderRequest
{
    /**
     * Customer name
     * Required field, minimum 2 characters
     */
    #[Assert\NotBlank(message: 'Customer name is required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Customer name must be at least 2 characters')]
    public string $customerName;

    /**
     * Customer email
     * Required field, must be a valid email
     */
    #[Assert\NotBlank(message: 'Customer email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    public string $customerEmail;

    /**
     * Order items
     * Must contain at least one item
     * Each item is validated separately via OrderItemDTO
     */
    #[Assert\NotNull(message: 'Items are required')]
    #[Assert\Count(min: 1, minMessage: 'Order must have at least one item')]
    #[Assert\Valid]
    public array $items = [];

    /**
     * Creates DTO from an array of data
     * Used for deserializing JSON requests
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->customerName = $data['customer_name'] ?? '';
        $dto->customerEmail = $data['customer_email'] ?? '';
        $dto->items = $data['items'] ?? [];

        return $dto;
    }
}



