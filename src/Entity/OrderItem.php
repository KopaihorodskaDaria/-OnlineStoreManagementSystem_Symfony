<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Table(name: 'order_items')]
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]

class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Many-to-one: many items belong to one order
     */
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;


    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Product name is required')]
    #[Assert\Length(max: 255)]
    private ?string $productName = null;


    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Quantity must be > 0')]
    private ?int $quantity = null;


    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Price must be > 0.00')]
    private ?string $price = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): ?float
    {
        return (float) $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = number_format($price, 2, '.', '');
        return $this;
    }


    public function getTotalPrice(): string
    {
        if ($this->price === null || $this->quantity === null) {
            return '0.00';
        }
        return bcmul($this->price, (string)$this->quantity, 2);
    }
}
