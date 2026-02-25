<?php

declare(strict_types=1);

namespace Omnify\SsoClient\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Schema definitions for SSO Client models.
 */
#[OA\Schema(
    schema: 'Role',
    title: 'Role',
    description: 'User role with permissions',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', maxLength: 100, example: 'admin'),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Administrator'),
        new OA\Property(property: 'level', type: 'integer', minimum: 0, maximum: 100, example: 100),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'permissions_count', type: 'integer', example: 10),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Permission',
    title: 'Permission',
    description: 'System permission',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', maxLength: 100, example: 'projects.create'),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Create Projects'),
        new OA\Property(property: 'group', type: 'string', maxLength: 50, nullable: true, example: 'projects'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'roles_count', type: 'integer', example: 3),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Team',
    title: 'Team',
    description: 'Team from Console SSO',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
        new OA\Property(property: 'console_organization_id', type: 'integer', example: 100),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Development Team'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'SsoUser',
    title: 'SSO User',
    description: 'Authenticated user from SSO',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'console_user_id', type: 'integer', example: 54321),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
    ]
)]
#[OA\Schema(
    schema: 'Organization',
    title: 'Organization',
    description: 'Console organization',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 100),
        new OA\Property(property: 'slug', type: 'string', example: 'acme-corp'),
        new OA\Property(property: 'name', type: 'string', example: 'ACME Corporation'),
        new OA\Property(property: 'role', type: 'string', example: 'admin'),
    ]
)]
#[OA\Schema(
    schema: 'ApiToken',
    title: 'API Token',
    description: 'Personal access token for API authentication',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'iPhone 15'),
        new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'is_current', type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'TeamPermission',
    title: 'Team Permission',
    description: 'Permission assigned to a team',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'console_organization_id', type: 'integer', example: 100),
        new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
        new OA\Property(property: 'permission_id', type: 'integer', example: 1),
        new OA\Property(property: 'permission', ref: '#/components/schemas/Permission', nullable: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    title: 'Validation Error',
    description: 'Validation error response',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            ),
            example: ['email' => ['The email field is required.']]
        ),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'Error Response',
    description: 'Generic error response',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'ERROR_CODE'),
        new OA\Property(property: 'message', type: 'string', example: 'Human readable error message'),
    ]
)]
class Schemas
{
    // This class exists only for OpenAPI schema definitions
}
