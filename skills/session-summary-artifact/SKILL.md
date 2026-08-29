---
name: session-summary-artifact
description: >-
  Generates an end-to-end architectural flow and comprehensive walkthrough artifact summarizing the session's work.
  Use this skill whenever the user asks for a session summary, architecture breakdown, flow walkthrough,
  summary artifact, or post-session review explaining the big picture, request journey, root causes, and code changes in full context.
---

# End-to-End Architecture & Session Summary Generator

This skill guides the agent in creating an **architectural flow-first, contextual session summary artifact** that avoids showing isolated code changes in a vacuum and instead grounds everything in the complete end-to-end request and data lifecycle.

---

## Core Philosophy

Code diffs in isolation leave developers feeling lost. To provide true clarity, every summary artifact must:
1. **Map the Entire Landscape**: Show how the frontend, API, middleware, backend controllers, database, and background services connect.
2. **Trace the Real Data Journey**: Walk through each step a request takes from trigger to final output with concrete values.
3. **Pinpoint the Failure in the Chain**: Use visual flow maps to show exactly *where* in the pipeline the breakdown occurred and *why*.
4. **Contextualize the Fixes**: Show how the modified code restores the intended end-to-end flow.

---

## Artifact Structure & Mandatory Sections

When generating the artifact (e.g., `session_summary.md`), structure it with the following 5 sections:

### 1. The Big Picture: System Architecture
- **Architecture Overview**: Explain the high-level pattern (e.g. API-to-API dispatch, event-driven worker, client-server sync, request-response lifecycle).
- **Mermaid Flowchart**: Include a Mermaid diagram mapping the full system journey across subsystems:
  - Subsystem 1: Client / Frontend Trigger (UI, parameters, state).
  - Subsystem 2: Routing, Middleware & Ingestion (Auth, ID decoding, validation).
  - Subsystem 3: Core Processing & Sub-requests (Controllers, services, queries).
  - Subsystem 4: Data Layer & Output Generation (Database queries, formatting, files, mailers).

### 2. The Complete Request Journey (Step-by-Step)
Walk through the happy path from start to finish with concrete examples:
- **Step 1: Client Request**: Show the exact payload / URL / command sent.
- **Step 2: Middleware & Pre-processing**: What runs before the controller (e.g. ID decoding, auth checks, parameter normalization).
- **Step 3: Controller Entry**: How the request is ingested and dispatched.
- **Step 4: Filter / Parameter Translation**: How inputs are transformed for queries or sub-services.
- **Step 5: Query Execution & Data Fetching**: Exact SQL / Eloquent operations and constraints applied.
- **Step 6: Data Transformation & Document / View Building**: How raw data is formatted into the final payload, CSV, PDF, or view.
- **Step 7: Response / Delivery**: How the result is returned to the user or dispatched to recipients.

### 3. Where and Why the Flow Broke Down (Failure Maps)
For each bug or issue tackled during the session:
- **Visual ASCII / Mermaid Pipeline**: Trace the data entering the pipeline, where it diverged from expected behavior, and the exact failure point.
- **Root Cause Mechanics**: Explain the language or framework quirk that caused it (e.g. PHP falsy `"0"`, operator stripping, missing relations, unhandled edge conditions).
- **Impact**: Contrast what *actually* happened vs. what *should* have happened (with concrete row/data counts).

### 4. How the Flow Works Now (The Fixes)
For each fix:
- **File Link**: Include clickable links to the file and line range (e.g. `[BillingReportController.php (Lines 220–222)](file:///path/to/file.php#L220-L222)`).
- **Targeted Code Snippet**: Clean before/after code blocks.
- **Behavioral Shift**: Explain how this specific fix repairs the broken step in the pipeline.
- **Verification Evidence**: State the concrete test results or counts confirming the fix works.

### 5. Summary Checklist of All Files Changed
A concise markdown table summarizing every file touched across the session:
| File | What was changed | Context / Role in the Pipeline |
| :--- | :--- | :--- |
| `[Controller.php](file:///path/to/Controller.php#L10-L20)` | Brief description | Explain how this file fits into the broader architecture |

---

## Execution Workflow for the Agent

1. **Investigate the Full System Context**:
   - Inspect all touched files and their callers/callees across the frontend and backend.
   - Trace the entire request lifecycle from trigger to output.

2. **Draft the Artifact**:
   - Write the file to `<appDataDir>/brain/<conversation-id>/session_summary.md`.
   - Set `UserFacing = true` and `RequestFeedback = false`.
   - Use standard GitHub Flavored Markdown with Mermaid diagrams and clickable file links.

3. **Deliver to User**:
   - Notify the user with a direct clickable link to the generated artifact.
   - Highlight the key takeaways concisely in chat without repeating the whole document.
