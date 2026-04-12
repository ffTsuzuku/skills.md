---
name: laravel-testing
description: >
  Generates comprehensive, structurally correct PHPUnit test suites for Laravel service classes,
  models, jobs, controllers, and any other PHP class. Use this skill whenever the user shares
  source code and asks to write tests, expand coverage, fix an existing suite, or identify gaps.
  Triggers on: "write tests for this", "add test coverage", "fix my test suite", "what's not
  tested", "generate PHPUnit tests", "write unit tests", "test this class/service/job/model",
  or any time PHP/Laravel source is shared with testing intent — even phrased casually like
  "can you test this?" or "tests are missing for X". Always apply this skill before writing
  any PHP test code.
---

# Laravel Testing Skill

## Step 0: Produce a Branch Map Before Writing Anything

**Do not write a single line of test code until this step is complete and written out.**

A mental audit is not sufficient. You must produce an explicit branch map — a written
list of every method and every branch within it that requires a test. This is the document
you write tests against. If a branch is not in the map, it will not get a test.

### How to build the branch map

The branch map has two mandatory sections. Both must be present before you write any tests.

#### Section 1: Method inventory

List every method in the source file by name. For each one, assign a status:

- `BRANCHES BELOW` — has conditional logic that requires test cases, expanded in Section 2
- `SKIP — trivial` — only delegates or assigns, no branching (e.g. a one-line setter)
- `SKIP — unreachable` — cannot be reached by any means; explain why in one sentence

Every method in the source must appear here. If a method is missing from the inventory,
the map is incomplete and no tests should be written yet.

Example:

```
METHOD INVENTORY
─────────────────────────────────────────────────────
processTime()                     BRANCHES BELOW
loadRelations()                   SKIP — trivial, just calls load()
isValidForProcessing()            BRANCHES BELOW
getFacilitySettings()             SKIP — trivial, delegates to resolveFacilityRequirements()
resolvePatientEnrollment()        SKIP — trivial, delegates to getPatientEnrollment()
markAsNotCounted()                SKIP — trivial, sets flag and saves
determineChartCreatedAt()         BRANCHES BELOW
determineBillCodes()              BRANCHES BELOW  [HIGH]
getBillableTargetMinutes()        BRANCHES BELOW  [HIGH]
processBillCode()                 BRANCHES BELOW
getApplicableBillCodeIds()        BRANCHES BELOW
findOrCreateBill()                BRANCHES BELOW
findOrCreateTask()                BRANCHES BELOW
linkRecordedTimeToBill()          SKIP — trivial, sets bill_id and saves
updateBillTimeAndStatus()         BRANCHES BELOW
getNewlyReachedThreshold()        BRANCHES BELOW  [HIGH]
applyResolvedCcmStatus()          BRANCHES BELOW
applyBillableState()              SKIP — trivial, sets multiple fields, no branching
calculateDateOfService()          BRANCHES BELOW
updateTaskStatus()                BRANCHES BELOW
resolveFacilityRequirements()     BRANCHES BELOW
getPatientEnrollment()            BRANCHES BELOW
─────────────────────────────────────────────────────
```

#### Section 2: Branch detail

For every method marked `BRANCHES BELOW` in the inventory, list every discrete outcome
that requires its own test case:

- Every early `return` (including `return null`, `return false`, `return []`)
- Every `if` arm and every `else` arm
- Every `null` guard (both the null case and the non-null case)
- Every `foreach` body (empty collection case and populated case)
- Every `catch` block
- Every ternary arm where the two outcomes differ meaningfully

For each item, note:
- What condition triggers it
- What the observable outcome is (return value, model mutation, log call, save call, etc.)
- Whether it is data-driven (needs a `@dataProvider`)
- Whether it is `[HIGH]` priority

### Example branch map entry format

```
processTime()
  ├── [RT-01] counted_towards = false → returns immediately, no side effects
  ├── [RT-02] patient is null → Log::error "has no patient", returns
  ├── [RT-03] patient->facility is null → Log::error "has no facility", returns
  ├── [RT-04] practice_id is null → Log::error "does not have a primary practice", returns
  ├── [RT-05] facility feature not enabled → returns without processing
  ├── [RT-06] enrollment required + enrollment missing → counted_towards = false, save()
  ├── [RT-07] bill_codes is empty → Log::info "does not have bill codes", returns
  ├── [RT-08] all bill codes fail processBillCode → counted_towards = false, save()
  └── [RT-09] at least one bill code succeeds → counted_towards remains true

calculateDateOfService()
  ├── [DOS-01] task->datetime_of_service is set and within bill period → returns it
  ├── [DOS-02] task->datetime_of_service is outside bill period → returns bill end_date
  ├── [DOS-03] task->datetime_of_service is null → returns bill end_date
  ├── [DOS-04] resolvedAt provided and no valid dos → returns resolvedAt
  ├── [DOS-05] patient->deceased_date is set, after bill start, before dos → caps dos at deceased_date
  └── [DOS-06] patient->deceased_date is before bill start → does not affect dos
```

