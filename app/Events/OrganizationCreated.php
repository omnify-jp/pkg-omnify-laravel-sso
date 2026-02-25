<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Omnify\SsoClient\Models\Organization;

/**
 * Event dispatched when an organization is cached for the first time.
 *
 * This event is fired when a new organization is added to the local cache,
 * typically during SSO login or when accessing organization data.
 *
 * Use this event to:
 * - Create default org-specific roles
 * - Initialize org-specific settings
 * - Set up default permissions for the organization
 *
 * @example
 * // In EventServiceProvider
 * protected $listen = [
 *     OrganizationCreated::class => [
 *         SetupOrganizationDefaults::class,
 *     ],
 * ];
 */
class OrganizationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Organization $organization,
        public bool $wasRecentlyCreated = true
    ) {}
}
