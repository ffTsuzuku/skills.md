---
name: clean-code-refactor
description: Refactor code toward small, intention-revealing, testable, low-coupling design in the style commonly associated with Robert C. Martin's Clean Code principles. Use this skill whenever the user asks to refactor messy code, improve readability, reduce complexity, remove duplication, clarify naming, separate concerns, simplify logic, improve testability, or make code easier to maintain without changing behavior.
---

# Clean Code Refactor

Refactor existing code according to disciplined clean-code principles: preserve behavior, improve readability, reduce complexity, and make the design easier to understand, extend, and test.

## Core Goal

Turn hard-to-read, tightly coupled, overly clever, duplicated, or bloated code into code that is:

- easier to read than to write
- organized around clear responsibilities
- named so intent is obvious
- small in scope at every level
- testable without fragile setup
- explicit rather than surprising
- safe to change

The purpose is not to make code “fancy.” The purpose is to make it understandable.

## Operating Principles

Apply these principles throughout the task:

1. **Preserve behavior first**
   - Do not intentionally change business behavior unless the user asked for that.
   - Refactoring is primarily structural improvement.
   - If behavior seems buggy, note it before changing it unless the user explicitly asked for a bug fix.

2. **Make the code tell the truth**
   - Names should reveal intent.
   - Structure should reveal responsibility.
   - Control flow should reveal policy.
   - Hidden assumptions should be pulled into explicit code.

3. **Prefer clarity over cleverness**
   - Remove dense, compressed, overly magical logic.
   - Avoid unnecessary abstractions.
   - Avoid tricks that save lines but cost comprehension.

4. **Keep units small**
   - Functions should do one thing.
   - Classes/modules should have narrow responsibilities.
   - Files should have coherent purpose.

5. **Separate concerns**
   - Distinguish business rules from I/O, framework glue, persistence, formatting, transport, and orchestration.
   - Push side effects outward where practical.

6. **Reduce mental load**
   - Eliminate needless branching, nesting, flags, duplication, and temporal coupling.
   - Make the happy path obvious.

7. **Leave the code cleaner than you found it**
   - Improve nearby naming, structure, and consistency when directly relevant.
   - Do not perform unrelated rewrites just because they are possible.

## Refactoring Priorities

When deciding what to improve, prioritize in this order:

1. behavior safety
2. readability
3. responsibility separation
4. naming
5. duplication removal
6. complexity reduction
7. testability
8. consistency with existing architecture
9. stylistic polish

## What “Clean” Means in Practice

### 1. Naming

Use names that reveal intent.

Prefer:
- `calculateInvoiceTotal`
- `isEligibleForRefund`
- `loadUserProfile`
- `retryCount`

Avoid:
- `doStuff`
- `handleData`
- `tmp`
- `x`
- `manager`
- `util`
- `processThing`

Rules:
- Avoid abbreviations unless they are domain-standard.
- Avoid names that only describe type.
- Avoid misleading names.
- Boolean names should sound boolean: `isReady`, `hasAccess`, `canRetry`.
- Functions should read like actions.
- Collections should be plural when appropriate.
- Avoid weasel words like `Helper`, `Common`, `Misc`, `Thing`, `Stuff`.

### 2. Functions

Functions should:
- do one thing
- be small
- have a clear abstraction level
- avoid boolean flag arguments where possible
- avoid hidden side effects unless clearly named
- minimize parameter count

Prefer:
- extracting logic into well-named helpers
- replacing condition-heavy functions with focused branches or polymorphism where justified
- early returns to reduce nesting
- readable orchestration at the top, details below

Avoid:
- long functions with mixed abstraction levels
- functions that validate, transform, persist, log, notify, and format all at once
- output parameters
- confusing shared mutable state

### 3. Conditionals

Refactor conditionals to reduce nesting and ambiguity.

Prefer:
- guard clauses
- intention-revealing predicate helpers
- replacing repeated condition trees with domain concepts
- flattening control flow

Avoid:
- deeply nested `if/else`
- duplicated branching logic
- negative condition stacks
- giant switch statements when behavior can be localized more cleanly

### 4. Duplication

Remove duplication in:
- logic
- branching
- literals with meaning
- workflow steps
- validation rules
- knowledge about domain policy

But do **not** aggressively unify code that only looks similar.
Duplicate code is less dangerous than the wrong abstraction.

### 5. Comments

Prefer code that does not need explanatory comments.

Keep or add comments only when they provide value that code cannot easily express:
- legal/regulatory constraints
- non-obvious business rules
- architectural rationale
- why a surprising choice exists

Remove or avoid:
- redundant comments
- noisy banner comments
- commented-out dead code
- comments that explain what obvious code already says

### 6. Error Handling

Make failure paths explicit and comprehensible.

Prefer:
- meaningful error names/messages
- separating happy path from error path
- handling errors at the right level of abstraction
- consistent error strategy

Avoid:
- swallowed exceptions
- vague failure behavior
- mixing domain errors with transport/framework details
- returning magic values without explanation

### 7. Objects / Modules / Classes

Design around responsibility.

Prefer:
- cohesive modules
- narrow interfaces
- dependency inversion where it improves testability and coupling
- domain logic independent of delivery/storage concerns when practical

Avoid:
- god classes
- feature envy
- anemic naming hiding procedural blobs
- classes that are only buckets of unrelated methods
- deep knowledge chains across modules

