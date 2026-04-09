# PHP & Laravel Refactoring Reference

Load this reference when refactoring PHP or Laravel code. These rules supplement the general refactoring guidance in SKILL.md.

## Modern PHP Conventions

Use these only when the project clearly supports them (check PHP version and existing code style):

- **`match` expressions** over verbose `if/elseif` chains or `switch` blocks when mapping a value to a result.
- **Nullsafe operator (`?->`)** to reduce null-check boilerplate: `$patient?->facility` instead of `$patient ? $patient->facility : null`.
- **Named arguments** for clarity when calling methods with multiple optional parameters.
- **Union types and nullable types** in method signatures: `function foo(?string $type, int|string $id)`.
- **`readonly` properties** (PHP 8.1+) for value objects and DTOs.
- **Enums** (PHP 8.1+) for fixed sets of status values, types, or categories.

## Laravel-Specific Patterns

### Avoid `compact()` for Return Values

Do not use `compact()` for returned method payloads when the reader must remember hidden key names. PHP's `compact()` creates an associative array from variable names, but the key names are invisible at the call site:

```php
// BAD — caller doesn't know the shape without reading the method
return compact('patient', 'facility', 'practiceId');

// BETTER — explicit keys
return [
    'patient' => $patient,
    'facility' => $facility,
    'practiceId' => $practiceId,
];

// BEST — separate method calls or a DTO when these travel together often
```

### `wasRecentlyCreated` and `refresh()`

`Model::refresh()` may reset transient attributes like `wasRecentlyCreated`. If the code needs to distinguish new vs. existing records after a `refresh()`, capture the distinction in an explicit boolean _before_ calling `refresh()`.

```php
// BAD — wasRecentlyCreated may be false after refresh
$bill = $this->findOrCreateBill(...);
$bill->increment('sum_seconds', $seconds);
$bill->refresh();
// ❌ This may always be false now:
$isNew = $bill->wasRecentlyCreated;

// GOOD — capture before mutation
$bill = $this->findOrCreateBill(...);
$isNewBill = $bill->wasRecentlyCreated;  // ✅ captured before refresh
$bill->increment('sum_seconds', $seconds);
$bill->refresh();
```

Do not rely on `wasRecentlyCreated` surviving `refresh()`, `load()`, or `fresh()` calls.

### Eager Loading

Preserve existing `load()` and `with()` calls during refactoring. If you move code into a new method, ensure the method's caller has already loaded the required relationships, or load them in the new method. Breaking eager loading silently introduces N+1 query regressions.

### Class Constants for Status Strings

Laravel models often use string statuses (`'pending'`, `'ready'`, `'resolved'`, etc.). Extract these into class constants:

```php
// BAD — stringly typed, typo-prone
if ($bill->status === 'pending') { ... }
$task->status = 'task_finished';

// GOOD — searchable, refactorable
private const STATUS_PENDING = 'pending';
private const STATUS_TASK_FINISHED = 'task_finished';

if ($bill->status === self::STATUS_PENDING) { ... }
$task->status = self::STATUS_TASK_FINISHED;
```

### Eloquent Scope Naming

When extracting query logic into reusable scopes or query methods, the method name should describe _what_ is being queried, not `getQuery` or `buildQuery`. For example: `billsForPatientInPeriod(...)`, `activeEnrollments(...)`.

## PHP-Specific Naming Pitfalls

### `$this->ensureXExists()` in ORM Contexts

In Laravel codebases, `ensure*Exists` often gets used for find-or-create patterns. This is misleading — `ensure` implies a void guard or an assertion that throws. If the method queries for an existing record, creates one when missing, and returns the model, name it `findOrCreate*` or `resolve*`:

```php
// BAD — reads like a boolean check or assertion
$bill = $this->ensureBillExists($patient, $facility, ...);

// GOOD — clearly states it returns an entity and may create one
$bill = $this->findOrCreateBill($patient, $facility, ...);
```

### Ternary Chains with Null Coalescing

Prefer `??` over ternary for null fallbacks:

```php
// Noisy
$value = $enrollment ? $enrollment->conditions_string : null;

// Clean
$value = $enrollment?->conditions_string;
```

## Common Laravel Refactoring Anti-Patterns

1. **God methods on Service classes** — Break into focused private/protected methods. Each method should do one thing.
2. **Inline query building in loops** — Extract into named query methods.
3. **Hardcoded IDs and codes** — Move to class constants with names that preserve the domain meaning (ID vs. code vs. magic number). Pay attention to comments like `"NOTE THIS IS THE ID NOT CODE"` — the constant name must reflect that distinction.
4. **Mixed validation and mutation** — Validate inputs (return bool or throw) separately from mutating state (return model or void).
5. **Silent side effects in getters** — A method named `get*` should not save, update, or create records. If it does, rename it.
