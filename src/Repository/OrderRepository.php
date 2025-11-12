<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository responsible for managing Order entities.
 * Provides methods for filtering, searching, and paginating orders.
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // Call the parent constructor with the Order entity class
        parent::__construct($registry, Order::class);
    }

    /**
     * Finds orders with pagination, filtering by status, date range and customer email search.
     *
     * @param int $page Page number (starting from 1)
     * @param int $limit Number of records per page
     * @param OrderStatus|null $status Optional filter by order status
     * @param \DateTimeImmutable|null $dateFrom Optional start date filter (inclusive)
     * @param \DateTimeImmutable|null $dateTo Optional end date filter (inclusive)
     * @param string|null $email Optional search by customer email (partial match)
     *
     * @return array{orders: array, total: int, page: int, limit: int, totalPages: int} Paginated results with metadata
     */
    public function findWithPagination(
        int                 $page = 1,
        int                 $limit = 10,
        ?OrderStatus        $status = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
        ?string             $email = null
    ): array
    {
        // Create the base query builder selecting orders and eager loading related items to avoid N+1 problem
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')   // Join related order items
            ->addSelect('i')             // Include items in select to fetch them eagerly
            ->orderBy('o.createdAt', 'DESC'); // Order results by creation date descending

        // Apply filter by order status if provided
        if ($status !== null) {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        // Apply filter to include orders created from this date onwards (inclusive)
        if ($dateFrom !== null) {
            $qb->andWhere('o.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        // Apply filter to include orders created up to the end of the specified date (inclusive)
        if ($dateTo !== null) {
            // Adjust dateTo by adding one day to include entire dateTo day
            $dateToEnd = $dateTo->modify('+1 day');
            $qb->andWhere('o.createdAt < :dateTo')
                ->setParameter('dateTo', $dateToEnd);
        }

        // Apply partial search filter on customer email if provided
        if ($email !== null && $email !== '') {
            $qb->andWhere('o.customerEmail LIKE :email')
                ->setParameter('email', '%' . $email . '%');
        }

        // Build a separate query builder for counting total records to avoid issues with joins and grouping
        $totalQb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)');

        // Apply the same filters to the count query as applied to the main query

        if ($status !== null) {
            $totalQb->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        if ($dateFrom !== null) {
            $totalQb->andWhere('o.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $dateToEnd = $dateTo->modify('+1 day');
            $totalQb->andWhere('o.createdAt < :dateTo')
                ->setParameter('dateTo', $dateToEnd);
        }

        if ($email !== null && $email !== '') {
            $totalQb->andWhere('o.customerEmail LIKE :email')
                ->setParameter('email', '%' . $email . '%');
        }

        // Execute count query and get total number of matching orders
        $total = (int)$totalQb->getQuery()->getSingleScalarResult();

        // Calculate offset for pagination based on current page and limit
        $offset = ($page - 1) * $limit;

        // Apply pagination limits to main query
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        // Execute the main query to get paginated orders
        $orders = $qb->getQuery()->getResult();

        // Calculate total number of pages for pagination
        $totalPages = (int)ceil($total / $limit);

        // Return array with orders and pagination metadata
        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Finds a single order by ID with eager loading of its items.
     * This optimizes queries by fetching related items in one query.
     *
     * @param int $id Order ID
     * @return Order|null The order with its items, or null if not found
     */
    public function findWithItems(int $id): ?Order
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')   // Join related items to avoid lazy loading
            ->addSelect('i')             // Include items in select
            ->where('o.id = :id')        // Filter by order ID
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();      // Return single order or null if not found
    }
}

//    /**
//     * @return Order[] Returns an array of Order objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Order
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

