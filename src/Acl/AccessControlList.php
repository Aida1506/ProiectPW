<?php

namespace App\Acl;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;

class AccessControlList
{
    private Acl $acl;

    public function __construct()
    {
        $this->acl = new Acl();

        $this->setupRoles();
        $this->setupResources();
        $this->setupPermissions();
    }

    private function setupRoles(): void
    {
        // Roles: admin, player, guest
        $this->acl->addRole(new Role('guest'));
        $this->acl->addRole(new Role('player'), 'guest');
        $this->acl->addRole(new Role('admin'), 'player');
    }

    private function setupResources(): void
    {
        // Resources
        $this->acl->addResource(new Resource('game'));
        $this->acl->addResource(new Resource('player'));
        $this->acl->addResource(new Resource('card'));
        $this->acl->addResource(new Resource('monster'));
        $this->acl->addResource(new Resource('admin'));
    }

    private function setupPermissions(): void
    {
        // Guest permissions
        $this->acl->allow('guest', 'game', 'view');
        $this->acl->allow('guest', 'player', 'view');
        $this->acl->allow('guest', 'card', 'view');
        $this->acl->allow('guest', 'monster', 'view');

        // Player permissions
        $this->acl->allow('player', 'game', ['create', 'join', 'play']);
        $this->acl->allow('player', 'player', ['update_own']);
        $this->acl->allow('player', 'card', ['draw', 'play', 'discard']);
        $this->acl->allow('player', 'monster', ['attack']);

        // Admin permissions
        $this->acl->allow('admin', null, null); // All permissions
    }

    public function isAllowed(string $role, string $resource, string $privilege): bool
    {
        return $this->acl->isAllowed($role, $resource, $privilege);
    }

    public function getAcl(): Acl
    {
        return $this->acl;
    }
}