---
name: partition-driven-testing
description: Design or review a focused test suite using input partitions and boundary cases. Use when choosing test cases, fixing a regression, or reducing redundant tests; complements an existing implementation or TDD workflow.
---

# Partition-driven testing

Draft for use alongside pstack. This file does not install itself, authorize repository changes, or replace the project's existing workflow.

## Source principle

Inspired by [MIT 6.005, Spring 2016, Reading 3: Testing](https://ocw.mit.edu/ans7870/6/6.005/s16/classes/03-testing/), especially its sections on partitioning and boundaries. This is an original operational adaptation, not an MIT or pstack skill.

Start from the behavior contract. Divide the input space into meaningful classes whose members are expected to behave similarly. Identify dimensions separately, including relationships between inputs. Select representative cases, making boundaries explicit: empty collections, endpoints, numeric limits, and values at a change in behavior. Document the strategy and what each case covers.

Choose deliberately between covering each class and covering legal combinations. Exclude impossible combinations. More exhaustive combinations cost more; a small suite can cover several dimensions per case. Inspect implementation and coverage afterward for additional risks, without treating incidental implementation behavior as required behavior.

## Apply it to the task

1. **Establish the oracle.** Record the required result and its source: an acceptance criterion, documented contract, independently calculated example, or agreed invariant. If required behavior is ambiguous, identify the ambiguity rather than approving whatever the implementation returns.
2. **Derive cases from the specification.** Build a compact case ledger from the contract before using existing tests or implementation details to choose cases. For a small change, a few bullets are sufficient. For interacting inputs, use the table below. Name dimensions only when they could change the expected result or reveal a plausible defect.
3. **Inventory existing protection.** Read nearby tests, fixtures, test commands, and local instructions. Map current tests onto the ledger before adding more. Reuse coverage, but do not let existing tests define the contract.
4. **Choose the missing cases.** Prefer an existing test extension or parameterized case when it preserves readable failures. Each new case needs a distinct requirement, boundary, interaction, or regression rationale.
5. **Check implementation-specific gaps.** After the specification-first pass, inspect the implementation and available coverage evidence. Look for meaningful untested paths or domains, such as algorithm switches, caches and repeated inputs, or fast paths. Add a case only when it addresses a plausible defect; assert contract-required behavior, not incidental implementation choices. In a planning-only task, record unavailable coverage rather than running checks outside scope.
6. **Verify within scope.** If asked only to plan, stop at the ledger. If implementation is authorized, use the existing framework and run the relevant checks in the approved test environment. Revisit the gap check using any new coverage evidence. When it helps future maintenance and fits local conventions, retain concise strategy and case-coverage notes alongside the tests.

## Case ledger

| Case | Input/state and covered classes | Expected observable result | Independent basis | Existing test or gap | Defect it targets |
|---|---|---|---|---|---|
| Use concrete values | Include relevant history/state | Output, error, or externally visible effect | Requirement or calculation | File/test reference | A plausible wrong implementation |

For each dimension, make classes non-overlapping and collectively cover its intended domain. Use cross-cutting labels as annotations rather than claiming they form another exhaustive partition. Mark omitted dimensions or combinations with a reason when material.

## Visual partition coverage

Produce a readable partition table with stable case IDs (T1, T2, ...) matching the case ledger. Show the proposed design first; distinguish planned coverage from executed results. A case label in a cell means the case exercises it, not that the test passed or proves correctness.

For one dimension, show one row per class and list its representative values and case IDs. For two interacting dimensions, prefer a matrix: rows are one dimension's classes, columns are the other's, and cells contain covering case IDs.

For three or more dimensions, do not print an unreadable Cartesian cube. Start with a dimension/class coverage table; add small pairwise matrices or fixed-value slices for important interactions, plus a list of explicitly selected higher-order combinations. Name the dimensions fixed by each slice. Clearly state whether the strategy covers each class, selected interactions, pairwise combinations, or the full legal product. A pairwise table must not imply coverage of all higher-order combinations.

Use these cell labels:
- **T1, T4**: linked cases exercise this class or combination; identify planned versus existing cases in the ledger.
- **UNCOVERED**: a feasible class or combination in the stated coverage scope has no case. Say whether to add one or why the gap is deliberately accepted.
- **IMPOSSIBLE**: the contract or domain constraints make the combination infeasible; give a brief reason. Do not invent a test for it.
- **OUT OF SCOPE**: not selected under the stated strategy; explain material exclusions. Do not call such cells covered.
- **UNKNOWN**: feasibility or coverage cannot yet be established; record the missing fact rather than guessing.

Check that every listed case ID exists in the ledger, its inputs actually belong to its labeled classes, and all required feasible classes or combinations are accounted for. Report test execution separately as passed, failed, or not run.

Example for the range contract below (all cases initially planned):

| Quantity class | Representative | Covering case | Expected result |
|---|---:|---|---|
| Below 2 | 1 | T1 | Validation failure |
| Exactly 2 | 2 | T2 | Accept and preserve 2 |
| Between 2 and 8 | 5 | T3 | Accept and preserve 5 |
| Exactly 8 | 8 | T4 | Accept and preserve 8 |
| Above 8 | 9 | T5 | Validation failure |

A hypothetical two-dimensional fragment for non-null collections makes gaps and constraints visible. Contract: an empty collection has no first item; a nonempty collection has a present first item. This fragment is illustrative, not a recommendation to test impossible states.

| Collection size / first-item state | Absent | Present |
|---|---|---|
| Empty | T6 | IMPOSSIBLE: no item exists |
| Nonempty | IMPOSSIBLE: first item exists | UNCOVERED: add a representative |

Here T6 would need its own concrete input and oracle in the task's ledger. Do not copy illustrative case IDs into a real report without defining them.

## Practical guardrails against test inflation

- Do not add ten arbitrary values that exercise the same risk merely to increase test count.
- Do not impose a fixed test-count cap. A genuinely different interaction can justify another case.
- Cover known high-risk interactions explicitly. Pairwise sampling can be a useful compromise for many dimensions, but it does not establish coverage of three-way or higher interactions. Use a full legal product when it is small and meaningful.
- Make each boundary explicit. Choose values on relevant sides using the domain's actual precision, but do not mechanically require three new cases at every threshold. For an inclusive bounded range, start with each endpoint, a just-outside value at each end, and an interior representative. Add inside-adjacent values when they target a distinct risk, such as rounding or a special near-edge branch. Other contracts may need a different selection.
- For stateful behavior, consider relevant sequences, not only isolated inputs.
- Invalid inputs require a specified response or an explicit robustness goal. Do not invent validation guarantees for inputs excluded by the contract.
- Expected values must not be obtained by calling the production function, duplicating its algorithm, or blindly accepting its current snapshot. Mocks should supply dependencies, not manufacture the result being asserted.
- Assert no more than the contract promises: do not pin an exact error message, exception subtype, unordered-result order, or formatting unless it is required. For an unspecified validation-error representation, verify rejection as a validation failure using the public interface, not the current incidental wording.
- Assert enough to distinguish the intended result from a plausible wrong one. Non-null, type-only, length-only, no-throw, or mock-call-only checks are insufficient when the contract also specifies content or effects. Such checks are appropriate when they fully express the particular requirement; combine them with stronger assertions when needed.
- For an authorized bug fix, demonstrate that the regression test fails for the intended reason before the fix and passes afterward when feasible. Otherwise report the limitation. A deliberately wrong implementation can help assess a test, but use an isolated copy rather than destructive edits to working code.
- Coverage is diagnostic evidence, not evidence that assertions are correct. Report the exact commands run and distinguish passed, failed, and not run.
- Preserve meaningful regression history. Propose removal of apparently redundant tests with evidence; do not delete them merely because another case shares a label.

## Small worked example

Hypothetical contract: an integer quantity is accepted only from 2 through 8 inclusive. Rejection returns a validation error; acceptance preserves the quantity.

Use classes below 2, exactly 2, between 2 and 8, exactly 8, and above 8. Representatives 1, 2, 5, 8, and 9 cover these classes. Expected results come from the contract: 1 and 9 produce a validation failure; 2, 5, and 8 are accepted and retain their exact input value. Do not assert error wording that the contract does not specify.

These cases detect common inclusive/exclusive and missing-bound mistakes, not every possible bug. Do not automatically add 3, 4, 6, and 7 without another risk. Inside-adjacent values 3 and 7 are useful if a near-edge branch or numeric transformation could behave differently. Consider fixed-width numeric extremes when overflow or representation is relevant; do not add them by rote. If the API also promises to reject fractional quantities, add that as a separate class; otherwise flag the missing contract instead of inventing it.

## Handoff to an existing workflow

Use this skill during test design, before writing a batch of tests. Return the contract, concise case ledger, visual partition coverage table, existing coverage, selected gaps, and unresolved questions. Feed selected cases into the project's current TDD or implementation process. Do not create a second test framework or assume a particular pstack command exists.

Example request: "Use this skill to review tests for this change. Show partitions and boundaries, reuse existing coverage, and propose only cases with a distinct purpose. Do not edit files yet."
