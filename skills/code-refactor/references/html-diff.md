# HTML Diff Output Guide

When the user requests an HTML diff as part of a refactor, follow all the rules below.

## Output Format

- The output must be a `.html` file.
- It must show side-by-side code diffs for before vs. after.
- It should be updated progressively during the refactor, not only after all code edits are done.
- It should remain consistent with the current state of the refactor if the task spans multiple edits.
- The HTML diff file may be written in the working directory or in `/tmp`.

## Per-Chunk Structure

- Do not present only the entire file before and the entire file after.
- Show each refactored chunk as its own side-by-side before/after snippet pair, rendered as a separate row in the HTML.
- Each snippet pair should appear on its own separate row in the HTML output.
- The left side should show the **complete original snippet** for that refactor step, and the right side should show the **complete updated snippet**.
- Add new snippet pairs as the refactor progresses so the HTML becomes a running log of the actual changes made.
- Keep each snippet focused on the specific extracted, renamed, reordered, or simplified code that changed. Do not pad snippets with large unchanged regions unless needed for comprehension.

## CRITICAL: No Placeholders or Abbreviations

**NEVER** use any of the following shortcuts in the HTML diff output:
- `// ...` or `// ... (rest of method)`
- `/* ... */` or `/* ... complex logic ... */`
- `// ... (imports)` or `// ... (constants)`
- `{ /* ... */ }` for method bodies
- `someObject.method(...)` or `$this->someMethod(...)` with literal `...` instead of real arguments
- Pseudocode stubs like `function foo(...) { /* ... */ }` or `protected function foo(...) { /* ... */ }`
- Comments like `// ... (other extracted methods)` in place of actual code

Every snippet — both left (original) and right (refactored) — must contain **real, complete, copy-pasteable code**. If a snippet is too long, narrow the scope of the chunk to a smaller, focused region. Never abbreviate code with ellipses or placeholders. The reader should be able to look at any snippet pair and see the exact before and exact after without guessing what was omitted.

## Entry Labels

- Treat the HTML diff as a sequence of refactor entries, not as a single whole-file comparison.
- Each entry should represent one meaningful refactor chunk.
- For every entry, include:
  - the old snippet (complete, real code — no ellipsis)
  - the new snippet (complete, real code — no ellipsis)
  - a short label describing the refactor, such as rename, extraction, guard clause cleanup, or constant introduction
- If several nearby lines changed for one reason, keep them in one snippet pair.
- If separate refactors happen in different areas, show them as separate snippet pairs rather than merging them into one large block.
- The diff must never degrade into a two-column layout where one side is full code and the other is pseudocode or placeholder stubs.

## Expected HTML Structure

Each refactor step should render as its own visual row. Use a structure like this:

```html
<!-- One row per refactor step -->
<div class="diff-step">
  <div class="step-label">Step 1: Extract guard clauses into validateContext()</div>
  <div class="diff-row">
    <div class="diff-col original">
      <div class="col-header">Before</div>
      <pre><code>
patient = recordedTime.patient;
if (!patient) {
    logger.error(`RecordedTime ${recordedTime.id} has no patient.`);
    return;
}

facility = patient.facility;
if (!facility) {
    logger.error(`Patient ${patient.id} has no facility.`);
    return;
}
      </code></pre>
    </div>
    <div class="diff-col refactored">
      <div class="col-header">After</div>
      <pre><code>
if (!this.validateContext(recordedTime, patient, facility, primaryPracticeId)) {
    return;
}

// --- extracted method ---
validateContext(recordedTime, patient, facility, primaryPracticeId) {
    if (!patient) {
        logger.error(`RecordedTime ${recordedTime.id} has no patient.`);
        return false;
    }
    if (!facility) {
        logger.error(`Patient ${patient.id} has no facility.`);
        return false;
    }
    if (!primaryPracticeId) {
        logger.error(`Patient ${patient.id} has no primary practice!`);
        return false;
    }
    return true;
}
      </code></pre>
    </div>
  </div>
</div>

<div class="diff-step">
  <div class="step-label">Step 2: Replace magic number with constant</div>
  <div class="diff-row">
    <!-- ... next real snippet pair ... -->
  </div>
</div>
```

Notice: the "After" column shows both the new call site AND the full extracted method body. No `{ /* ... */ }` stubs.
