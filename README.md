# 🛒 Online Store Order Management System

A simple RESTful API for managing online store orders.  
Built with **Symfony 6/7**, **Doctrine ORM**, **Symfony Messenger**, and **PostgreSQL**.

---

## ⚙️ Tech Stack
- Symfony 6/7  
- Doctrine ORM  
- Symfony Messenger  
- PostgreSQL  

---

## 🚀 Main Features
- Full **REST API** for managing orders  
- Pagination, filtering, and search  
- CRUD operations (Create, Read, Update, Delete)  
- Order status update via `PATCH`  
- Event-driven architecture (Symfony Events)  
- Worker for asynchronous email notifications  

---

## 📦 API Endpoints
| Method | Endpoint | Description |
|:--|:--|:--|
| **GET** | `/api/orders` | Get all orders (pagination, filtering, search) |
| **GET** | `/api/orders/{id}` | Get order details |
| **POST** | `/api/orders` | Create a new order |
| **PUT** | `/api/orders/{id}` | Update an existing order |
| **DELETE** | `/api/orders/{id}` | Delete an order |
| **PATCH** | `/api/orders/{id}/status` | Change order status |

---


## ✉️ Worker (EmailNotificationHandler)
- Sends welcome email on order creation  
- Sends delivery update when status = “shipped”  
- Sends thank-you email when status = “delivered”  
- Logs all sent emails  

---


## Launching the Messenger worker (in a separate terminal)
```bash
php bin/console messenger:consume async -vv
```

---

## Running the tests

### Make sure the database for the tests is created:
```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
```

### Running all Codeception tests

```bash
vendor/bin/codecept run
```

