<?php

namespace App\Controller;

use App\DTO\CreateOrderRequest;
use App\DTO\OrderItemDTO;
use App\DTO\ViewOrder;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatus;
use App\Event\OrderCreatedEvent;
use App\Event\OrderStatusChangedEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API Controller to manage orders
 * Supports all CRUD operations and changing order status
 */
#[Route('/api/orders', name: 'api_orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * GET /api/orders
     * Fetches a list of orders with pagination, filtering and search
     *
     * Query parameters:
     * - page: page number (default 1)
     * - limit: number of records per page (default 10)
     * - status: filter by order status (pending, processing, shipped, delivered, cancelled)
     * - date_from: filter orders from this date (format: Y-m-d)
     * - date_to: filter orders up to this date (format: Y-m-d)
     * - email: search by customer email (partial match)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Get query params with defaults
        $page = (int) ($request->query->get('page', 1));
        $limit = (int) ($request->query->get('limit', 10));
        $status = $request->query->get('status');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $email = $request->query->get('email');

        // Validate pagination params to avoid nonsense values
        if ($page < 1) {
            return $this->json(['error' => 'Page must be greater than 0'], Response::HTTP_BAD_REQUEST);
        }
        if ($limit < 1 || $limit > 100) {
            return $this->json(['error' => 'Limit must be between 1 and 100'], Response::HTTP_BAD_REQUEST);
        }

        // Parse status filter if provided and check if it is valid
        $orderStatus = null;
        if ($status !== null) {
            if (!OrderStatus::isValid($status)) {
                return $this->json(
                    ['error' => 'Invalid status. Allowed values: ' . implode(', ', OrderStatus::values())],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $orderStatus = OrderStatus::from($status);
        }

        // Parse date_from filter if provided
        $dateFromObj = null;
        if ($dateFrom !== null) {
            try {
                $dateFromObj = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
                if ($dateFromObj === false) {
                    return $this->json(['error' => 'Invalid date_from format. Use Y-m-d'], Response::HTTP_BAD_REQUEST);
                }
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date_from format. Use Y-m-d'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Parse date_to filter if provided
        $dateToObj = null;
        if ($dateTo !== null) {
            try {
                $dateToObj = \DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);
                if ($dateToObj === false) {
                    return $this->json(['error' => 'Invalid date_to format. Use Y-m-d'], Response::HTTP_BAD_REQUEST);
                }
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date_to format. Use Y-m-d'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Use repository method to get orders with pagination and filters
        $result = $this->orderRepository->findWithPagination(
            page: $page,
            limit: $limit,
            status: $orderStatus,
            dateFrom: $dateFromObj,
            dateTo: $dateToObj,
            email: $email
        );

        // Convert each order entity to a DTO for response
        $orders = array_map(
            fn(Order $order) => ViewOrder::fromEntity($order)->toArray(),
            $result['orders']
        );

        // Return data with pagination info
        return $this->json([
            'data' => $orders,
            'pagination' => [
                'page' => $result['page'],
                'limit' => $result['limit'],
                'total' => $result['total'],
                'total_pages' => $result['totalPages'],
            ],
        ]);
    }

    /**
     * GET /api/orders/{id}
     * Gets details for a specific order by ID
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->findWithItems($id);

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Return order details as DTO
        return $this->json([
            'data' => ViewOrder::fromEntity($order)->toArray(),
        ]);
    }

    /**
     * POST /api/orders
     * Creates a new order
     * Validates input and dispatches event after creation
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Decode JSON from request body
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Map data to DTO for validation
        $createRequest = CreateOrderRequest::fromArray($data);

        // Validate DTO with Symfony Validator
        $errors = $this->validator->validate($createRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Validate each order item separately
        $itemErrors = [];
        $orderItems = [];
        foreach ($createRequest->items as $index => $itemData) {
            $itemDto = OrderItemDTO::fromArray($itemData);
            $validation = $this->validator->validate($itemDto);

            if (count($validation) > 0) {
                foreach ($validation as $error) {
                    $itemErrors["items[$index].{$error->getPropertyPath()}"] = $error->getMessage();
                }
            }

            // Extra check for price > 0
            $price = is_numeric($itemDto->price) ? (float)$itemDto->price : 0;
            if ($price <= 0) {
                $itemErrors["items[$index].price"] = 'Price must be greater than 0';
            }

            // Create OrderItem entity from DTO
            $orderItem = new OrderItem();
            $orderItem->setProductName($itemDto->productName);
            $orderItem->setQuantity($itemDto->quantity);
            $orderItem->setPrice((float)$itemDto->price);
            $orderItems[] = $orderItem;
        }

        // If there are any item validation errors, return them
        if ($itemErrors) {
            return $this->json(['errors' => $itemErrors], Response::HTTP_BAD_REQUEST);
        }

        // Create new Order entity and set its data
        $order = new Order();
        $order->setCustomerName($createRequest->customerName);
        $order->setCustomerEmail($createRequest->customerEmail);
        $order->setStatus(OrderStatus::PENDING);

        // Add all items to order
        foreach ($orderItems as $orderItem) {
            $order->addItem($orderItem);
        }

        // Calculate total amount based on items
        $order->calculateTotalAmount();

        // Save order to DB
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Dispatch event that order was created (for example, to send emails)
        $this->eventDispatcher->dispatch(new OrderCreatedEvent($order), OrderCreatedEvent::NAME);

        // Return created order with 201 status
        return $this->json(['data' => ViewOrder::fromEntity($order)->toArray()], Response::HTTP_CREATED);
    }


    /**
     * PUT /api/orders/{id}
     * Updates an existing order fully
     * Replaces all fields with new data from request
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $order = $this->orderRepository->findWithItems($id);

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Decode JSON from request body
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate and update customer name if provided
        if (isset($data['customer_name'])) {
            if (!is_string($data['customer_name']) || strlen($data['customer_name']) < 2 || strlen($data['customer_name']) > 255) {
                return $this->json(['error' => 'Customer name must be a string between 2 and 255 characters'], Response::HTTP_BAD_REQUEST);
            }
            $order->setCustomerName($data['customer_name']);
        }

        // Validate and update customer email if provided
        if (isset($data['customer_email'])) {
            if (!filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Invalid email format'], Response::HTTP_BAD_REQUEST);
            }
            $order->setCustomerEmail($data['customer_email']);
        }

        // If items are passed, validate and replace them completely
        if (isset($data['items'])) {
            if (!is_array($data['items']) || count($data['items']) === 0) {
                return $this->json(['error' => 'Items must be a non-empty array'], Response::HTTP_BAD_REQUEST);
            }

            // Remove old items from order and DB
            foreach ($order->getItems() as $item) {
                $order->removeItem($item);
                $this->entityManager->remove($item);
            }

            // Add new items after validation
            foreach ($data['items'] as $index => $itemData) {
                if (
                    !isset($itemData['product_name'], $itemData['quantity'], $itemData['price']) ||
                    !is_string($itemData['product_name']) || strlen($itemData['product_name']) < 1 ||
                    !is_int($itemData['quantity']) || $itemData['quantity'] < 1 ||
                    !is_numeric($itemData['price']) || $itemData['price'] <= 0
                ) {
                    return $this->json([
                        'error' => "Invalid item at index {$index}. Each item must have product_name (string), quantity (int > 0), price (number > 0)."
                    ], Response::HTTP_BAD_REQUEST);
                }

                $orderItem = new OrderItem();
                $orderItem->setProductName($itemData['product_name']);
                $orderItem->setQuantity($itemData['quantity']);
                $orderItem->setPrice((float)$itemData['price']);
                $order->addItem($orderItem);
            }
        }

        // Recalculate total amount after update
        $order->calculateTotalAmount();

        $this->entityManager->flush();

        // Return updated order
        return $this->json([
            'data' => ViewOrder::fromEntity($order)->toArray(),
        ]);
    }


    /**
     * DELETE /api/orders/{id}
     * Deletes an order by ID
     * Order items are deleted automatically via cascade
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Remove order (items deleted cascade)
        $this->entityManager->remove($order);
        $this->entityManager->flush();

        // Return no content with success message
        return $this->json(['message' => 'Order deleted successfully'], Response::HTTP_NO_CONTENT);
    }

    /**
     * PATCH /api/orders/{id}/status
     * Changes the status of an order
     * Dispatches event to handle things like sending email notifications
     */
    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Decode JSON from request body
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check if status field is provided
        if (!isset($data['status'])) {
            return $this->json(['error' => 'Status field is required'], Response::HTTP_BAD_REQUEST);
        }

        // Validate new status
        if (!OrderStatus::isValid($data['status'])) {
            return $this->json(
                ['error' => 'Invalid status. Allowed values: ' . implode(', ', OrderStatus::values())],
                Response::HTTP_BAD_REQUEST
            );
        }

        $newStatus = OrderStatus::from($data['status']);
        $oldStatus = $order->getStatus();

        // If status didn't change, just return current order data
        if ($oldStatus === $newStatus) {
            return $this->json([
                'data' => ViewOrder::fromEntity($order)->toArray(),
            ]);
        }

        // Update status
        $order->setStatus($newStatus);
        $this->entityManager->flush();

        // Dispatch event about status change (for example to send notification email)
        $this->eventDispatcher->dispatch(
            new OrderStatusChangedEvent($order, $oldStatus, $newStatus),
            OrderStatusChangedEvent::NAME
        );

        // Return updated order
        return $this->json([
            'data' => ViewOrder::fromEntity($order)->toArray(),
        ]);
    }
}

