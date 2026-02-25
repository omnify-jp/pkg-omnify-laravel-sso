<?php

/**
 * TeamPermission Resource
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 */

namespace Omnify\SsoClient\Http\Resources;

use Illuminate\Http\Request;
use Omnify\SsoClient\Http\Resources\OmnifyBase\TeamPermissionResourceBase;

class TeamPermissionResource extends TeamPermissionResourceBase
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
