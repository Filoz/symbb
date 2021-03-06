<?php
/**
 *
 * @package symBB
 * @copyright (c) 2013-2014 Christian Wielath
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace SymBB\Core\UserBundle\DependencyInjection;

use SymBB\Core\UserBundle\Entity\User\Data;
use \Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use \Doctrine\ORM\EntityManager;
use \SymBB\Core\UserBundle\Entity\UserInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

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

    /**
     *
     * @var \Symfony\Component\Security\Core\SecurityContextInterface
     */
    protected $securityContext;

    /**
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcher 
     */
    protected $dispatcher;

    protected $container;

    protected $postCountCache = array();
    
    protected $symbbDataCache = array();

    public function __construct($container)
    {
        $this->em = $container->get('doctrine.orm.symbb_entity_manager');
        $this->securityFactory = $container->get('security.encoder_factory');
        $config = $container->getParameter('symbb_config');
        $this->config = $config['usermanager'];
        $this->userClass = $this->config['user_class'];
        $this->paginator = $container->get('knp_paginator');
        $this->securityContext = $container->get('security.context');
        $this->dispatcher = $container->get('event_dispatcher');
        $this->container = $container;
        $this->translator = $container->get("translator");
    }

    /**
     * 
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function getRequest()
    {
        return $this->container->get('request');
    }

    /**
     * 
     * @return \SymBB\Core\UserBundle\Entity\UserInterface
     */
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
     * update the given user data
     * @param \SymBB\Core\UserBundle\Entity\User\Data $user
     */
    public function updateUserData(Data $data)
    {
        $this->em->persist($data);
        $this->em->flush();

        //return array with sf validator errors
        return new \Symfony\Component\Validator\ConstraintViolationList();
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
        $validator = $this->container->get('validator');
        $passwordConstraints = $this->getPasswordValidatorConstraints();
        $passwordConstraints[] = new \Symfony\Component\Validator\Constraints\NotBlank();
        $errorsPassword = $validator->validateValue($newPassword, $passwordConstraints);

        if($errorsPassword->count() === 0){
            $this->em->persist($user);
            $this->em->flush();
        }

        return $errorsPassword;
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
     * @param string $username
     * @return \SymBB\Core\UserBundle\Entity\UserInterface
     */
    public function findByUsername($username)
    {
        $user = $this->em->getRepository($this->userClass)->findOneBy(array('username' => $username));
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

    public function countUsers()
    {
        $users = $this->findUsers();
        return count($users);
    }

    public function getClass()
    {
        return $this->userClass;
    }

    public function findBy($criteria, $limit, $page = 1)
    {

        $qb = $this->em->getRepository($this->userClass)->createQueryBuilder('u');
        $qb->select("u");
        $countValue = 1;
        foreach ($criteria as $field => $value) {
            if (\is_array($value)) {
                $qb->where("u." . $field . " " . key($value) . " ?" . reset($countValue) . "");
                $value = reset($value);
            } else {
                $qb->where("u." . $field . " = ?" . $countValue . "");
            }
            $qb->setParameter($countValue, $value);
            $countValue++;
        }
        $qb->orderBy("u.username", "ASC");
        $query = $qb->getQuery();
        $pagination = $this->paginator->paginate(
            $query, $page, $limit
        );

        return $pagination;
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
        $user = $this->getCurrentUser();
        $data = $this->getSymbbData($user);
        $tz = $data->getTimezone();

        if (!empty($tz)) {
            $tz = new \DateTimeZone($tz);
        } else {
            $now = new \DateTime;
            $tz = $now->getTimezone();
        }

        return $tz;
    }

    public function getSignature(\SymBB\Core\UserBundle\Entity\UserInterface $user = null)
    {
        if (!$user) {
            $user = $this->getCurrentUser();
        }

        $data = $this->getSymbbData($user);
        $signature = $data->getSignature();

        $event = new \SymBB\Core\UserBundle\Event\UserParseSignatureEvent($user, $signature);
        $this->dispatcher->dispatch("symbb.core.user.parse.signature", $event);

        $signature = $event->getSignature();
        return $signature;
    }

    public function getAbsoluteAvatarUrl(\SymBB\Core\UserBundle\Entity\UserInterface $user = null)
    {
        $url = $this->getAvatar($user);
        $host = '';

        if (strpos($url, 'http') === false) {
            $host = "http://" . $this->getRequest()->server->get('HTTP_HOST');
        }

        return $host . $url;
    }

    public function getAvatar(\SymBB\Core\UserBundle\Entity\UserInterface $user = null)
    {
        if (!$user) {
            $user = $this->getCurrentUser();
        }

        $data = $this->getSymbbData($user);
        $avatar = $data->getAvatar();
        if (empty($avatar)) {
            $avatar = '/bundles/symbbtemplatedefault/images/avatar/empty.gif';
        }
        return $avatar;
    }

    public function getPostCount(\SymBB\Core\UserBundle\Entity\UserInterface $user = null)
    {
        if (!$user) {
            $user = $this->getCurrentUser();
        }
        
        if (!isset($this->postCountCache[$user->getId()])) {
            $qb = $this->em->getRepository('SymBBCoreForumBundle:Post')->createQueryBuilder('p');
            $qb->select('COUNT(p.id)');
            $qb->where("p.author = " . $user->getId());
            $count = $qb->getQuery()->getSingleScalarResult();
            $this->postCountCache[$user->getId()] = $count;
        } else {
            $count = $this->postCountCache[$user->getId()];
        }
        return $count;
    }

    public function getLastPosts(\SymBB\Core\UserBundle\Entity\UserInterface $user = null, $limit = 20)
    {
        if (!$user) {
            $user = $this->getCurrentUser();
        }

        $qb = $this->em->getRepository('SymBBCoreForumBundle:Post')->createQueryBuilder('p');
        $qb->select('p');
        $qb->where("p.author = " . $user->getId());
        $qb->orderBy("p.created", "desc");
        $dql = $qb->getDql();
        $query = $this->em->createQuery($dql);

        $posts = $this->paginator->paginate(
            $query, 1/* page number */, $limit/* limit per page */
        );
        return $posts;
    }

    public function getTopicCount(\SymBB\Core\UserBundle\Entity\UserInterface $user = null)
    {
        if (!$user) {
            $user = $this->getCurrentUser();
        }

        $qb = $this->em->getRepository('SymBBCoreForumBundle:Topic')->createQueryBuilder('p');
        $qb->select('COUNT(p.id)');
        $qb->where("p.author = " . $user->getId());
        $count = $qb->getQuery()->getSingleScalarResult();
        return $count;
    }

    /**
     * manually login e.g for Tapatalk Extension
     * mtehod will be generate a cookie
     * @param type $username
     * @param type $password
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param type $providerKey
     * @param \Symfony\Component\HttpFoundation\Response $redirectResponse
     * @return boolean
     */
    public function login($username, $password, \Symfony\Component\HttpFoundation\Request $request, $providerKey, \Symfony\Component\HttpFoundation\Response $redirectResponse)
    {

        $user = $this->findByUsername($username);

        $encoder = $this->securityFactory->getEncoder($user);
        $password2 = $encoder->encodePassword($password, $user->getSalt());
        if ($user->getPassword() === $password2) {
            $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, $user->getPassword(), 'symbb', $user->getRoles());

            $this->securityContext->setToken($token);

            $securityKey = 'myKey';
            $random = new \Symfony\Component\Security\Core\Util\SecureRandom();

            $persistenService = new \Symfony\Component\Security\Http\RememberMe\PersistentTokenBasedRememberMeServices(array($this), $providerKey, $securityKey, array('path' => '/', 'name' => 'REMEMBERME', 'domain' => null, 'secure' => false, 'httponly' => true, 'lifetime' => 31536000, 'always_remember_me' => true, 'remember_me_parameter' => '_remember_me'), null, $random);
            $persistenService->setTokenProvider(new \Symfony\Component\Security\Core\Authentication\RememberMe\InMemoryTokenProvider());

            $persistenService->loginSuccess($request, $redirectResponse, $token);

            return true;
        }

        return false;
    }

    public function getPasswordValidatorConstraints()
    {
        $constraints = array();

        $strength = 3;

        if ($strength >= 1) {
            // Uppercase
            $constraints[] = new \Symfony\Component\Validator\Constraints\Regex(array(
                "pattern" => "/[A-Z]/",
                'message' => $this->translator->trans('Your Password need a minimum of 1 uppercase character', array(), 'validators')
            ));
            $constraints[] = new \Symfony\Component\Validator\Constraints\Length(array(
                "min" => 6,
                'minMessage' => $this->translator->trans('Your Password need a minimum of 6 characters', array(), 'validators')
            ));
        }

        if ($strength >= 2) {
            //lowercase
            $constraints[] = new \Symfony\Component\Validator\Constraints\Regex(array(
                "pattern" => "/[a-z]/",
                'message' => $this->translator->trans('Your Password need a minimum of 1 lowercase character', array(), 'validators')
            ));
        }

        if ($strength >= 3) {
            //lowercase
            $constraints[] = new \Symfony\Component\Validator\Constraints\Regex(array(
                "pattern" => "/[0-9]/",
                'message' => $this->translator->trans('Your Password need a minimum of 1 number', array(), 'validators')
            ));
        }

        if ($strength >= 4) {
            //none word characters
            $constraints[] = new \Symfony\Component\Validator\Constraints\Regex(array(
                "pattern" => "/\W/",
                'message' => $this->translator->trans('Your Password need a minimum of 1 none word character', array(), 'validators')
            ));
        }


        return $constraints;
    }

    public function getDateFormater($format)
    {

        if (\is_string($format)) {
            $format = \constant('\IntlDateFormatter::' . \strtoupper($format));
        } else if (!\is_numeric($format)) {
            throw new Exception('Format must be an string or IntlDateFormater Int Value');
        }

        $locale = \Symfony\Component\Locale\Locale::getDefault();
        $tz = $this->getTimezone();

        $fmt = new \IntlDateFormatter(
            $locale, $format, $format, $tz->getName(), \IntlDateFormatter::GREGORIAN
        );
        return $fmt;
    }

    /**
     * 
     * @param string $username
     * @return \SymBB\Core\UserBundle\Entity\UserInterface
     */
    public function findFields($criteria)
    {
        $fields = $this->em->getRepository('SymBBCoreUserBundle:Field')->findBy($criteria);
        return $fields;
    }

    public function getSymbbData(\SymBB\Core\UserBundle\Entity\UserInterface $user)
    {
        if (!isset($this->symbbDataCache[$user->getId()])) {
            $this->symbbDataCache[$user->getId()] = $user->getSymbbData();
        }
        return $this->symbbDataCache[$user->getId()];
    }
}