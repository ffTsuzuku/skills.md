---
name: code-porter
description: Port code from one codebase or language/framework to another. Use this skill whenever the user says things like "port this code", "let's begin a porting session", "migrate this to [framework]", "copy this logic over to my [X] repo", or "I need this Firebase/Node/Python/etc. code in my Laravel/Go/Rails/etc. project". Also use it when the user points to a file and says something like "this needs to go into my other repo" or "translate this to [language]". Always use this skill when any cross-repo or cross-stack code transfer is implied, even if the word "port" is never used.
---

# Code Porter

Port code from one codebase or framework to another. Work in small, visible steps — write something after every item, never go dark.

**The cardinal rule: output before analysis. Write a draft, show it, refine it. Don't build the perfect port in your head first. A rough file on disk is worth more than ten minutes of silent thinking.**

---

## 0. Session Setup

You need:
- **Source path** — file or directory to port from
- **Target path** — where ported code should land (ask if not given)
- **Stack context** — source and target language/framework (infer if obvious)

Confirm with the user in one sentence, then start immediately.

---

## 1. Quick Scan (5 minutes max)

Read the entry-point file. Skim — don't deep-read yet. Look for:
- The list of functions/classes/exports
- Direct local imports (one level only)
- Any obvious environment-specific items (cloud triggers, platform hooks)

Tell the user what you found in a short bullet list. Example:

> "Found 6 items across 2 files: `createUser`, `validateInput`, `sendWelcome` in `users.js`; and `onUserCreate` (Firebase trigger) in `index.js`. Starting to port now."

If you hit more than 10 local files during this scan, stop and ask the user to narrow the scope before continuing.

**Go back and deep-read individual files only when you're actively porting that item — not all upfront.**

---

## 2. Port One Item at a Time

For each item, in order:

### 2a. Write it now

Write the ported file (or add to an existing file) immediately. Don't wait until you've analyzed everything. A draft is fine. Use `// TODO:` comments for anything you're uncertain about — you can resolve them after the user sees the file.

### 2b. Categorize it

Before or as you write, decide:

| Status | Meaning |
|---|---|
| ✅ **Ported** | Translated cleanly |
| ✅ **Ported with refactor** | Restructured (split function, convert to class, etc.) |
| 🔄 **Replaced** | Target framework handles this natively — no code needed |
| ⏭️ **Skipped** | Dead code, environment-specific, or out of scope — always explain why |
| ❓ **Blocked** | You need input from the user before proceeding |

### 2c. Refactoring: when to just do it vs. when to ask

- **Just do it** if it's a well-known, low-risk transformation: split a long function, convert callbacks to async/await, convert a script to a class, replace a library with a native equivalent. Document your decision in the mapping file.
- **Ask first** if it changes data flow, alters a public API, or merges with existing target code in a non-obvious way.

### 2d. Add a row to PORTING_MAP.md

After writing each item, immediately append a row to the mapping table. Don't save the mapping file for the end.

### 2e. Tell the user

After each item (or every 3 items if they're trivial), say something like:

> "Ported `createUser` → `app/Services/UserService.php`. Converted the flat function to a class method and swapped Firebase Admin SDK calls for Eloquent. Next: `validateInput`."

If you're blocked on something, say so immediately — don't keep working around it silently.

---

## 3. Mapping File (PORTING_MAP.md)

Create this file at the start of the session with an empty table. Add one row per item as you go. Only fill in the Summary section at the very end.

This is the format:

```markdown
# Porting Map

**Source**: `<path>`
**Target**: `<path>`
**Date**: <date>
**From**: <e.g., Node.js + Firebase>
**To**: <e.g., PHP + Laravel>

## Summary
<Fill in at the end — 1–2 sentences on what was ported and key decisions.>

## Item Map

| Item | Source | Target | Status | Notes |
|------|--------|--------|--------|-------|
| `createUser` | `src/users.js:14` | `app/Services/UserService.php` | ✅ Ported with refactor | Converted to class; split auth + persistence |
| `onUserCreate` | `functions/index.js:44` | — | ⏭️ Skipped | Firebase Auth trigger — environment-specific. Laravel equivalent would be a model observer, but not in scope |
| `verifyToken` | `src/middleware.js:5` | — | 🔄 Replaced | `auth:sanctum` middleware handles this natively |

## Refactoring Decisions
- **`processOrder` → `OrderService` class**: Split 200-line function into `validate()`, `save()`, `notify()` for readability.

## Dependencies Introduced
| Package | Reason |
|---|---|
| `guzzlehttp/guzzle` | Replaces `axios` for HTTP requests |

## Open Questions
- [ ] `sendGrid.send()` has no equivalent yet — configure a mail driver in `.env`.
```

---

## 4. Wrap Up

When all items are handled:
1. Fill in the `## Summary` section of `PORTING_MAP.md`
2. Tell the user it's done and where the mapped file lives
3. Call out any `// TODO:` comments left in ported files and any open questions

---

## Quick Reference: Common Translations

### Firebase → Laravel
| Source | Target |
|---|---|
| Firebase Auth | Laravel Sanctum or Fortify |
| Firestore | Eloquent + MySQL/PostgreSQL |
| Cloud Function (HTTP) | Controller + route |
| Cloud Function (trigger) | Observer, Job, or scheduled command |
| Firebase Storage | Laravel Storage (S3, local, GCS) |
| `functions.config()` | `.env` + `config/` |

### Node/Express → Laravel
| Source | Target |
|---|---|
| `express` router | `routes/web.php` / `routes/api.php` |
| Express middleware | Laravel middleware class |
| `async/await` | Synchronous code or Jobs/Queues |
| `process.env.X` | `env('X')` |

### Node → Go
| Source | Target |
|---|---|
| Promises/callbacks | Goroutines + channels |
| Dynamic JSON | `encoding/json` + typed structs |
| `express` | `chi` or `gin` |
| `null/undefined` | Pointer types or zero values |

### Always
- Match target error handling (exceptions in PHP/Ruby, error returns in Go)
- Declare types explicitly when going dynamic → static
- Use `// TODO:` for uncertain spots rather than stalling
