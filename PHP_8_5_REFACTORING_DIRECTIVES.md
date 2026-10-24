# Agent Directives: Fidel's Pizza Event - PHP 8.5.12 Modernization & Feature Adoption

> **Target Project:** `C:\xampp\htdocs\fidelspizzaevent`  
> **Runtime Environment:** PHP 8.5.12 (ZTS Visual C++ 2022 x64) / Apache 2.4.58 (Win64) / MySQL (XAMPP Windows)  
> **Architecture Status:** Advanced Clean Architecture in `src/`; Root entry points requiring consolidation  
> **Lead Architect Directive:** Security-First Principal Engineer Governance  

---

## 1. Executive Summary & Runtime Baseline

The server's PHP runtime has been upgraded to **PHP 8.5.12** (VS17 x64 Thread Safe with OPcache).

`fidelspizzaevent` possesses a solid Clean Architecture foundation in `src/` (`Domain/Model`, `Application/UseCase`, `Infrastructure/Persistence`). However, the existing classes and root scripts must be updated to comply with PHP 8.5's modern syntax, strict deprecation guards, and enhanced standard library features.

---

## 2. Key PHP 8.5 Architectural Directives

### 2.1 Domain Entities with Asymmetric Visibility
Upgrade existing domain entities in `src/Domain/Model/` (e.g., [`CompedOrder.php`](file:///C:/xampp/htdocs/fidelspizzaevent/src/Domain/Model/CompedOrder.php), [`PickupTimeSlot.php`](file:///C:/xampp/htdocs/fidelspizzaevent/src/Domain/Model/PickupTimeSlot.php), [`PromotionalCampaign.php`](file:///C:/xampp/htdocs/fidelspizzaevent/src/Domain/Model/PromotionalCampaign.php)):

* Replace verbose private properties with `public private(set)` declarations:
  ```php
  <?php
  declare(strict_types=1);

  namespace FidelsPizza\Domain\Model;

  use DateTimeImmutable;

  final class PickupTimeSlot
  {
      public function __construct(
          public private(set) int $id,
          public private(set) DateTimeImmutable $slotTime,
          public private(set) int $capacity,
          public private(set) int $currentBookings = 0
      ) {}

      public bool $isAvailable {
          get => $this->currentBookings < $this->capacity;
      }

      public function incrementBookings(): void
      {
          if (!$this->isAvailable) {
              throw new \DomainException("Slot capacity of {$this->capacity} reached.");
          }
          $this->currentBookings++;
      }
  }
  ```

### 2.2 Fix Implicitly Nullable Parameter Deprecations (Hard Requirement)
PHP 8.5 emits an `E_DEPRECATED` warning on any parameter formatted with a default `null` without an explicit nullable type hint.
* **FORBIDDEN (PHP 8.5 deprecation):**
  ```php
  public function findByDate(string $date, string $status = null): array
  ```
* **MANDATORY:**
  ```php
  public function findByDate(string $date, ?string $status = null): array
  ```
* Audit all methods in `src/Application/UseCase/` and `src/Infrastructure/Persistence/` for this deprecation.

### 2.3 Implement `#[\Override]` on All Repositories
Every repository implementing an interface in `src/Infrastructure/Persistence/` must declare `#[Override]` on all interface methods:
```php
namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\CompedOrder;
use FidelsPizza\Domain\Repository\CompedOrderRepositoryInterface;
use Override;

final class PdoCompedOrderRepository implements CompedOrderRepositoryInterface
{
    #[Override]
    public function findById(int $id): ?CompedOrder
    {
        // ...
    }
}
```

### 2.4 Use Native Array Helpers (`array_find`, `array_any`)
Refactor order item search and slot availability checks to use PHP 8.4/8.5 native functions:
```php
// Check if an order contains a specific pizza type:
$hasSpecialtyPizza = array_any($order->getItems(), fn($item) => $item->isSpecialty());

// Find matching pickup slot:
$selectedSlot = array_find($availableSlots, fn($slot) => $slot->id === $targetSlotId);
```

---

## 3. Mandatory Backend Governance Rules

1. **Strict Types:** `declare(strict_types=1);` must be at the very top of EVERY file (including migrations and root templates).
2. **Thin Controllers:** Root scripts ([`menu.php`](file:///C:/xampp/htdocs/fidelspizzaevent/menu.php), [`edit_order.php`](file:///C:/xampp/htdocs/fidelspizzaevent/edit_order.php), [`login.php`](file:///C:/xampp/htdocs/fidelspizzaevent/login.php)) must act solely as thin HTTP adapters that fetch use cases from [`getServiceContainer()`](file:///C:/xampp/htdocs/fidelspizzaevent/src/bootstrap.php).
3. **DOM Sanitization:** All output to HTML views must be escaped via `htmlspecialchars((string)$var, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
4. **Session Hardening:** In `config.php` and `bootstrap.php`, ensure sessions are started with `cookie_httponly = true`, `cookie_samesite = 'Lax'`, and `use_strict_mode = true`.
5. **Database Governance (MySQL InnoDB):** Ensure all tables (`orders`, `order_items`, `pizzas`, `pickup_time_slots`, `users`) use `ENGINE=InnoDB` with explicit foreign key constraints.

---

## 4. Agent Execution Checklist

1. **Syntax Check:** Run `C:\xampp\php\php.exe -l` on all PHP files in `src/`, root, and `admin/`.
2. **Deprecation Audit:** Confirm zero `E_DEPRECATED` notices from implicitly nullable parameters.
3. **Run Unit Tests:** Execute the test suite in `tests/` using `C:\xampp\php\php.exe` to guarantee zero regressions under PHP 8.5.12.
