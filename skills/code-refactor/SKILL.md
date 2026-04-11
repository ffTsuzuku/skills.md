---
name: code-refactor
description: Refactors code to improve readability, apply modern conventions, reduce complexity, and produce clearer names without changing business logic. Language-agnostic core; PHP/Laravel-specific guidance is loaded from a reference file when relevant. Use this skill whenever the user asks to refactor, clean up, or improve code in any language.
---

# Code Refactoring Skill

You are an expert developer. Refactor the provided code to improve clarity and structure while preserving behavior exactly.

When refactoring PHP or Laravel code, also read [PHP & Laravel Reference](references/php-laravel.md) for framework-specific guidance. If that file is unavailable, note it in `refactor_plan.md` and continue using the core mandates below.

When producing an HTML diff, read [HTML Diff Guide](references/html-diff.md) for formatting requirements. If that file is unavailable, note it and produce a best-effort unified diff instead.

---

## When Not to Refactor

Before proceeding, consider whether the code actually needs changes. Skip refactoring — and say so — when:

- The code is already well-named, cohesive, and readable.
- The complexity is intentional and documented (e.g., a deliberate performance optimization with a comment explaining the tradeoff).
- A safe refactor would require domain knowledge or production data that is not visible in the code and the user has not provided it.
- The rename or extraction would make behavior *less* obvious, not more.

Refactoring to justify output is worse than leaving good code alone.

---

---
## Passthrough Refinement (Iterative Refactoring)

When a user requests multiple "passthroughs" or "passes" (e.g., "Do 5 passthroughs"), they are asking for **complete, sequential iterations of the entire refactoring process**, not steps within a single refactor. Each passthrough is a full start-to-finish refactoring effort that improves upon the result of the previous pass.

**CRITICAL RULES FOR PASSTHROUGHS:**
- **Do NOT treat a passthrough as a step** (e.g., do not plan "Passthrough 1: constants, Passthrough 2: extracts"). A single pass should include all necessary refactoring steps.
- **Do NOT plan multiple passes ahead of time** in your `refactor_plan.md`. Your initial plan must only cover Pass 1. 

For each requested pass (up to a maximum of 5):
1. **Plan:** Write or update `refactor_plan.md` for the *current* pass only.
2. **Execute:** Refactor the code completely based on the current plan. Update the diff file as yoy go along if one was requested.
3. **Review:** Re-read the fully refactored output.
4. **Refine (Next Pass):** Identify deeper improvements, missed patterns, or consistency gaps that are only visible now that the code is cleaner. 
5. **Log:** Provide a brief summary to the user of what was improved in this specific pass.
6. **Stop Early:** If a review yields no meaningful improvements, stop the passes early and inform the user.

---

## Core Mandates

### Behavior Preservation

1. **Logical Equivalence:** NEVER change business logic. The refactored code MUST be logically equivalent to the input code. All existing tests should still pass. If a test runner is available, run tests after refactoring and report results. If not, note that tests could not be verified automatically.

2. **Plan First, Sync Last:** Before modifying code, create a `refactor_plan.md` file describing the intended refactor — methods to extract, identifiers to rename, constants to introduce, risky areas, and any assumptions already identified. After the refactor is complete, return to `refactor_plan.md` and update it so all method names, constants, and structural decisions match the actual output exactly. The plan must stay synchronized with the final code.

3. **Document Assumptions:** If any assumption is made during the refactor, record it clearly in `refactor_plan.md` and mention it again in the final response. Do not hide assumptions.

4. **Surface Data Questions:** If understanding the correct refactor depends on production or domain data that is not visible in code, explicitly ask the user what to check and explain why that context matters. Prefer asking for precise missing context over guessing when naming or behavior depends on domain data.

---

### Naming

5. **Naming Quality:** Rename unclear or misleading identifiers. Names must be intention-revealing, pronounceable, and searchable. Use lengths proportional to scope: short scope can use short names; broader scope should use more explicit names. Avoid encoded names, misleading names, vague names, disingenuous abbreviations, and names that need comments to explain them.

6. **Naming Semantics:** Variables are typically nouns or noun phrases. Methods should use verbs or verb phrases that describe the action and outcome. Avoid names that merely say something is "handled," "processed," "managed," or "data" unless that is genuinely the most precise description.

7. **Prefer Specific Intent:** If a method name hides what it actually does, rename it to reflect its real behavior. For example:
   - `processBillCode` is too vague unless the method truly means generic processing.
   - `getCreatedAt` is wrong if it sometimes returns a different timestamp.
   - `ensureRecordExists` is wrong if the method queries for an existing record, creates one when missing, and returns the entity — that is a `findOrCreateRecord`. Reserve `ensure*` for void methods that throw or abort on failure. If the method returns the entity, use `findOrCreate*` or `resolve*`.

8. **Side Effects Must Be Named:** If a method mutates state, persists data, increments counters, updates timestamps, or changes business-critical fields, the method name must make that side effect clear. Prefer verbs like `record...`, `update...`, `attach...`, `mark...`, `sync...`, or `recalculate...` when those match the real effect better than `process...`. Do not use names that make a mutation-heavy method sound harmless or read-only.

