<?php

/**
 * RolePermission Resource
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 */

namespace Omnify\Core\Http\Resources\OmnifyBase;

use Illuminate\Http\Request;
use Omnify\Core\Http\Resources\OmnifyBase\OmnifyBase\RolePermissionResourceBase;

class RolePermissionResource extends RolePermissionResourceBase
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge($this->schemaArray($request), [
            // Custom fields here
        ]);
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            // Additional metadata here
        ];
    }
}