### Do not stop at the public surface

Private and protected methods with non-trivial logic must appear in the branch map too.
Reach them through the public interface where possible. If the public path is too
complex to set up, use reflection — but the branch must still be in the map and must
still have a test.

### Flag high-priority branches explicitly

Some branches carry more risk than others and must be marked `[HIGH]` in the map.
A branch is high priority if it:

- Contains named-constant switching (e.g. `if ($type === 'CCM')`, `if ($name === 'Olivera Community Care')`)
- Contains a threshold array or numeric boundary (e.g. `[60, 40, 20]`)
- Determines what gets saved, billed, or marked as processed
- Has never been tested in any prior suite

High-priority branches are not optional. They are the first ones written, not the last.
If a response runs out of space, low-priority branches are deferred — never high-priority ones.

Mark them in the map like this:

```
determineBillCodes()
  ├── [BC-01] [HIGH] cpt_type = CCM + practice name = Olivera → returns Olivera bill code
  ├── [BC-02] [HIGH] cpt_type = CCM + any other practice → returns careflow bill_codes
  └── [BC-03] [HIGH] cpt_type = RPM → returns careflow bill_codes

getBillableTargetMinutes()
  ├── [BT-01] [HIGH] Olivera CCM → [90, 60, 30]
  ├── [BT-02] [HIGH] bill_code_id = 99470 (RPM 2026) → [60, 40, 20, 10]
  └── [BT-03] [HIGH] anything else → [60, 40, 20]
```

### If an existing suite is provided

Produce the full branch map for the source first. Then mark each item as covered ✅ or
missing ❌. Only write tests for the missing items, and do not touch tests that are
already correct.

### The branch map must be output visibly before any code

Do not write tests in the same response as the branch map. The correct sequence is:

1. **Response 1:** Output the complete branch map — both the method inventory and the
   branch detail. Then count the total number of branch detail items and state it
   explicitly at the bottom of the map. End the response there. No tests, no preamble.
2. **Responses 2+:** Write the tests in branch map order. Begin each response by stating
   which items you are covering in this response (e.g. "Covering PT-01 through IV-04").
   End each response with a progress line: "Covered X of Y items. Remaining: [list]."
   Continue until all items are covered.

The branch map must appear in the conversation so the user can see exactly what was
committed to. A branch map that exists only in your reasoning and is never shown cannot
be verified, audited, or held to account. If it is not visible, it does not exist.

**The method inventory is the completeness check.** If every method in the source file
appears in the inventory — even trivial ones marked SKIP — then nothing can be silently
omitted. A map that covers two methods out of twelve is visibly incomplete the moment
the inventory is read. This is by design.

**Large classes will require multiple responses. This is expected and normal.** A class
with 20 methods and 50 branch items cannot be fully tested in one response. Do not
compress, skip, or silently omit items to make them fit. Instead, be transparent: state
the count up front, work through the items in order across as many responses as needed,
and track progress explicitly so the user can see exactly where you are at all times.

The user prompt "continue" means: write the next batch of tests, starting from the first
uncovered item in the branch map. Do not re-explain the map. Do not restart. Just
continue from where you left off and end with an updated progress line.

### The branch map is your definition of done

You are not finished when you run out of ideas or run out of response space. You are
finished when every item in the branch map has a corresponding test.

When writing tests, work through branch map items in order. Do not skip ahead, do not
reorder, do not quietly drop items because they seem hard to set up. If a branch is
genuinely unreachable through any means — public interface, reflection, or subclass —
state that explicitly next to the item in the map and explain why. A skipped item with
no explanation is a bug in the test suite.

**All `[HIGH]` items are written before any non-`[HIGH]` items.** If a response ends
before all `[HIGH]` items are covered, the next response begins with the remaining
`[HIGH]` items — not with wherever the ordered list happens to be.

---

## Core Rules

These are non-negotiable. Any test that violates them is worse than no test.

### Rule 1 — Never Mock the System Under Test

```php
// ❌ WRONG — you are testing a mock, not the real class
$this->service = Mockery::mock(MyService::class)->makePartial();

// ✅ CORRECT — instantiate the real class
$this->service = new MyService();

// ✅ CORRECT — inject mocked dependencies if the constructor requires them
$this->service = new MyService($this->createMock(SomeDependency::class));
```

If you need to isolate one internal method, write a hand-rolled subclass that overrides
only that method. Do not reach for `makePartial()` on the SUT.

### Rule 2 — Mock Only External I/O

Candidates for mocking:
- Eloquent queries not satisfied by seeders/factories
- Facades: `Log`, `Mail`, `Event`, `Queue`, `Cache`, `Notification`, `Http`
- Injected service classes (repositories, API clients, etc.)

Do **not** mock:
- The class under test
- Value objects or DTOs
- Carbon / plain PHP objects with no side effects
- Anything you could seed with a factory in the same time it takes to mock it

### Rule 3 — Never Use RefreshDatabase. Use DatabaseTransactions Instead.

