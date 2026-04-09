---
name: code-refactor
description: Refactors code to improve readability, apply modern conventions, reduce complexity, and produce clearer names without changing business logic. Use this skill whenever the user asks to refactor, clean up, or improve code in any language.
---

# Code Refactoring Skill

You are an expert developer. Refactor the provided code to improve clarity and structure while preserving behavior exactly.

When refactoring PHP or Laravel code, also read [PHP & Laravel Reference](references/php-laravel.md) for framework-specific guidance.

## Core Mandates

1. **Logical Equivalence:** NEVER change business logic. The refactored code MUST be logically equivalent to the input code. All tests (if any) should still pass.
2. **Plan First:** Before modifying code, create a `refactor_plan.md` file describing the intended refactor. After the refactor is complete, update `refactor_plan.md` so that the method names, constants, and structural decisions in the plan match the actual output. The plan must stay synchronized with the final code.
3. **Smaller Units:** Break up long or mixed-responsibility methods into smaller, cohesive helpers when it improves readability.
4. **Naming Quality:** Rename unclear or misleading identifiers. Names must be intention-revealing, pronounceable, and searchable. Use lengths proportional to scope: short scope can use short names; broader scope should use more explicit names.
5. **Naming Semantics:** Variables are typically nouns or noun phrases. Methods should use verbs or verb phrases that describe the action and outcome. Avoid encoded names, misleading names, vague names, disingenuous abbreviations, and names that need comments to explain them.
6. **Prefer Specific Intent:** If a method name hides what it actually does, rename it to reflect its real behavior. For example, a name like `processBillCode` is too vague unless the method truly means generic processing. A name like `getCreatedAt` is wrong if it sometimes returns a different timestamp. A name like `ensureRecordExists` is wrong if the method actually queries for an existing record, creates one when missing, and returns the entity — that is a `findOrCreateRecord`, not an assertion or guard clause. Reserve `ensure*` for void methods that throw or abort on failure. If the method returns the entity, use `findOrCreate*` or `resolve*`.
7. **Preserve Domain Meaning:** Do not rename identifiers in a way that collapses important domain distinctions. Keep IDs, codes, foreign keys, timestamps, statuses, and display values clearly separated in names. If the source code or comments establish that a value is an ID rather than a code, the refactor must preserve that distinction.
8. **Magic Numbers and Strings:** Replace hardcoded magic numbers and status strings with named constants, class constants, enums, or well-named variables when doing so improves clarity. Status strings (e.g., `'resolved'`, `'pending'`, `'ready'`) should be extracted into constants or enums so they are searchable, refactorable, and typo-proof.
9. **Document Assumptions:** If any assumption is made during the refactor, record it clearly in `refactor_plan.md` and mention it again in the final response. Do not hide assumptions.
10. **Surface Data Questions:** If understanding the correct refactor depends on production or domain data that is not visible in code, explicitly ask the user what to check and explain why that context matters.
11. **Signature Clarity:** Method signatures should stay readable and conventional. Optional or nullable parameters should not be placed before later required parameters unless there is a strong existing API constraint.
12. **Return Shape Clarity:** Do not make a method named like a validator, checker, or guard clause return bundled context data. If a method validates, it should read like validation and return a validation-oriented result. If a method gathers context, its name and return shape should say that explicitly.
13. **Avoid Opaque Bundles:** Do not hide meaningful structure inside shorthand bundling (e.g., anonymous associative arrays, dictionaries, tuples, or equivalent) when a clearer object, typed structure, or explicit local variables would be easier to read. Returned data shapes must be obvious at the call site. For example, a method returning `{'codes': ..., 'targets': ...}` forces the caller to remember hidden keys. Prefer either separate method calls or a clearly named data structure.
14. **Side Effects Must Be Named:** If a method mutates state, persists data, increments counters, updates timestamps, or changes business-critical fields, the method name must make that side effect clear. Do not use vague names like `process...` when the method actually records, updates, attaches, persists, marks, or recalculates something specific.
15. **Modern Conventions:** Use modern language and framework conventions only when the project clearly supports them. Check language version, framework version, and existing code style before introducing newer syntax.
16. **Output Location Flexibility:** Supporting artifacts and the refactored code itself may be written either in place or to a temporary directory such as `/tmp`, depending on the user's request or what is safer for the task. Mention the chosen path clearly in the final response.
17. **Respect Requested Write Mode:** If the user wants a non-destructive refactor output, write the refactored file to `/tmp` or another clearly stated output path instead of overwriting the source file. If the user wants the file changed directly, replace it in place.
18. **Preserve Domain Rationale:** When renaming a variable or extracting a method removes the context that a comment previously explained, relocate that rationale into a docblock on the new method or a comment at the new call site. Do not silently delete comments that explain _why_ the code makes a domain-specific choice. The _what_ can be expressed through naming; the _why_ still needs prose.
19. **Avoid Fragile Back-Calculations:** Do not reconstruct a "before" value by reverse-engineering it from an already-mutated "after" value. Capture the value explicitly _before_ the mutation. Back-calculations are brittle, harder to read, and break silently if the mutation logic changes.
20. **Preserve Diagnostic Logging:** Do not delete `Log::info`, `Log::error`, or similar diagnostic telemetry statements during a refactor. Retain exact string formatting and all interpolated context variables (dates, IDs, periods). Production log parsers and alerts often depend on these exact structural formats.
21. **Latent Error Consistency:** If you identify a latent crash or unhandled null condition in the original code, and choose to patch it with null-safe operators (`?->`) or null-checks, ensure you apply them consistently to all downstream usages (including logging) to avoid simply shifting the fatal error to the next line.

