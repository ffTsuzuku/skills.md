# TASK-042: Implement Idempotency Key Handling for Webhook Ingestion

**Type**: Feature / Architecture Hardening  
**Estimated Complexity**: Medium  
**Target Files**: `app/Http/Controllers/WebhookController.php`, `app/Services/PaymentWebhookService.php`, `database/migrations/2026_09_01_create_idempotency_keys_table.php`  
**Date**: 2026-09-01  

---

## 1. 🎯 Mission & Problem Statement
Our payment provider (Stripe) guarantees at-least-once delivery for webhooks, meaning our endpoint occasionally receives the exact same `invoice.payment_succeeded` event multiple times within seconds. 

Currently, our application does not track processed event IDs, resulting in duplicate user credits and duplicated confirmation emails. We need to implement an idempotency check layer that records processed event keys within an atomic database transaction.

### Acceptance Criteria
- [ ] Incoming requests with an `idempotency_key` (or Stripe `event_id`) that has already succeeded return `200 OK` with the cached response without re-executing business logic.
- [ ] Concurrent duplicate requests for the same event key are locked or blocked from running concurrently.
- [ ] Failed webhook processing does not permanently lock the event key from being retried.
- [ ] Existing webhook unit and feature tests continue to pass with new idempotency coverage added.

---

## 2. 🧠 Domain & Business Logic Glossary

| Term / Concept | Plain-English Definition | Invariant / Business Rule |
| :--- | :--- | :--- |
| **Idempotency** | The property of an operation where executing it multiple times produces the same result as executing it once. | Multiple identical webhook deliveries MUST NOT create multiple transactions or side-effects. |
| **Event ID (`evt_...`)** | The unique identifier provided by Stripe in the webhook payload. | Every incoming event has a unique string ID that serves as our idempotency key. |
| **In-Flight Lock** | A temporary state indicating an event is currently being processed by a worker/thread. | If worker B receives `evt_123` while worker A is processing it, worker B must wait or return a retryable status. |

---

## 3. 🗺️ Code Map & Data Flow

### Data Flow Diagram
```
[POST /api/webhooks/stripe]
          ⬇
[WebhookController@handle]  <-- Extract event_id & payload
          ⬇
[IdempotencyManager]        <-- Check DB table `idempotency_keys`
          ├─► [Already Processed?] ──► Return cached 200 OK
          └─► [New Event]
                    ⬇
      [DB Transaction / Atomic Lock]
                    ⬇
      [PaymentWebhookService@process]
                    ⬇
      [Save Result & Mark Completed]
```

### Key Files & Symbol Reference
- `app/Http/Controllers/WebhookController.php`: Receives the raw HTTP payload and signature verification.
  - Inspect method: `handle(Request $request)`
- `app/Services/PaymentWebhookService.php`: Dispatches event types (`invoice.paid`, `customer.subscription.deleted`).
  - Inspect method: `process(array $payload)`
- `app/Models/IdempotencyKey.php`: Eloquent model tracking processed keys, status, and response bodies.
- `database/migrations/`: Where table schema definitions live.

---

## 4. 📐 Repo Conventions & Idiomatic Patterns to Follow

Look at how atomic database transactions and locking are handled elsewhere in our codebase:

```php
// Reference pattern from app/Services/WalletService.php:
public function transferCredits(User $sender, User $receiver, int $amount): void
{
    DB::transaction(function () use ($sender, $receiver, $amount) {
        // We use lockForUpdate() on critical balance records
        $senderBalance = Wallet::where('user_id', $sender->id)->lockForUpdate()->first();
        
        if ($senderBalance->amount < $amount) {
            throw new InsufficientFundsException("Cannot transfer credits");
        }
        
        $senderBalance->decrement('amount', $amount);
        $receiver->wallet()->increment('amount', $amount);
    });
}
```

> **Key Pattern Takeaways:**
> - All multi-step persistence logic is wrapped in `DB::transaction(...)`.
> - Custom domain exceptions are thrown for business logic failures.
> - Database locks (`lockForUpdate()`) prevent race conditions.

---

## 5. 📝 Interactive Implementation Worksheet

### 💭 Pre-Coding Thought Exercises
1. *Where should the unique constraint live—in application memory (Redis) or relational DB? What happens if Redis restarts?*
2. *If the service throws an unexpected exception halfway through, what should the status of the idempotency record be?*
3. *How long should idempotency records be kept before pruning?*

### 🔨 Stepped Milestones

