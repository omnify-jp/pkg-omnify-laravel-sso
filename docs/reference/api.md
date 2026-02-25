# API Reference

Complete reference for all SSO Client API endpoints.

## Authentication Endpoints

Base URL: `/api/sso` (configurable)

### Exchange Code

Exchange authorization code for session/token.

```http
POST /api/sso/callback
Content-Type: application/json
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes | Authorization code from Console |
| `device_name` | string | No | Device name for API token (mobile apps) |

**Example Request:**

```json
{
    "code": "abc123...",
    "device_name": "iPhone 15 Pro"
}
```

**Success Response (200):**

```json
{
    "user": {
        "id": 1,
        "console_user_id": 12345,
        "email": "user@example.com",
        "name": "John Doe"
    },
    "organizations": [
        {
            "id": "org-123",
            "name": "My Organization",
            "role": "admin"
        }
    ],
    "token": "1|abc..."  // Only if device_name provided
}
```

**Error Response (401):**

```json
{
    "error": "INVALID_CODE",
    "message": "Failed to exchange SSO code"
}
```

---

### Get Current User

Get authenticated user information.

```http
GET /api/sso/user
Authorization: Bearer {token}  // or session cookie
```

**Success Response (200):**

```json
{
    "user": {
        "id": 1,
        "console_user_id": 12345,
        "email": "user@example.com",
        "name": "John Doe"
    },
    "organizations": [...]
}
```

---

### Logout

Logout current user.

```http
POST /api/sso/logout
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
    "message": "Logged out successfully"
}
```

---

### Get Global Logout URL

Get Console logout URL for single sign-out.

```http
GET /api/sso/global-logout-url?redirect_uri=/logged-out
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `redirect_uri` | string | No | Redirect after logout |

**Success Response (200):**

```json
{
    "logout_url": "https://console.omnify.jp/sso/logout?redirect_uri=..."
}
```

---

## Token Management

### List Tokens

List all API tokens for current user.

```http
GET /api/sso/tokens
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
    "tokens": [
        {
            "id": 1,
            "name": "iPhone 15 Pro",
            "last_used_at": "2024-01-15T10:30:00Z",
            "created_at": "2024-01-01T00:00:00Z"
        }
    ]
}
```

---

### Delete Token

Revoke a specific token.

```http
DELETE /api/sso/tokens/{tokenId}
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
    "message": "Token revoked"
}
```

---

### Revoke Other Tokens

Revoke all tokens except current.

```http
POST /api/sso/tokens/revoke-others
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
    "message": "Other tokens revoked",
    "revoked_count": 3
}
```

---

## Admin API - Roles

Base URL: `/api/admin/sso` (configurable)

Requires: `sso.role:admin` middleware

### List Roles

```http
GET /api/admin/sso/roles
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number |
| `per_page` | int | Items per page |
| `search` | string | Search by name/slug |

**Success Response (200):**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Administrator",
            "slug": "admin",
            "description": "Full access",
            "level": 100,
            "created_at": "2024-01-01T00:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 5,
        "per_page": 15
    }
}
```

---

### Create Role

```http
POST /api/admin/sso/roles
Content-Type: application/json
```

**Request Body:**

```json
{
    "name": "Editor",
    "slug": "editor",
    "description": "Can edit content",
    "level": 30
}
```

**Success Response (201):**

```json
{
    "data": {
        "id": 2,
        "name": "Editor",
        "slug": "editor",
        "description": "Can edit content",
        "level": 30
    }
}
```

---

### Get Role

```http
GET /api/admin/sso/roles/{id}
```

---

### Update Role

```http
PUT /api/admin/sso/roles/{id}
Content-Type: application/json
```

---

### Delete Role

```http
DELETE /api/admin/sso/roles/{id}
```

---

### Get Role Permissions

```http
GET /api/admin/sso/roles/{id}/permissions
```

**Success Response (200):**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Create Users",
            "slug": "users.create",
            "group": "users"
        }
    ]
}
```

---

### Sync Role Permissions

```http
PUT /api/admin/sso/roles/{id}/permissions
Content-Type: application/json
```

**Request Body:**

```json
{
    "permission_ids": [1, 2, 3, 4]
}
```

---

## Admin API - Permissions

### List Permissions

```http
GET /api/admin/sso/permissions
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `group` | string | Filter by group |
| `search` | string | Search by name/slug |

---

### Create Permission

```http
POST /api/admin/sso/permissions
Content-Type: application/json
```

**Request Body:**

```json
{
    "name": "Create Users",
    "slug": "users.create",
    "group": "users"
}
```

---

### Permission Matrix

Get role-permission matrix.

```http
GET /api/admin/sso/permission-matrix
```

**Success Response (200):**

```json
{
    "roles": [
        {"id": 1, "name": "Admin", "slug": "admin"}
    ],
    "permissions": [
        {"id": 1, "name": "Create Users", "slug": "users.create", "group": "users"}
    ],
    "matrix": {
        "1": [1, 2, 3]  // role_id: [permission_ids]
    }
}
```

---

## Admin API - Team Permissions

### List Team Permissions

```http
GET /api/admin/sso/teams/permissions
```

---

### Get Team Permissions

```http
GET /api/admin/sso/teams/{teamId}/permissions
```

---

### Sync Team Permissions

```http
PUT /api/admin/sso/teams/{teamId}/permissions
Content-Type: application/json
```

**Request Body:**

```json
{
    "permission_ids": [1, 2, 3]
}
```

---

### Delete Team Permissions

```http
DELETE /api/admin/sso/teams/{teamId}/permissions
```

---

### List Orphaned Teams

Teams with permissions but no longer exist in Console.

```http
GET /api/admin/sso/teams/orphaned
```

---

### Cleanup Orphaned

Remove all orphaned team permissions.

```http
DELETE /api/admin/sso/teams/orphaned
```

---

## Error Responses

All endpoints may return these errors:

| Status | Error Code | Description |
|--------|------------|-------------|
| 401 | `UNAUTHENTICATED` | Not authenticated |
| 403 | `PERMISSION_DENIED` | Insufficient permissions |
| 403 | `ROLE_REQUIRED` | Insufficient role level |
| 404 | `NOT_FOUND` | Resource not found |
| 422 | `VALIDATION_ERROR` | Invalid input |
| 500 | `SERVER_ERROR` | Internal error |

**Error Response Format:**

```json
{
    "error": "ERROR_CODE",
    "message": "Human readable message",
    "errors": {  // Only for validation errors
        "field": ["Error message"]
    }
}
```
