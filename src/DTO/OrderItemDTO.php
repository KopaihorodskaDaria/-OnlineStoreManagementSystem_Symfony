<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for an order item
 * Used for validating individual order items
 */
class OrderItemDTO
{
    #[Assert\NotBlank(message: 'Product name is required')]
    #[Assert\Length(max: 255)]
    public string $productName;


    #[Assert\NotBlank(message: 'Quantity is required')]
    #[Assert\Type(type: 'integer', message: 'Quantity must be an integer')]
    #[Assert\Positive(message: 'Quantity must be greater than 0')]
    public int $quantity;


    #[Assert\NotBlank(message: 'Price is required')]
    #[Assert\Type(type: ['string', 'float', 'integer'], message: 'Price must be a number')]
    public string|float $price;


    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->productName = $data['product_name'] ?? '';
        $dto->quantity = (int) ($data['quantity'] ?? 0);
        $dto->price = $data['price'] ?? '0.00';

        return $dto;
    }
}

