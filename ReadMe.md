# Agent CLI Skills

Some personal agent skills I'm developing using the open source standard set by Anthropic. [Link](https://www.agentskills.io)

## Available Skills

### [Guided Dev Ticket (`ticket`)](./skills/ticket/SKILL.md)
Transforms high-level feature requests, user stories, or bug reports into structured, educational, self-guided developer tickets. Designed to counter AI coding dependency and help developers navigate unfamiliar or vibe-coded codebases by providing domain glossaries, architectural code maps, repo-idiomatic code patterns, stepped milestone worksheets, progressive 3-tier hints (`<details>` tags), hidden trap warnings, and testing recipes.
- **Use when:** You want to code a feature/bug yourself, need a guided implementation breakdown with hints, or want to learn the architecture of a repo without having the AI write the code directly.

### [Code Porter](./skills/code-porter/SKILL.md)
Port code from one codebase, language, or framework to another. It focuses on a high-velocity "output before analysis" workflow, providing a `PORTING_MAP.md` to track changes, refactors, and skipped items.
- **Use when:** Migrating services (e.g., Firebase to Laravel, Node to Go), translating logic between stacks, or moving code between repositories.

### [Code Refactor](./skills/code-refactor/SKILL.MD)
Expert-level code refactoring focused on readability, modern conventions, and structural clarity while strictly preserving business logic. It follows a "Plan -> Execute -> Validate" workflow.
- **Use when:** Cleaning up technical debt, improving naming, breaking down complex methods, or modernizing an existing codebase.

### [Clean Code](./skills/clean-code/SKILL.md)
Refactors code toward small, intention-revealing, testable, low-coupling design in the style of Robert C. Martin's Clean Code principles.
- **Use when:** Simplifying convoluted logic, eliminating code smells, isolating side effects, and improving testability.

### [Laravel Testing](./skills/laravel-testing/SKILL.md)
Generates comprehensive, structurally correct PHPUnit test suites for Laravel service classes, models, jobs, controllers, and custom logic.
- **Use when:** Writing test coverage, diagnosing untested branches, or building robust test suites in Laravel projects.

### [Partition-driven Testing](./skills/partition-driven-testing/SKILL.md)
Designs focused test suites from input partitions and boundary cases, with a case ledger and visual coverage tables. Complements existing testing and TDD workflows without adding redundant tests.
- **Use when:** Choosing test cases, reviewing coverage, fixing regressions, or reducing redundant tests.

### [Session Summary Artifact](./skills/session-summary-artifact/SKILL.md)
Generates an end-to-end architectural flow and comprehensive walkthrough artifact summarizing the session's work, request journey, and code changes.
- **Use when:** Generating high-context handover artifacts and post-session architecture reviews.

## How to Use

To use these skills with Gemini CLI, you can point to their location in your 
configuration or activate them directly if they are in your skills path.

```bash
# Example: Linking a skill
gemini skill link /path/to/repo/skills/<skill_folder>
```

## Structure

Each skill is contained within its own directory under `skills/` and includes:
- `SKILL.md`: The core instructions and mandates for the skill.
- `references/`: (Optional) Additional context, style guides, or technical references.
- `examples/`: (Optional) Example tickets, mappings, or reference implementations.
- `evals/`: (Optional) Evaluation datasets for testing skill performance.