9. **Preserve Domain Meaning:** Do not rename identifiers in a way that collapses important domain distinctions. Keep IDs, codes, foreign keys, timestamps, statuses, and display values clearly separated in names. If the source code or comments establish that a value is an ID rather than a code, the refactor must preserve that distinction. When extracting constants, preserve the meaning of the original value — do not rename an internal ID to look like a business code just because the numeric values happen to match.

10. **Preserve Domain Rationale:** When renaming a variable or extracting a method removes the context that a comment previously explained, relocate that rationale into a docblock on the new method or a comment at the new call site. Do not silently delete comments that explain *why* the code makes a domain-specific choice. The *what* can be expressed through naming; the *why* still needs prose.

---

### Structure

11. **Smaller Units:** Break up long or mixed-responsibility methods into smaller, cohesive helpers when it improves readability.

12. **Magic Numbers and Strings:** Replace hardcoded magic numbers and status strings with named constants, class constants, enums, or well-named variables when doing so improves clarity. Status strings (e.g., `'resolved'`, `'pending'`, `'ready'`) should be extracted so they are searchable, refactorable, and typo-proof.

13. **Signature Clarity:** Method signatures should stay readable and conventional. Optional or nullable parameters should not be placed before required parameters unless there is a strong existing API constraint.

---

### Return Shapes

14. **Return Shape Clarity:** Do not make a method named like a validator, checker, or guard clause return bundled context data. If a method validates, it should return a validation-oriented result. If a method gathers context, its name and return shape should say that explicitly. If a method both validates and assembles data, split those responsibilities or rename so both behaviors are obvious.

15. **Avoid Opaque Bundles:** Do not hide meaningful structure inside anonymous associative arrays, dictionaries, or tuples when a clearer object, typed structure, or explicit local variables would be easier to read. Returned data shapes must be obvious at the call site. Prefer either separate method calls or a clearly named data structure with explicit fields.

---

### Diagnostics and Safety

16. **Preserve Diagnostic Logging:** Do not delete `Log::info`, `Log::error`, or equivalent diagnostic telemetry statements. Retain exact string formatting and all interpolated context variables (dates, IDs, periods). Production log parsers and alerts often depend on these exact structural formats.

17. **Latent Error Consistency:** If you patch a latent crash or unhandled null condition with null-safe operators or null-checks, apply them consistently to all downstream usages — including logging — to avoid shifting the fatal error to the next line.

18. **Avoid Fragile Back-Calculations:** Do not reconstruct a "before" value by reverse-engineering it from an already-mutated "after" value. Capture the value explicitly *before* the mutation. Back-calculations are brittle and break silently if the mutation logic changes.

---

### Output and Conventions

19. **Modern Conventions:** Use modern language and framework conventions only when the project clearly supports them. Confirm support by checking explicit version signals — a `composer.json` `require.php` field, a `package.json` `engines` field, a `.nvmrc`, a target framework version in a project file, or the syntax style of the existing codebase. Do not assume the newest syntax is safe if no version signal is present.

20. **Output Location Flexibility:** Refactored code and supporting artifacts may be written in place or to `/tmp`, depending on the user's request or what is safer for the task. When not specified, in-place replacement is the default. Mention the chosen path clearly in the final response.

21. **Respect Requested Write Mode:** If the user wants a non-destructive output, write the refactored file to `/tmp` or another clearly stated path instead of overwriting the source. If the user wants the file changed directly, replace it in place.

---

## Workflow

1. **Analyze:** Read the provided file(s) and understand the business logic fully before changing structure or names. Determine whether a refactor is actually warranted (see *When Not to Refactor* above).
2. **Plan:** Write `refactor_plan.md`. Be specific about methods to extract, identifiers to rename, constants to introduce, risky areas where logic must be preserved carefully, and any assumptions already identified.
3. **Request Missing Context:** If a safe refactor depends on data state, historical data shape, or operational behavior that cannot be inferred from code, pause and ask the user for the exact lookups or examples needed.
4. **Choose the Write Target:** Decide whether to overwrite the source file or write to `/tmp` based on the user's request.
5. **Execute:** Apply the refactor to the chosen output path.
6. **Verify:** If a test runner is available, run tests and report results. If not, note that tests could not be verified automatically.
7. **Sync Plan:** Return to `refactor_plan.md` and update it so all method names, constants, and structural decisions match the final output exactly.

---

## Naming Quick Reference

| Situation | Guidance |
|---|---|
| Method returns found-or-created entity | `findOrCreate*` or `resolve*`, not `ensure*` |
| Method is void and throws on failure | `ensure*` is appropriate |
| Method mutates or persists | Use `record*`, `update*`, `attach*`, `mark*`, `sync*`, `recalculate*` |
| Method is vague (`process*`, `handle*`) | Rename to reflect the dominant action |
| Variable holds an ID | Name must include `id`, not `code` or `key` |
| Status string is hardcoded | Extract to a constant or enum |
| Comment explains *why* | Preserve it — move it to the new location if needed |
