<?php
/**
 *
 * @package symBB
 * @copyright (c) 2013-2014 Christian Wielath
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace SymBB\Core\ForumBundle\Security\Authorization;

use SymBB\Core\SystemBundle\DependencyInjection\AccessManager;
use SymBB\Core\UserBundle\Entity\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class PostVoter implements VoterInterface
{
    /**
     * @var \SymBB\Core\SystemBundle\DependencyInjection\AccessManager
     */
    protected $accessManager;

    public function __construct(AccessManager $accessManager){
        $this->accessManager = $accessManager;
    }

    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const MOVE = 'move';

    public function supportsAttribute($attribute)
    {
        return in_array($attribute, array(
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::MOVE,
        ));
    }

    public function supportsClass($class)
    {
        $supportedClass = 'SymBB\Core\ForumBundle\Entity\Post';

        return $supportedClass === $class || is_subclass_of($class, $supportedClass);
    }

    /**
     * @param TokenInterface $token
     * @param \SymBB\Core\ForumBundle\Entity\Post $post
     * @param array $attributes
     * @return int
     * @throws InvalidArgumentException
     */
    public function vote(TokenInterface $token, $post, array $attributes)
    {
        // check if class of this object is supported by this voter
        if (!$this->supportsClass(get_class($post))) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        // check if the voter is used correct, only allow one attribute
        // this isn't a requirement, it's just one easy way for you to
        // design your voter
        if(1 !== count($attributes)) {
            throw new InvalidArgumentException(
                'Only one attribute is allowed'
            );
        }

        // set the attribute to check against
        $attribute = $attributes[0];

        // get current logged in user
        $user = $token->getUser();

        // check if the given attribute is covered by this voter
        if (!$this->supportsAttribute($attribute)) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        // make sure there is a user object (i.e. that the user is logged in)
        if (!$user instanceof UserInterface) {
            return VoterInterface::ACCESS_DENIED;
        }

        switch($attribute) {
            case 'view':
                $forum = $post->getTopic()->getForum();
                $this->accessManager->addAccessCheck('CoreUser', 'VIEW', $forum);
                if ($this->accessManager->hasAccess()) {
                    return VoterInterface::ACCESS_GRANTED;
                }
                break;

            case 'edit':
                if ($user->getId() === $post->getAuthor()->getId()) {
                    return VoterInterface::ACCESS_GRANTED;
                }
                $forum = $post->getTopic()->getForum();
                $this->accessManager->addAccessCheck('CoreMod', 'EDIT_POST', $forum);
                if ($this->accessManager->hasAccess()) {
                    return VoterInterface::ACCESS_GRANTED;
                }
                break;

            case 'delete':
                if ($user->getId() === $post->getAuthor()->getId()) {
                    return VoterInterface::ACCESS_GRANTED;
                }
                $forum = $post->getTopic()->getForum();
                $this->accessManager->addAccessCheck('CoreMod', 'DELETE_POST', $forum);
                if ($this->accessManager->hasAccess()) {
                    return VoterInterface::ACCESS_GRANTED;
                }
                break;

            case 'move':
                $forum = $post->getTopic()->getForum();
                $this->accessManager->addAccessCheck('CoreMod', 'MOVE_POST', $forum);
                if ($this->accessManager->hasAccess()) {
                    return VoterInterface::ACCESS_GRANTED;
                }
                break;
        }
    }
}