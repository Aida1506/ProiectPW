<?php

namespace App\Acl;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;

class AccessControlList
{
    private Acl $acl;

    /**
     * Construieste obiectul ACL si incarca rolurile, resursele si permisiunile.
     * Constructorul centralizeaza regulile de acces pentru tot API-ul.
     */
    public function __construct()
    {
        // Creeaza obiectul ACL oferit de biblioteca Laminas.
        $this->acl = new Acl();

        // Ordinea conteaza: intai rolurile, apoi resursele, apoi permisiunile dintre ele.
        $this->setupRoles();
        $this->setupResources();
        $this->setupPermissions();
    }

    /**
     * Defineste ierarhia rolurilor: player mosteneste guest, iar admin mosteneste player.
     * In felul acesta admin primeste implicit toate permisiunile rolurilor inferioare.
     */
    private function setupRoles(): void
    {
        // Roles: admin, player, guest
        // Guest este rolul implicit pentru utilizatorii care doar citesc date.
        $this->acl->addRole(new Role('guest'));
        // Player mosteneste guest, deci poate si vizualiza, nu doar face actiuni.
        $this->acl->addRole(new Role('player'), 'guest');
        // Admin mosteneste player si ulterior primeste toate permisiunile.
        $this->acl->addRole(new Role('admin'), 'player');
    }

    /**
     * Inregistreaza resursele protejate de ACL.
     * Resursele sunt categorii logice, nu fisiere: game, player, card, monster si admin.
     */
    private function setupResources(): void
    {
        // Resources
        // Fiecare resursa corespunde unei zone logice din API.
        $this->acl->addResource(new Resource('game'));
        $this->acl->addResource(new Resource('player'));
        $this->acl->addResource(new Resource('card'));
        $this->acl->addResource(new Resource('monster'));
        $this->acl->addResource(new Resource('admin'));
    }

    /**
     * Leaga rolurile de privilegiile permise.
     * Guest poate doar vedea, player poate juca, iar admin primeste acces complet.
     */
    private function setupPermissions(): void
    {
        // Guest permissions
        // Guest are voie doar sa citeasca informatii publice.
        $this->acl->allow('guest', 'game', 'view');
        $this->acl->allow('guest', 'player', 'view');
        $this->acl->allow('guest', 'card', 'view');
        $this->acl->allow('guest', 'monster', 'view');

        // Player permissions
        // Player poate crea si juca sesiuni, dar nu primeste acces complet de administrare.
        $this->acl->allow('player', 'game', ['create', 'join', 'play', 'update_own']);
        // update_own este folosit pentru editarea datelor proprii sau ale unei sesiuni active.
        $this->acl->allow('player', 'player', ['update_own']);
        // Actiunile pe carti sunt separate: draw, play si discard.
        $this->acl->allow('player', 'card', ['draw', 'play', 'discard']);
        // Atacul de monstru este privilegiu distinct, mapat din ruta /attack.
        $this->acl->allow('player', 'monster', ['attack']);

        // Admin permissions
        // null, null inseamna orice resursa si orice privilegiu.
        $this->acl->allow('admin', null, null); // All permissions
    }

    /**
     * Verifica daca un rol are voie sa execute o actiune asupra unei resurse.
     * Middleware-ul apeleaza metoda inainte ca request-ul sa ajunga la ruta finala.
     */
    public function isAllowed(string $role, string $resource, string $privilege): bool
    {
        // Delegam verificarea catre Laminas ACL, care tine cont si de mostenirile dintre roluri.
        return $this->acl->isAllowed($role, $resource, $privilege);
    }

    /**
     * Expune obiectul Laminas ACL pentru debug sau extinderi viitoare.
     */
    public function getAcl(): Acl
    {
        // Returneaza obiectul intern pentru cazuri de debug sau inspectie.
        return $this->acl;
    }
}