#### Milestone 1: Migration & Model
- [ ] Step 1.1: Generate migration `create_idempotency_keys_table` with columns: `key` (string, unique index), `status` (string: pending, completed, failed), `response_code` (int), `response_body` (json, nullable), `created_at`, `updated_at`.
- [ ] Step 1.2: Create model `app/Models/IdempotencyKey.php` with `$fillable` attributes and casting.

#### Milestone 2: Idempotency Service Layer
- [ ] Step 2.1: Create `app/Services/IdempotencyService.php` with a `run(string $key, Closure $callback)` method.
- [ ] Step 2.2: Ensure execution is wrapped in a transactional lock.

#### Milestone 3: Webhook Controller Integration & Tests
- [ ] Step 3.1: Inject `IdempotencyService` into `WebhookController`.
- [ ] Step 3.2: Write a test in `tests/Feature/WebhookIdempotencyTest.php` that fires two identical POST requests concurrently.

---

## 6. 💡 Progressive Hints (Expand only if stuck!)

### Milestone 1 Hints
<details>
<summary>🔍 Level 1: Conceptual Nudge</summary>

Use Artisan CLI to make a migration. Think about indexing the `key` column for fast O(1) lookups.
</details>

<details>
<summary>🧩 Level 2: Target Files & CLI Commands</summary>

Run: `php artisan make:model IdempotencyKey -m`
Look at `database/migrations/xxxx_xx_xx_create_idempotency_keys_table.php`.
</details>

<details>
<summary>📋 Level 3: Pseudocode & Solution Blueprint</summary>

```php
Schema::create('idempotency_keys', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('status')->default('pending'); // pending, completed, failed
    $table->integer('response_code')->nullable();
    $table->json('response_body')->nullable();
    $table->timestamps();
});
```
</details>

### Milestone 2 Hints
<details>
<summary>🔍 Level 1: Conceptual Nudge</summary>

How can you use `firstOrCreate` with a DB transaction to ensure only one thread acquires the pending state?
</details>

<details>
<summary>🧩 Level 2: Target Files & Method Clues</summary>

In `app/Services/IdempotencyService.php`, use `DB::transaction(function() { ... })`. Check if status is `'completed'`. If so, return immediately.
</details>

<details>
<summary>📋 Level 3: Pseudocode & Solution Blueprint</summary>

```php
public function execute(string $key, callable $action): array
{
    return DB::transaction(function () use ($key, $action) {
        $record = IdempotencyKey::where('key', $key)->lockForUpdate()->first();

        if ($record && $record->status === 'completed') {
            return [
                'code' => $record->response_code,
                'body' => $record->response_body,
                'cached' => true
            ];
        }

        if (!$record) {
            $record = IdempotencyKey::create(['key' => $key, 'status' => 'pending']);
        }

        try {
            $result = $action();
            $record->update([
                'status' => 'completed',
                'response_code' => 200,
                'response_body' => $result,
            ]);
            return ['code' => 200, 'body' => $result, 'cached' => false];
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed']);
            throw $e;
        }
    });
}
```
</details>

---

## 7. ⚠️ Traps & Guardrails (Watch out for these!)
- ⚠️ **Trap 1 - Uncommitted Transactions**: If you save the idempotency record *inside* the same transaction as the webhook side-effects, an unhandled exception will roll back the idempotency record too, allowing an immediate duplicate retry.
- ⚠️ **Trap 2 - Large Payloads in DB**: Do not serialize massive binary payloads into the `response_body` json column. Only save the response data returned to the caller.

---

## 8. 🧪 Verification & Testing Recipes

### Automated Test Command
```bash
php artisan test --filter=WebhookIdempotencyTest
```

### Manual Verification Steps
1. Send first payload via cURL:
   ```bash
   curl -X POST http://localhost:8000/api/webhooks/stripe \
     -H "Content-Type: application/json" \
     -d '{"id": "evt_test_123", "type": "invoice.paid", "data": {}}'
   ```
2. Check database table: `SELECT * FROM idempotency_keys WHERE key = 'evt_test_123';`
3. Send identical cURL request again and observe response time (< 10ms) and no duplicate logs in `storage/logs/laravel.log`.

---

## 9. 🎓 Learning Takeaways
- **Idempotent API Design**: Protecting distributed systems against network retries.
- **Pessimistic vs Optimistic Locking**: When to use `lockForUpdate()` in relational databases.
- **Resilient Webhook Architecture**: Decoupling receipt from processing.