**`RefreshDatabase` is unconditionally banned.** It runs `migrate:fresh` against whatever
database your test environment points to, wiping it completely. If that database is shared
across developers, it destroys everyone's data with no warning and no recovery. Never use it.

**`DatabaseTransactions` is the current approved approach for tests that require DB
interaction.** It wraps each test in a transaction and rolls it back on completion, so any
rows your test writes are cleaned up automatically without touching pre-existing data.

```php
// ❌ NEVER — destroys the shared database
use Illuminate\Foundation\Testing\RefreshDatabase;

// ✅ APPROVED — writes are rolled back, shared data is untouched
use Illuminate\Foundation\Testing\DatabaseTransactions;
```

**Known limitation:** `DatabaseTransactions` only protects against writes leaking out. It
does not protect against the shared database leaking in. If your test runs a query and
pre-existing rows in the shared DB match that query, your test may get unexpected results —
a `first()` that should return `null` might return a real record, taking a different code
path entirely. Tests can become non-deterministic across machines as a result.

This is an accepted tradeoff for now. The long-term fix is a dedicated per-developer test
database, at which point `DatabaseTransactions` can be swapped for `RefreshDatabase` in a
single find-and-replace.

**When to use `DatabaseTransactions` vs pure mocking:**

- Use `DatabaseTransactions` when testing logic that is meaningfully coupled to real query
  behaviour — joins, ordering, nullable constraints, scope chains.
- Use pure Eloquent mocking (no trait) for unit tests where the query outcome is not what
  you are testing. This is faster and immune to shared DB interference.
- Never mix both approaches in the same test class.

### Rule 4 — One Assertion Focus Per Test

Each test should assert one logical outcome. "Logical outcome" means a single observable
effect: a return value, a model attribute, a DB record, a log call, an event dispatch.
Multiple `assert*` calls are fine when they describe the same outcome.

```php
// ✅ Fine — all assertions describe one outcome: the bill was created correctly
$this->assertDatabaseHas('bills', ['patient_id' => $patient->id]);
$this->assertEquals('ready', Bill::first()->status);

// ❌ Avoid — two unrelated outcomes jammed into one test
$this->assertDatabaseHas('bills', [...]);
$this->assertDatabaseHas('tasks', [...]);  // separate concern, separate test
```

### Rule 5 — Test Names Must Describe Behaviour, Not Implementation

```php
// ❌ Tells you nothing when it fails
public function test_process_time(): void

// ✅ Tells you exactly what broke and why
public function test_marks_recorded_time_as_not_counted_when_enrollment_is_required_but_missing(): void
```

Use the pattern: `test_{subject}_{condition}_{expected_outcome}`.

---

## Test Structure

### File Organisation

Mirror the `app/` directory structure under `tests/`:

```
app/Services/Billing/BillingTimeProcessor.php
→ tests/Unit/Services/Billing/BillingTimeProcessorTest.php

app/Jobs/ProcessRecordedTime.php
→ tests/Unit/Jobs/ProcessRecordedTimeTest.php
```

For complex classes with many branches, split into focused files:

```
tests/Unit/Services/Billing/BillingTimeProcessor/
├── ProcessTimeTest.php          ← orchestration / early-exit branches
├── ThresholdTest.php            ← data-driven threshold logic
├── FindOrCreateBillTest.php     ← bill lookup and creation
└── DateOfServiceTest.php        ← DOS calculation branches
```

### Test Class Templates

