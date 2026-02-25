# Omnify SSO Client Documentation

Laravel package for integrating with Omnify Console SSO.

## Quick Navigation

### Getting Started

| Document | Description |
|----------|-------------|
| [Installation](getting-started/installation.md) | Requirements, composer install, setup |
| [Configuration](getting-started/configuration.md) | Environment variables, config options |

### Guides

| Document | Description |
|----------|-------------|
| [Authentication](guides/authentication.md) | ServiceInstance architecture, login flow, JWT handling |
| [Authorization](guides/authorization.md) | RBAC, roles, permissions, branch-level access |
| [Middleware](guides/middleware.md) | Route protection, `sso.auth`, `sso.organization` middleware |
| [IAM Pages](guides/iam-pages.md) | Pre-built Inertia pages for user/role/permission management |
| [Security](guides/security.md) | Open redirect protection, JWT verification, best practices |

### Reference

| Document | Description |
|----------|-------------|
| [Schema Reference](reference/schemas.md) | SSO schemas: User, Role, Permission, etc. |
| [API Reference](reference/api.md) | All SSO Client API endpoints |
| [Seeders](reference/seeders.md) | Database seeders for roles, permissions |
| [Logging](reference/logging.md) | Audit trails, debugging, log channels |

### Development

| Document | Description |
|----------|-------------|
| [Testing](development/testing.md) | Test suite, mocking SSO, test helpers |

### Architecture (Internal)

Design documents for contributors and maintainers.

| Document | Status | Description |
|----------|--------|-------------|
| [SSO Package Traits](architecture/sso-package-traits.md) | ✅ Implemented | Scoping traits (HasOrganizationScope, HasBranchScope, etc.) |
| [Access Management](architecture/access-management.md) | ✅ Implemented | Two-tier access management design |
| [Layer 1: Service Access](architecture/layer-1-service-access.md) | ✅ Implemented | Service access layer (SSO → Services) |
| [Access Control Flow](architecture/access-control-flow-diagram.md) | ✅ Implemented | Access control flow diagrams |
| [Scoping Traits Design](architecture/scoping-traits-design.md) | ✅ Implemented | Detailed scoping traits design |
| [SSO Org API Sync](architecture/sso-org-api-sync.md) | ✅ Implemented | Organization data sync (via React SSO package) |
| [Branch Permissions Design](architecture/branch-permissions-design.md) | ✅ Implemented | Branch-level RBAC architecture |
| [Event Bus Implementation](architecture/event-bus-implementation.md) | 📋 Planning | AWS SNS/SQS event-driven architecture |
| [Refactor SSO Cache Schemas](architecture/refactor-sso-cache-schemas.md) | ✅ Done | Cache model naming convention |

## Directory Structure

```
docs/
├── README.md                 # This file
├── getting-started/          # Setup & configuration
│   ├── installation.md
│   └── configuration.md
├── guides/                   # How-to guides
│   ├── authentication.md
│   ├── authorization.md
│   ├── middleware.md
│   ├── iam-pages.md
│   └── security.md
├── reference/                # API & feature reference
│   ├── schemas.md
│   ├── api.md
│   ├── seeders.md
│   └── logging.md
├── development/              # For contributors
│   └── testing.md
└── architecture/             # Design documents
    ├── sso-package-traits.md          # ✅ Scoping traits
    ├── access-management.md           # ✅ Access management
    ├── layer-1-service-access.md      # ✅ Service access layer
    ├── access-control-flow-diagram.md # ✅ Flow diagrams
    ├── scoping-traits-design.md       # ✅ Traits design
    ├── sso-org-api-sync.md            # ✅ Org sync
    ├── branch-permissions-design.md   # ✅ Branch RBAC
    ├── event-bus-implementation.md    # 📋 Event bus
    └── refactor-sso-cache-schemas.md  # ✅ Cache schemas
```

## Related Documentation

- [Main Project CLAUDE.md](../CLAUDE.md) - Project conventions and rules
- [Omnify Schema Guide](../.claude/omnify/guides/omnify/schema-guide.md) - Schema definitions
