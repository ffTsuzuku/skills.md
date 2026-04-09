# Agent CLI Skills

Some personal agent skills I'm developing using the open source standard set by Anthropic. [Link](https://www.agentskills.io)

## Available Skills

### [Code Porter](./skills/code-porter/SKILL.md)
Port code from one codebase, language, or framework to another. It focuses on a high-velocity "output before analysis" workflow, providing a `PORTING_MAP.md` to track changes, refactors, and skipped items.
- **Use when:** Migrating services (e.g., Firebase to Laravel, Node to Go), translating logic between stacks, or moving code between repositories.

### [Code Refactor](./skills/code-refactor/SKILL.MD)
Expert-level code refactoring focused on readability, modern conventions, and structural clarity while strictly preserving business logic. It follows a "Plan -> Execute -> Validate" workflow.
- **Use when:** Cleaning up technical debt, improving naming, breaking down complex methods, or modernizing an existing codebase.

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
- `evals/`: (Optional) Evaluation datasets for testing skill performance.