**For pure unit tests (no DB interaction — preferred):**
```php
<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\MyService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class MyServiceTest extends TestCase
{
    // No database trait — all Eloquent calls are mocked

    private MyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MyService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**For tests that require real DB queries:**
```php
<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\MyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class MyServiceTest extends TestCase
{
    use DatabaseTransactions; // rolls back after each test — never use RefreshDatabase

    private MyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MyService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

---

## Mocking Patterns

### Laravel Facades

```php
// Log
Log::shouldReceive('error')
    ->once()
    ->withArgs(fn($msg) => str_contains($msg, 'expected fragment'));

Log::shouldReceive('info')->never(); // assert it is NOT called

// Event
Event::fake();
$this->service->doSomething();
Event::assertDispatched(SomeEvent::class);

// Mail
Mail::fake();
$this->service->doSomething();
Mail::assertSent(SomeMailable::class, fn($m) => $m->hasTo('user@example.com'));

// Queue
Queue::fake();
$this->service->doSomething();
Queue::assertPushed(SomeJob::class);
```

### Injected Dependencies (Mockery)

```php
$repo = Mockery::mock(PatientRepository::class);
$repo->shouldReceive('findActive')
     ->once()
     ->with(42)
     ->andReturn(Patient::factory()->make(['id' => 42]));

$this->service = new MyService($repo);
```

### Injected Dependencies (PHPUnit)

```php
$repo = $this->createMock(PatientRepository::class);
$repo->method('findActive')->willReturn(Patient::factory()->make());
$this->service = new MyService($repo);
```

### Eloquent Static Calls (Queries)

Since we never touch the database, all Eloquent query chains must be mocked. The
cleanest way to do this is to alias-mock the model class.

**Simple query returning one record:**
```php
$bill = Mockery::mock(Bill::class)->makePartial();
$bill->id = 1;
$bill->status = 'pending';
$bill->sum_seconds = 0;

Mockery::mock('alias:App\Models\Bill')
    ->shouldReceive('where->where->first')
    ->andReturn($bill);
```

**Query returning null (record not found):**
```php
Mockery::mock('alias:App\Models\Bill')
    ->shouldReceive('where->where->first')
    ->andReturn(null);
```

**Query returning a collection:**
```php
Mockery::mock('alias:App\Models\Patient')
    ->shouldReceive('where->get')
    ->andReturn(collect([$patient1, $patient2]));
```

**Chained scopes (whereIn, orderBy, etc.):**
```php
Mockery::mock('alias:App\Models\Bill')
    ->shouldReceive('whereIn->where->orderBy->first')
    ->andReturn($bill);
```

**Model `save()` — assert it is called:**
```php
$model = Mockery::mock(Bill::class)->makePartial();
$model->shouldReceive('save')->once();
```

**Model `save()` — assert it is never called:**
```php
$model = Mockery::mock(Bill::class)->makePartial();
$model->shouldReceive('save')->never();
```

**Model `create()` — static factory method:**
```php
$newBill = Mockery::mock(Bill::class)->makePartial();
$newBill->id = 99;

Mockery::mock('alias:App\Models\Bill')
    ->shouldReceive('create')
    ->once()
    ->withArgs(fn($attrs) => $attrs['patient_id'] === 42)
    ->andReturn($newBill);
```

**Model `increment()` and `refresh()`:**
```php
$bill->shouldReceive('increment')->once()->with('sum_seconds', 600);
$bill->shouldReceive('refresh')->once()->andReturnSelf();
```

### Building Model Instances Without the Database

Use `make()` — not `create()` — to get a model instance with attributes set but no DB
write. Assign relations directly as properties.

```php
// A model instance with attributes, no DB hit
$patient = Patient::factory()->make(['id' => 1]);

// Attach a relation directly — no DB join needed
$facility = Facility::factory()->make(['id' => 10, 'timezone' => 'America/New_York']);
$patient->setRelation('facility', $facility);

// Attach a collection relation
$practice = Practice::factory()->make(['id' => 5, 'name' => 'Main Practice']);
$facility->setRelation('practices', collect([$practice]));
```

`setRelation()` bypasses Eloquent's lazy-loading and lets you wire up any object graph
you need without a database.

### Shared Model-Building Helpers

Extract repeated model construction into protected helpers on the test class:

```php
protected function makePatient(array $attrs = []): Patient
{
    $patient = Patient::factory()->make(array_merge(['id' => 1], $attrs));
    $facility = $this->makeFacility();
    $patient->setRelation('facility', $facility);
    return $patient;
}

protected function makeFacility(array $attrs = []): Facility
{
    $facility = Facility::factory()->make(array_merge(
        ['id' => 1, 'timezone' => 'America/New_York'],
        $attrs
    ));
    $practice = Practice::factory()->make(['id' => 1, 'name' => 'Default Practice']);
    $facility->setRelation('practices', collect([$practice]));
    return $facility;
}
```

This gives you a fully-wired object graph with zero database interaction.

---

## The load() Problem

Many Laravel service classes call `$model->load([...])` internally to eager-load
relations before doing their work. This is one of the most common blockers when writing
unit tests, because `load()` fires real Eloquent queries against the database.

**Why it breaks tests:** Even if you build a model with `factory()->make()` and wire up
all its relations with `setRelation()`, a subsequent `$model->load(...)` call inside the
SUT will overwrite those relations by querying the real database — or throw an exception
if there is no DB connection. Tests that get past early exit branches will fail or behave
unpredictably for this reason.

### Preferred solution: make loadRelations() protected in the source

If you own the source code, change the visibility of the internal relation-loading method
from `private` to `protected`. This has no effect on production behaviour but unlocks
the cleanest test pattern — a minimal subclass that makes `load()` a no-op:

```php
// In the source class — change private to protected
protected function loadRelations(RecordedTime $recordedTime): void
{
    $recordedTime->load([
        'patient.facility.corporation',
        'patient.facility.practices',
        'task.careflow',
    ]);
}
```

```php
// In your test file — override to do nothing
class TestableBillingTimeProcessor extends BillingTimeProcessor
{
    protected function loadRelations(RecordedTime $recordedTime): void
    {
        // No-op: relations are pre-wired via setRelation() in tests
    }
}

// In setUp()
$this->service = new TestableBillingTimeProcessor();
```

Your test helper then builds the full object graph with `factory()->make()` and
`setRelation()`, and since `loadRelations()` never runs, those relations survive into the
method under test.

### Fallback solution: stub load() with makePartial()

If you cannot modify the source, `makePartial()` on the input model is acceptable for
the sole purpose of stubbing `load()`. This is the **one legitimate exception** to the
rule against `makePartial()` on input models — it is narrow, justified, and must not
expand beyond this single method stub.

```php
protected function makeRecordedTime(array $attrs = []): RecordedTime
{
    // Build a real model instance with factory attributes
    $built = RecordedTime::factory()->make(array_merge([
        'id'              => 1,
        'counted_towards' => true,
        'practice_id'     => 1,
        'cpt_type'        => 'CCM',
        'seconds'         => 300,
        'recorded_at_local' => Carbon::now()->toDateTimeString(),
    ], $attrs));

    // Wrap only to stub load() — nothing else is mocked here
    $mock = Mockery::mock($built)->makePartial();
    $mock->shouldReceive('load')->andReturnSelf();

    return $mock;
}
```

Then wire up relations normally in each test:

```php
$recordedTime = $this->makeRecordedTime(['cpt_type' => 'RPM']);
$patient = $this->makePatient();
$recordedTime->setRelation('patient', $patient);
```

### Which solution to use

| Situation | Solution |
|---|---|
| You can change the source | Make `loadRelations()` `protected`, use a subclass |
| You cannot change the source | `makePartial()` on the input model, stub `load()` only |
| You are tempted to stub more than `load()` | Stop — use `setRelation()` for everything else |

---

## Data Providers for Branch-Heavy Logic

Any logic that varies by a discrete input (type string, threshold value, status, date
range) belongs in a data provider, not duplicated across near-identical test methods.

```php
/** @dataProvider billingThresholdProvider */
public function test_threshold_detection(int $prev, int $curr, ?int $expected): void
{
    $result = $this->service->getNewlyReachedThreshold($prev, $curr);
    $this->assertSame($expected, $result);
}

public static function billingThresholdProvider(): array
{
    return [
        'crosses lower bound'     => [1100, 1250, 20],
        'crosses middle bound'    => [2300, 2500, 40],
        'crosses upper bound'     => [3500, 3700, 60],
        'already above, no cross' => [3700, 3900, null],
        'below all thresholds'    => [0,    500,  null],
        'exact boundary'          => [1199, 1200, 20],
    ];
}
```

Cover: the lower boundary, each threshold crossing in isolation, already-past-threshold
(no double-trigger), and below-all-thresholds.

---

## Testing Private and Protected Methods

### Prefer the Public Interface

The best test of a private method is one that exercises it through a public method. If a
private method is only reachable via one public path, that is fine — cover the branch via
that path.

### Reflection (When Necessary)

When a private method has complex logic with many branches that are difficult to reach
through the public interface, use reflection:

```php
private function callPrivate(string $method, array $args = []): mixed
{
    $ref = new \ReflectionMethod($this->service, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($this->service, $args);
}

public function test_some_private_calculation(): void
{
    $result = $this->callPrivate('calculateDateOfService', [$patient, $bill, $task]);
    $this->assertEquals('2024-12-31', $result->toDateString());
}
```

Add `callPrivate` as a helper method on your base test class or a shared trait so it is
available everywhere.

### Private vs Protected: choosing the right approach

**This distinction is critical. Getting it wrong produces a PHP fatal error at runtime.**

| Visibility | Accessible from subclass? | Correct test approach |
|---|---|---|
| `private` | ❌ No | `ReflectionMethod` (`callPrivate`) |
| `protected` | ✅ Yes | Subclass with expose method OR `ReflectionMethod` |

**`private` methods cannot be called via dynamic dispatch from a subclass.** PHP will
throw a fatal error. This pattern silently fails for private methods:

```php
// ❌ WILL FATAL ERROR if $method is private on the parent
class TestableFoo extends Foo
{
    public function exposeMethod(string $method, mixed ...$args): mixed
    {
        return $this->$method(...$args); // Fatal: cannot access private method
    }
}
```

Before using a subclass expose pattern, check the visibility of every method you intend
to call through it. If any are `private`, use `callPrivate` with reflection instead.

**For `protected` methods**, a subclass is the cleaner option because it does not require
`setAccessible(true)` and makes the intent explicit:

```php
// ✅ Correct — only works if the methods are protected, not private
class TestableBillingTimeProcessor extends BillingTimeProcessor
{
    protected function loadRelations(RecordedTime $recordedTime): void
    {
        // No-op — prevents DB hits during tests
    }

    public function expose(string $method, mixed ...$args): mixed
    {
        return $this->$method(...$args);
    }
}
```

**For `private` methods**, always use `ReflectionMethod` regardless of how many there are:

```php
private function callPrivate(string $method, array $args = []): mixed
{
    $ref = new \ReflectionMethod($this->service, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($this->service, $args);
}
```

**The safest general approach** when a class has a mix of `private` and `protected`
methods is to use `callPrivate` for everything. Reflection works on both visibilities,
so it is always correct. The subclass pattern is an optimisation, not a necessity.

**Before writing a single expose call**, check the source and confirm the method is
`protected`. If it is `private`, switch to `callPrivate`. Do not assume — verify.

---

## Exception and Error Path Testing

### Exceptions Thrown by the SUT

```php
public function test_throws_when_patient_not_found(): void
{
    $this->expectException(PatientNotFoundException::class);
    $this->expectExceptionMessage('Patient 99 not found');
    $this->service->process(99);
}
```

### Exceptions That Are Caught Internally

When the SUT catches an exception and logs it, assert two things: the log call fired,
and execution continued past the catch block (i.e. the method did not re-throw).

The key is making `save()` throw on a mocked model instance:

```php
public function test_logs_error_and_continues_when_task_session_save_throws(): void
{
    $recordedTime = $this->makeRecordedTime();

    $taskSession = Mockery::mock(TaskSession::class)->makePartial();
    $taskSession->shouldReceive('save')
        ->once()
        ->andThrow(new \Exception('DB connection lost'));

    $recordedTime->setRelation('taskSession', $taskSession);

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn($msg) => str_contains($msg, 'Error saving task session'));

    // Call the method and assert it did not re-throw
    // If it did re-throw, this line would never be reached
    $this->callPrivate('updateTaskStatus', [$recordedTime, $task, true, $bill]);

    // Assert the task status was still updated despite the exception
    $this->assertEquals('task_finished', $task->status);
}
```

The pattern is always: mock the dependency to throw → assert the log → assert the
post-catch state is correct. Never use `$this->expectException()` for internally caught
exceptions — that asserts the exception escaped, which is the opposite of what you want.

---

## Asserting Stateful Outcomes

Some of the hardest branches to test are those where the observable outcome is not a
return value or a log call, but a combination of attribute mutations on a model that
has been passed through several internal operations. This section covers the patterns
for each category.

### Mutations through increment() → refresh() → save()

When a method calls `$bill->increment('sum_seconds', $seconds)` followed by
`$bill->refresh()`, the mock must simulate what the real DB would do — increment the
value and make it readable on the next access.

```php
$bill = Mockery::mock(Bill::class)->makePartial();
$bill->sum_seconds = 1170; // prev: just below 20-min threshold (1200s)
$bill->date_submitted = null;
$bill->date_billable = null;

$bill->shouldReceive('increment')
    ->once()
    ->with('sum_seconds', 300)
    ->andReturnUsing(function() use ($bill) {
        $bill->sum_seconds = 1470; // simulate what increment does in the DB
    });

$bill->shouldReceive('refresh')
    ->once()
    ->andReturnSelf(); // refresh reads back the value we just set above

$bill->shouldReceive('save')->once();

$this->callPrivate('updateBillTimeAndStatus', [$recordedTime, $bill, $task, $billCode, null]);

// Assert the threshold was crossed and billable state was applied
$this->assertEquals('ready', $bill->status);
$this->assertNotNull($bill->date_billable);
```

The critical detail is `andReturnUsing()` — it lets you mutate the mock's own property
inside the callback, so the subsequent `refresh()` and attribute reads see the updated
value. Without this, `$bill->sum_seconds` stays at its initial value and threshold logic
never fires.

### Asserting multiple attribute changes on one model

When a method sets several attributes on a model (e.g. `applyBillableState()` sets
`dos`, `date_billable`, `status`, `dx_codes`, `user_id`, `task_session_id`), assert
each attribute that has branching logic. Do not assert attributes that are always set
unconditionally — those do not need their own test cases.

Focus on the conditional ones:

```php
// Test: date_billable is preserved when already set
$bill = new Bill();
$bill->date_billable = '2024-01-01'; // already set
$bill->task_session_id = null;

$this->callPrivate('applyBillableState', [$bill, $recordedTime, null, $dos]);

$this->assertEquals('2024-01-01', $bill->date_billable); // preserved, not overwritten
$this->assertEquals($recordedTime->task_session_id, $bill->task_session_id); // was null, now set

// Test: date_billable is set when null
$bill2 = new Bill();
$bill2->date_billable = null;

$this->callPrivate('applyBillableState', [$bill2, $recordedTime, null, $dos]);

$this->assertEquals($recordedTime->recorded_at_local, $bill2->date_billable); // now set
```

Split these into separate test methods — one per conditional attribute — so a failure
identifies exactly which condition broke.

### Asserting model replication

When a method replicates a model and saves the copy (e.g. `TaskSession::replicate()`),
assert that the replicated instance was saved with the correct attributes, not that
`replicate()` was called.

```php
$originalSession = Mockery::mock(TaskSession::class)->makePartial();
$originalSession->task_id = 1;
$originalSession->new_status = 'task_started';

$replicatedSession = Mockery::mock(TaskSession::class)->makePartial();
$replicatedSession->shouldReceive('save')
    ->once()
    ->andReturnUsing(function() use ($replicatedSession) {
        // Assert the replicated session has the correct values at save time
        \PHPUnit\Framework\Assert::assertEquals(99, $replicatedSession->task_id);
        \PHPUnit\Framework\Assert::assertEquals('task_finished', $replicatedSession->new_status);
    });

$originalSession->shouldReceive('replicate')
    ->once()
    ->andReturn($replicatedSession);

$recordedTime->setRelation('taskSession', $originalSession);
$recordedTime->task_id = 1;

// task->id is different from recordedTime->task_id to trigger replication branch
$task = $this->makeTask(['id' => 99]);

$this->callPrivate('updateTaskStatus', [$recordedTime, $task, true, $bill]);
```

The inner `Assert::` calls inside `andReturnUsing()` fire at save time, which is the
only moment you can guarantee the attributes have been set.

### Asserting the RPM 2026 bill code swap

The bill code swap (`$bill->bill_code_id = $billCode->id`) only fires when two
conditions are both true: the bill started as the 2026 code AND the threshold reached
is >= 20 minutes. Test them independently and together.

```php
// Condition met: bill started as RPM 2026 code, threshold >= 20 min
public function test_rpm_2026_bill_code_id_swaps_when_threshold_reaches_20_minutes(): void
{
    $recordedTime = $this->makeRecordedTime(['cpt_type' => 'RPM', 'seconds' => 1200]);
    $recordedTime->setRelation('patient', $this->makePatient());

    $bill = Mockery::mock(Bill::class)->makePartial();
    $bill->bill_code_id = 99470; // starts as RPM 2026 code
    $bill->sum_seconds = 0;
    $bill->date_submitted = null;
    $bill->date_billable = null;

    $bill->shouldReceive('increment')
        ->andReturnUsing(fn() => $bill->sum_seconds = 1200); // crosses 20-min threshold

    $bill->shouldReceive('refresh')->andReturnSelf();
    $bill->shouldReceive('save')->once();

    $billCode = new BillCode(['id' => 5, 'code' => 99457, 'cpt_category' => 'RPM']);
    $task = $this->makeTask();

    $this->callPrivate('updateBillTimeAndStatus', [$recordedTime, $bill, $task, $billCode, null]);

    $this->assertEquals(5, $bill->bill_code_id); // swapped from 99470 to billCode->id
}

// Condition not met: threshold < 20 min (10 min reached instead)
public function test_rpm_2026_bill_code_id_does_not_swap_at_10_minutes(): void
{
    // bill_code_id stays as 99470 when only 10-min threshold is crossed
    // ... same setup but sum_seconds reaches 600 (10 min) not 1200
    $this->assertEquals(99470, $bill->bill_code_id); // not swapped
}
```

---

## Asserting Side Effects

| Side effect              | Assertion                                                              |
|--------------------------|------------------------------------------------------------------------|
| Model attribute mutated  | `$this->assertEquals($expected, $model->attribute)`                   |
| Model `save()` called    | `$model->shouldReceive('save')->once()`                                |
| Model `save()` not called| `$model->shouldReceive('save')->never()`                               |
| Model `create()` called  | mock alias, `shouldReceive('create')->once()->withArgs(...)`           |
| Log written              | `Log::shouldReceive('info')->once()->withArgs(...)`                    |
| Log never written        | `Log::shouldReceive('error')->never()`                                 |
| Event dispatched         | `Event::fake()` then `Event::assertDispatched(MyEvent::class)`        |
| Job queued               | `Queue::fake()` then `Queue::assertPushed(MyJob::class)`              |
| Mail sent                | `Mail::fake()` then `Mail::assertSent(MyMailable::class)`             |
| Return value             | `$this->assertSame($expected, $result)`                               |
| Exception thrown         | `$this->expectException(MyException::class)`                          |
| Exception swallowed      | assert log call; assert post-catch state; see Exception section       |
| Attribute mutated through `increment()` | `andReturnUsing()` to simulate DB side-effect; see Stateful Outcomes |
| Model replicated and saved | assert attributes on replicated instance inside `andReturnUsing()` on `save()` |
| Conditional attribute preserved | assert old value unchanged; assert other path sets it; split into two tests |

---

## Common Mistakes to Avoid

| Mistake | Fix |
|---|---|
| `Mockery::mock(SUT::class)->makePartial()` | `new SUT()` |
| Using `RefreshDatabase` | Unconditionally banned — use `DatabaseTransactions` or pure mocking instead |
| Using `factory()->create()` | Use `factory()->make()` and `setRelation()` |
| Asserting `load()` was called without checking relations | Assert the relation's data on the model instead |
| One test covering multiple unrelated outcomes | Split into one test per outcome |
| Testing that a method calls another method on the same class | Test the observable outcome instead |
| No tests for the `false`/`null`/empty collection paths | Trace every early return and add a test |
| Named constants tested only with magic numbers | Reference the constant or document why the value was chosen |
| Alias-mocking a model globally when only one method needs mocking | Use `makePartial()` on a model instance and mock only that method |
| `Mockery::mock(InputModel::class)->makePartial()` broadly on input models | Use `factory()->make()` and `setRelation()`. The only accepted exception is stubbing `load()` — see The load() Problem section |
| Importing `DatabaseTransactions` but mocking all Eloquent calls | Remove the trait — it does nothing when no real queries run |
| Stopping when ideas run out | Stop when every item in the branch map has a test |
| Using `$this->expectException()` for a catch block that swallows the exception | Assert the log call and the post-catch state instead — see Exception section |
| Asserting `increment()` was called without simulating the DB side-effect | Use `andReturnUsing()` to update the mock's attribute so threshold logic fires correctly |
| Asserting `replicate()` was called | Assert the replicated instance's attributes at `save()` time instead |
| Testing both arms of a conditional attribute in one test | Split into two tests — one where the value is already set, one where it is null |
| `assertTrue(true)` as the only assertion | Every test must have an assertion that can actually fail |
| Missing closing brace on a long test method | The next `public function` appears to be nested inside the previous test — count braces before submitting |
| Using `$this->$method()` dynamic dispatch in a subclass to call a `private` parent method | PHP will fatal error — `private` methods require `ReflectionMethod`; only `protected` methods are accessible via subclass dispatch |
| Assuming a method is `protected` without checking | Open the source and verify the visibility before choosing the subclass pattern over `callPrivate` |
| Calling `shouldReceive()` on a real model instance | `shouldReceive()` only works on Mockery mocks — wrap with `Mockery::mock($model)->makePartial()` first |

---

## Checklist Before Submitting Tests

Work through this in order. Do not submit until every item is checked.

**Branch map**
- [ ] The branch map was output as visible text in a response before any test code was written
- [ ] The map contains a method inventory listing every method in the source file by name
- [ ] Every method in the inventory is assigned a status: `BRANCHES BELOW`, `SKIP — trivial`, or `SKIP — unreachable`
- [ ] Every method marked `BRANCHES BELOW` has its branches expanded in Section 2 of the map
- [ ] Every `SKIP` entry has a one-sentence explanation
- [ ] The total branch item count was stated at the bottom of the map response
- [ ] Every branch containing named-constant switching, threshold arrays, or billing outcomes is marked `[HIGH]`
- [ ] All `[HIGH]` branches have tests — written before any non-`[HIGH]` branches
- [ ] Each test response began with a statement of which items it was covering
- [ ] Each test response ended with a progress line: "Covered X of Y. Remaining: [list]"
- [ ] Every item in the branch map has a corresponding test — count branch items and compare to test method count
- [ ] Every skipped branch has an explicit written explanation of why it is unreachable

**Structure**
- [ ] The SUT is instantiated directly with `new`, never with `Mockery::mock()`
- [ ] Input models (arguments passed to the SUT) are built with `factory()->make()` and `setRelation()`, not `Mockery::mock()->makePartial()`
- [ ] `RefreshDatabase` is absent from every test file
- [ ] `DatabaseTransactions` is present only in test classes that actually execute real DB queries — if all Eloquent calls are mocked, remove the trait
- [ ] `Mockery::close()` is called in `tearDown()`

**Coverage**
- [ ] Every early `return` in every public method has a dedicated test
- [ ] Every `if` arm and every `else` arm has at least one test
- [ ] Every `catch` block has a test that triggers it
- [ ] Every `foreach` has a test for the empty collection case and the populated case
- [ ] Data-driven logic (thresholds, type strings, status values, constants) uses `@dataProvider`
- [ ] Each `@dataProvider` includes: the minimum boundary, values above and below each threshold, and the already-past case

**Quality**
- [ ] Facades are faked/mocked where needed (`Log::shouldReceive`, `Event::fake()`, etc.)
- [ ] Test names follow `test_{subject}_{condition}_{expected_outcome}`
- [ ] No test asserts more than one logical outcome
- [ ] No test contains `assertTrue(true)` or any other assertion that cannot fail
- [ ] All model instances are built with `factory()->make()` and `setRelation()`, not `create()`
- [ ] If the SUT calls `load()` internally, the load() Problem section has been read and one of its two solutions is applied — either a `protected` subclass override or a narrowly scoped `makePartial()` stub on the input model
- [ ] Every method called via a subclass expose pattern has been confirmed `protected` in the source — if any are `private`, `callPrivate` with reflection is used instead
- [ ] `callPrivate` is present on the test class or a shared base class and is used for all `private` method access

**Syntax verification (run before presenting the file)**
- [ ] Count every `public function test_` in the file — the number must equal the branch map item count minus any explicitly skipped items
- [ ] Count opening braces `{` and closing braces `}` in the file — they must be equal
- [ ] Every test method has exactly one opening `{` on its signature line and exactly one closing `}` on its own line before the next `public function` or end of class
- [ ] No test method body contains another `public function` declaration — this indicates a missing closing brace from the previous method
- [ ] The file ends with exactly two closing braces: one for the last test method, one for the class
- [ ] State the final method count explicitly at the end of the last test response: "File contains N test methods covering M of M branch map items."
