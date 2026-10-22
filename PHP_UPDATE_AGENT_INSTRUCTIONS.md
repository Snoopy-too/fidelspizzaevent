# Agent Directives: Fidel's Pizza Event - Clean Architecture Consolidation & PHP 8.2.35 Compliance

> **Target Project:** `C:\xampp\htdocs\fidelspizzaevent`  
> **Runtime Environment:** PHP 8.2.35 (ZTS Visual C++ 2019 x64) / Apache 2.4 / MySQL (XAMPP Windows)  
> **Architecture Status:** Advanced Clean Architecture in `src/`; Legacy procedural scripts remain at root  
> **Lead Architect Directive:** Security-First Principal Engineer Governance  

---

## 1. Executive Summary & Current State Audit

PHP has been upgraded to **8.2.35** on this server.

In `fidelspizzaevent`, a high-quality Clean (Hexagonal) Architecture exists in `src/` (featuring `Application/UseCase`, `Domain/Model`, and `Infrastructure/Container`). However, the public web surface still relies on legacy hybrid scripts in the project root:
* Root scripts like [login.php](file:///C:/xampp/htdocs/fidelspizzaevent/login.php), [register.php](file:///C:/xampp/htdocs/fidelspizzaevent/register.php), [menu.php](file:///C:/xampp/htdocs/fidelspizzaevent/menu.php), [edit_order.php](file:///C:/xampp/htdocs/fidelspizzaevent/edit_order.php), and [admin_login.php](file:///C:/xampp/htdocs/fidelspizzaevent/admin_login.php) mix inline HTML, direct `$_POST` mutations, and global helper calls from [helpers.php](file:///C:/xampp/htdocs/fidelspizzaevent/helpers.php).
* Several files lack strict type declarations (`declare(strict_types=1);`).

The mission for incoming agents is to **consolidate all business logic into the existing Hexagonal Architecture**, reduce root files to **ultra-thin HTTP adapters/controllers**, eliminate deprecated procedural code, and enforce strict security boundaries.

---

## 2. Target Architecture & Layer Alignment

Ensure all code strictly follows the existing Clean Architecture boundaries:

```
src/
├── Domain/
│   ├── Model/                # CompedOrder, PickupTimeSlot, PromotionalCampaign, MarketingUser, etc.
│   ├── Repository/           # Interface definitions (CompedOrderRepositoryInterface, etc.)
│   └── Service/              # OrderWindowService, EmailSenderInterface
│
├── Application/
│   ├── DTO/                  # Typed request/response objects (CreateCompedOrderRequest, etc.)
│   └── UseCase/              # Orchestration (CreateCompedOrderUseCase, ManagePickupTimeSlotsUseCase, etc.)
│
└── Infrastructure/
    ├── Container/            # ServiceContainer.php (Dependency Injection)
    ├── Persistence/          # PDO repository implementations with prepared statements
    └── Mail/                 # Email delivery adapters
```

---

## 3. Mandatory Governance Standards

### 3.1 Strict Typing
Every `.php` file in the project (including root entry points, migrations, and template includes) MUST begin with:
```php
<?php
declare(strict_types=1);
```
Type hint all function parameters, return types, and class properties with PHP 8.2 typed features (`readonly`, union types, nullable types).

### 3.2 Elimination of Root Script Business Logic
* Root PHP files (`menu.php`, `login.php`, `edit_order.php`, `admin/index.php`) MUST only:
  1. Retrieve the `ServiceContainer` instance via `getServiceContainer()`.
  2. Instantiate or retrieve the appropriate `Application\UseCase`.
  3. Map HTTP `$_POST` or `$_GET` parameters into a strongly-typed `Application\DTO`.
  4. Invoke the UseCase and capture the typed result.
  5. Render the view or send a redirect with session flash messaging.
* **FORBIDDEN:** Direct `PDO->prepare()` or `PDO->query()` statements in root scripts. All database interactions belong inside `Infrastructure/Repository/`.

### 3.3 DOM Output Sanitization & XSS Prevention
* All customer names, notes, addresses, and email content rendered in HTML templates MUST be strictly escaped:
  ```php
  <?= htmlspecialchars((string)$item->getNotes(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  ```
* For localized multilingual strings (Japanese/English from `languages/`), ensure placeholder interpolation sanitizes dynamic values before insertion.

### 3.4 Session Security & CSRF
* In [bootstrap.php](file:///C:/xampp/htdocs/fidelspizzaevent/src/bootstrap.php) / [config.php](file:///C:/xampp/htdocs/fidelspizzaevent/config.php), enforce secure session parameters:
  ```php
  if (session_status() === PHP_SESSION_NONE) {
      session_start([
          'cookie_httponly' => true,
          'cookie_samesite' => 'Lax',
          'use_strict_mode' => true,
      ]);
  }
  ```
* Every order submission, profile update, and password reset form must validate a CSRF token using constant-time comparison (`hash_equals()`).

---

## 4. Database Governance (MySQL InnoDB)

* Verify that all tables in `database_schema.sql` and the active MySQL database use `ENGINE=InnoDB`:
  * `orders`, `order_items`, `pizzas`, `pickup_time_slots`, `promotional_campaigns`, `password_reset_tokens`.
* Verify Foreign Key constraints:
  * `order_items.order_id` -> `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE`
  * `order_items.pizza_id` -> `FOREIGN KEY (pizza_id) REFERENCES pizzas(id) ON DELETE RESTRICT`
  * `password_reset_tokens.user_id` -> `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
* Transactions are mandatory when persisting orders and updating stock or pickup slot capacities.

---

## 5. Agent Action Items

1. **Audit & Enable Strict Types:** Add `declare(strict_types=1);` to all files in root and `admin/`.
2. **Refactor Order Submission:** Migrate [menu.php](file:///C:/xampp/htdocs/fidelspizzaevent/menu.php) and [edit_order.php](file:///C:/xampp/htdocs/fidelspizzaevent/edit_order.php) POST handlers to use `CreateCompedOrderUseCase` and `CreateOrderUseCase` via `getServiceContainer()`.
3. **Refactor Authentication:** Move procedural login logic in [login.php](file:///C:/xampp/htdocs/fidelspizzaevent/login.php) and [admin_login.php](file:///C:/xampp/htdocs/fidelspizzaevent/admin_login.php) into an `AuthenticateUserUseCase` that returns user domain entities.
4. **Deprecate Procedural Helpers:** Refactor functions in [helpers.php](file:///C:/xampp/htdocs/fidelspizzaevent/helpers.php) into domain services or static pure formatters.
5. **Run Test Suite:** Execute the test suite in `tests/` using `C:\xampp\php\php.exe` to guarantee zero regressions.