## Workflow

1. **Analyze:** Read the provided file(s) and understand the business logic before changing structure or names.
2. **Plan:** Write `refactor_plan.md`. Be specific about methods to extract, identifiers to rename, constants to introduce, risky areas where logic must be preserved carefully, and any assumptions already identified. This file may be created in the current working directory or in `/tmp`.
3. **Request Missing Context When Needed:** If a safe refactor depends on data state, historical data shape, or operational behavior that cannot be inferred from code, pause and ask the user for the exact lookups or examples needed.
4. **Choose the Write Target:** Decide whether to overwrite the source file or write the refactored result to `/tmp` based on the user's request. When not specified, in-place replacement is the default.
5. **Execute:** Apply the refactor to the chosen output path.
6. **HTML Diff (if requested):** If the user asks for an HTML diff, see [HTML Diff Guide](references/html-diff.md) for formatting requirements.

## Naming Guidance

- Prefer names that tell the reader why the code exists and what result it produces.
- Rename methods when the current name overstates, understates, or misstates the behavior.
- Do not preserve a bad name just because it already exists.
- Avoid names that merely say something is "handled", "processed", "managed", or "data" unless that is genuinely the most precise description.
- When extracting constants, preserve the meaning of the original value. Do not rename an internal ID to look like a business code just because the numeric values happen to match.
- Read nearby comments carefully before renaming. If a comment says a value is an ID and not a code, the refactor should keep that distinction visible in the new name.
- If a method both validates and assembles data, split those responsibilities or rename the method so both behaviors are obvious.
- If a method updates state, prefer names like `record...`, `update...`, `attach...`, `mark...`, `sync...`, or `recalculate...` when those verbs match the real effect better than `process...`.
- Avoid names that make a mutation-heavy method sound harmless or read-only.
- Do not use `ensure*Exists` for methods that return the found-or-created entity. `ensure` implies a void assertion or a boolean guard. If it returns the entity, name it `findOrCreate*` or `resolve*`. If it truly only asserts existence and throws on failure, `ensure` is fine.
- When a method does significantly more than its name suggests (e.g., a method called `updateStatus` that also calculates thresholds, sets dates, attaches enrollment data, and saves), either rename to reflect the dominant action or split into focused helpers.

## Collaboration Expectations

- If the refactor depends on an assumption, state it plainly.
- If the code suggests a likely data-driven rule but the source of truth appears to be in a database or external system, tell the user exactly what lookups or examples would help confirm the refactor.
- Prefer asking for precise missing context over guessing when the naming or behavior depends on domain data.

## Method Design Guardrails

- Do not introduce a `validate...` method that returns loaded models or miscellaneous context unless the method name clearly says it resolves or loads context.
- If multiple values need to travel together, prefer one of these:
  - keep them as explicit locals in the calling method
  - extract a clearly named context object or typed structure with explicit fields
  - split validation from context loading
- Keep nullable or optional parameters at the end of the parameter list when possible.
- If a method has multiple important side effects, reflect the main side effect in the method name and mention notable secondary effects in the plan or final summary.
