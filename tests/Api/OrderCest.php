<?php

namespace Api;

use App\Tests\Support\ApiTester;

/**
 * Tests for Order API
 * Checks creating an order and retrieving order details
 */
class OrderCest
{
    /**
     * Test order creation
     * Verifies that an order is created with valid data
     */
    public function testCreateOrder(ApiTester $I): void
    {
        $orderData = [
            'customer_name' => 'John Doux',
            'customer_email' => 'john.doe@example.com',
            'items' => [
                [
                    'product_name' => 'Laptop',
                    'quantity' => 1,
                    'price' => 25000.00
                ],
                [
                    'product_name' => 'Mouse',
                    'quantity' => 2,
                    'price' => 500.00
                ]
            ]
        ];

        $I->sendPOST('/api/orders', json_encode($orderData));
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);

        // Check response structure
        $I->assertArrayHasKey('data', $response);
        $I->assertArrayHasKey('id', $response['data']);
        $I->assertArrayHasKey('customer_name', $response['data']);
        $I->assertArrayHasKey('customer_email', $response['data']);
        $I->assertArrayHasKey('total_amount', $response['data']);
        $I->assertArrayHasKey('status', $response['data']);
        $I->assertArrayHasKey('items', $response['data']);

        // Check order data
        $I->assertEquals('John Doe', $response['data']['customer_name']);
        $I->assertEquals('john.doe@example.com', $response['data']['customer_email']);
        $I->assertEquals('pending', $response['data']['status']);
        $I->assertEquals('26000.00', $response['data']['total_amount']); // 25000 + 2*500

        // Check order items
        $I->assertCount(2, $response['data']['items']);
        $I->assertEquals('Laptop', $response['data']['items'][0]['product_name']);
        $I->assertEquals(1, $response['data']['items'][0]['quantity']);
        $I->assertEquals(25000.00, $response['data']['items'][0]['price']);
    }

    /**
     * Test retrieving order details
     * Verifies that we can retrieve the details of a created order
     */
    public function testGetOrderDetails(ApiTester $I): void
    {
        // First create an order
        $orderData = [
            'customer_name' => 'Mary Johnson',
            'customer_email' => 'mary.johnson@example.com',
            'items' => [
                [
                    'product_name' => 'Smartphone',
                    'quantity' => 1,
                    'price' => 15000.00
                ],
                [
                    'product_name' => 'Case',
                    'quantity' => 1,
                    'price' => 300.00
                ]
            ]
        ];

        $I->sendPOST('/api/orders', json_encode($orderData));
        $I->seeResponseCodeIs(201);

        $createResponse = json_decode($I->grabResponse(), true);
        $orderId = $createResponse['data']['id'];

        // Get order details
        $I->sendGET("/api/orders/{$orderId}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);

        // Check response structure
        $I->assertArrayHasKey('data', $response);
        $I->assertEquals($orderId, $response['data']['id']);
        $I->assertEquals('Mary Johnson', $response['data']['customer_name']);
        $I->assertEquals('mary.johnson@example.com', $response['data']['customer_email']);
        $I->assertEquals('15300.00', $response['data']['total_amount']); // 15000 + 300
        $I->assertEquals('pending', $response['data']['status']);

        // Check order items
        $I->assertCount(2, $response['data']['items']);
        $I->assertArrayHasKey('id', $response['data']['items'][0]);
        $I->assertArrayHasKey('product_name', $response['data']['items'][0]);
        $I->assertArrayHasKey('quantity', $response['data']['items'][0]);
        $I->assertArrayHasKey('price', $response['data']['items'][0]);
        $I->assertArrayHasKey('total_price', $response['data']['items'][0]);
    }
}
