<?php
/**
 *
 * @package symBB
 * @copyright (c) 2013-2014 Christian Wielath
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace SymBB\Core\UserBundle\DependencyInjection;

use \Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use \Doctrine\ORM\EntityManager;
use \SymBB\Core\UserBundle\Entity\UserInterface;

class UserManager
{

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var SecurityFactoryInterface
     */
    protected $securityFactory;

    /**
     * @var string 
     */
    protected $userClass = '';

    protected $paginator;

    protected $securityContext;

    public function __construct($container)
    {
        $this->em = $container->get('doctrine.orm.symbb_entity_manager');
        $this->securityFactory = $container->get('security.encoder_factory');
        $config = $container->getParameter('symbb_config');
        $this->config = $config['usermanager'];
        $this->userClass = $this->config['user_class'];
        $this->paginator = $container->get('knp_paginator');
        $this->securityContext = $container->get('security.context');

    }

    public function getCurrentUser()
    {
        return $this->securityContext->getToken()->getUser();

    }

    /**
     * update the given user
     * @param \SymBB\Core\UserBundle\Entity\UserInterface $user
     */
    public function updateUser(UserInterface $user)
    {
        $this->em->persist($user);
        $this->em->flush();

    }

    /**
     * remove the given user
     * @param UserInterface $user
     */
    public function removeUser(UserInterface $user)
    {
        $this->em->remove($user);
        $this->em->flush();

    }

    /**
     * change the password of an user
     * @param \SymBB\Core\UserBundle\Entity\UserInterface $user
     * @param string $newPassword
     */
    public function changeUserPassword(UserInterface $user, $newPassword)
    {
        $encoder = $this->securityFactory->getEncoder($user);
        $password = $encoder->encodePassword($newPassword, $user->getSalt());
        $user->setPassword($password);
        $this->em->persist($user);
        $this->em->flush();

    }

    /**
     * create a new User
     * @return UserInterface
     */
    public function createUser()
    {
        $userClass = $this->userClass;
        $user = new $userClass();
        return $user;

    }

    /**
     * 
     * @param type $userId
     * @return \SymBB\Core\UserBundle\Entity\UserInterface
     */
    public function find($userId)
    {
        $user = $this->em->getRepository($this->userClass)->find($userId);
        return $user;

    }

    /**
     * 
     * @return array(<"\SymBB\Core\UserBundle\Entity\UserInterface">)
     */
    public function findUsers()
    {
        $users = $this->em->getRepository($this->userClass)->findAll();
        return $users;

    }

    public function getClass()
    {
        return $this->userClass;

    }

    public function paginateAll($request)
    {
        $dql = "SELECT u FROM SymBBCoreUserBundle:User u";
        $query = $this->em->createQuery($dql);

        $paginator = $this->paginator;
        $pagination = $paginator->paginate(
            $query, $request->query->get('page', 1)/* page number */, 20/* limit per page */
        );

        return $pagination;

    }

    public function getTimezone()
    {
        $user   = $this->getCurrentUser();
        $tz     = $user->getTimezone();
        return $tz;
    }
}