### 8. Testing Posture

Refactors should move code toward easier testing.

Prefer designs that:
- isolate pure logic
- reduce need for broad mocks
- separate policy from mechanism
- make setup small
- allow focused unit tests

If tests exist:
- update them only as needed to preserve intent
- do not weaken meaningful coverage
- do not rewrite all tests unless necessary

If tests do not exist and the change is risky:
- strongly consider adding characterization tests first
- if you cannot add tests, explicitly state what behavior was preserved by inspection

## Refactoring Workflow

Follow this sequence:

1. **Read for intent**
   - Identify what the code appears to do.
   - Identify business rules vs plumbing.
   - Identify likely invariants and assumptions.

2. **Identify code smells**
   Common smells include:
   - long functions
   - long parameter lists
   - duplicated logic
   - unclear names
   - mixed abstraction levels
   - nested control flow
   - temporal coupling
   - inappropriate shared state
   - feature envy
   - god objects
   - data clumps
   - primitive obsession
   - comments compensating for bad structure

3. **Protect behavior**
   - Look for existing tests.
   - Add or preserve characterization coverage where possible.
   - Be conservative around ambiguous behavior.

4. **Refactor in small, coherent moves**
   Typical sequence:
   - rename for clarity
   - extract function
   - flatten conditionals
   - split responsibilities
   - reduce parameter count
   - isolate side effects
   - replace duplication with clear abstraction only when warranted
   - tighten module boundaries

5. **Re-read the result**
   Validate:
   - Is the main path obvious?
   - Do names tell the story?
   - Are responsibilities cleaner?
   - Is the code easier to test?
   - Did complexity actually go down?

## Default Refactoring Heuristics

Use these heuristics unless the repo has a stronger local convention:

- Prefer explicit domain types over loose maps/dicts/arrays when it improves clarity.
- Prefer composition over inheritance unless inheritance is already clearly justified.
- Prefer dependency injection over hidden globals/singletons where practical.
- Prefer immutable local transformations when that reduces confusion.
- Prefer one level of abstraction per function.
- Prefer constructors/factories that leave objects valid after creation.
- Prefer thin controllers/handlers and richer domain/application services where applicable.
- Prefer converting magic literals into named constants only when the name adds meaning.
- Prefer removing dead code rather than keeping it “just in case.”

## Output Requirements

For each refactoring task, produce:

### 1. Refactored code
Provide the updated code directly.

### 2. Refactoring summary
Briefly explain:
- what changed
- why it was changed
- what code smells were addressed
- what behavior was intentionally preserved

### 3. Clean-code assessment
Comment on the before/after in terms of:
- naming
- function size
- responsibilities
- duplication
- complexity
- testability

### 4. Risk notes
State any areas where:
- behavior was inferred rather than proven
- missing tests increase uncertainty
- architecture constraints limited cleanup
- a deeper redesign may still be warranted

## Constraints

- Do not do a full rewrite unless clearly necessary.
- Do not introduce large frameworks or patterns without need.
- Do not replace simple code with abstract pattern-heavy code.
- Do not over-engineer for hypothetical future requirements.
- Do not change public APIs unless the user asked for that or it is necessary and clearly explained.
- Do not silently change error semantics, persistence behavior, ordering, or side effects.
- Do not chase style-only changes if structural clarity is the real issue.

## Decision Rules

When faced with tradeoffs:

- Prefer readable duplication over a bad abstraction.
- Prefer a smaller honest function over a “generic” bloated one.
- Prefer explicit dependencies over hidden ones.
- Prefer simple objects with strong names over vague utility layers.
- Prefer direct code that reveals policy over indirect code that obscures it.
- Prefer consistency with the surrounding codebase when multiple clean options exist.
- Prefer the minimum structural change that meaningfully improves maintainability.

## Special Cases

### Legacy code
In legacy areas:
- make narrow, safe improvements
- preserve behavior carefully
- add characterization tests when feasible
- avoid ideological rewrites

### Framework-heavy code
When working inside frameworks:
- keep framework glue thin
- avoid leaking framework details into core domain logic when possible
- do not fight the framework unnecessarily

### Performance-sensitive paths
If a cleaner refactor may affect performance:
- preserve the performance characteristic unless the user asked otherwise
- state the tradeoff explicitly

## Success Criteria

The refactor is successful when a competent engineer can quickly answer:

- What does this code do?
- Where is each responsibility located?
- What are the important business rules?
- What can change safely?
- How would I test this?
- Where do side effects happen?
- What names should I trust?

## Style of Response

When executing this skill:
- be concrete, not vague
- point to exact smells and exact improvements
- justify structural changes in maintainability terms
- keep explanation disciplined
- avoid empty praise
- avoid saying code is “clean” without explaining why

## Example Invocation Phrases

Use this skill when the user says things like:

- “Refactor this mess.”
- “Make this code cleaner.”
- “Clean this up without changing behavior.”
- “This function is too big.”
- “Reduce duplication here.”
- “Make this more maintainable.”
- “Rewrite this in a Clean Code style.”
- “Refactor this the way Uncle Bob would.”

## Final Reminder

The target is not perfection.
The target is code that is easier to read, easier to trust, and easier to change.
When in doubt, choose the refactor that makes the code more honest.
