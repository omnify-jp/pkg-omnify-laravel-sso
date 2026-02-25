<?php

/**
 * User Resource
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 */

namespace Omnify\SsoClient\Http\Resources;

use Illuminate\Http\Request;
use Omnify\SsoClient\Http\Resources\OmnifyBase\UserResourceBase;
use Omnify\SsoClient\Models\Organization;

class UserResource extends UserResourceBase
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organization = null;

        if ($this->console_organization_id) {
            $org = Organization::where('console_organization_id', $this->console_organization_id)->first();

            if ($org) {
                $organization = [
                    'id' => $org->id,
                    'console_organization_id' => $org->console_organization_id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                ];
            }
        }

        return array_merge($this->schemaArray($request), [
            'organization' => $organization,
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
