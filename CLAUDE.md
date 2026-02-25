# Omnify Project

This project uses **Omnify** for schema-driven code generation.

## Quick Reference

- **Schema Guide**: @.claude/omnify/guides/omnify/schema-guide.md
- **Config Guide**: @.claude/omnify/guides/omnify/config-guide.md

## Commands

```bash
npx omnify generate    # Generate code from schemas
npx omnify validate    # Validate schemas
php artisan migrate    # Run database migrations
```

## Critical Rules

### ⛔ DO NOT EDIT Auto-Generated Files
- `database/migrations/omnify/**` - Regenerated on `npx omnify generate`
- `app/Models/OmnifyBase/**` - Base models (extend, don't edit)
- `app/Http/Requests/OmnifyBase/**` - Base requests
- `app/Http/Resources/OmnifyBase/**` - Base resources

### ✅ Schema-First Workflow
1. Edit YAML schema in `schemas/`
2. Run `npx omnify generate`
3. Run `php artisan migrate`

**NEVER use `php artisan make:migration`** - Always use schemas!

## Documentation Structure

```
.claude/
├── CLAUDE.md              # This file (root pointer)
├── rules/                 # Claude Code rules (path-specific)
│   └── omnify/*.md
└── omnify/                # Detailed guides
    ├── guides/
    │   ├── omnify/        # Schema & config docs
    │   ├── laravel/       # Laravel patterns
    │   └── react/         # React patterns
    ├── workflows/         # Step-by-step workflows
    └── agents/            # AI agent prompts
```

## Individual Preferences

Add your personal preferences in `CLAUDE.local.md` (gitignored).

<!-- OMNIFY_SECTION_START -->
## Omnify Schema System

This project uses **Omnify** for schema-driven code generation.

### Quick Reference

- **Schema Guide**: @.claude/omnify/guides/omnify/schema-guide.md
- **Config Guide**: @.claude/omnify/guides/omnify/config-guide.md

### Commands

```bash
npx omnify generate    # Generate code from schemas
npx omnify validate    # Validate schemas
php artisan migrate    # Run database migrations
```

### Critical Rules

#### ⛔ DO NOT EDIT Auto-Generated Files
- `database/migrations/omnify/**` - Regenerated on `npx omnify generate`
- `app/Models/OmnifyBase/**` - Base models (extend, don't edit)
- `app/Http/Requests/OmnifyBase/**` - Base requests
- `app/Http/Resources/OmnifyBase/**` - Base resources

#### ✅ Schema-First Workflow
1. Edit YAML schema in `schemas/`
2. Run `npx omnify generate`
3. Run `php artisan migrate`

**NEVER use `php artisan make:migration`** - Always use schemas!
<!-- OMNIFY_SECTION_END -->
