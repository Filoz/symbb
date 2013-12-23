<?
/**
*
* @package symBB
* @copyright (c) 2013 Christian Wielath
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace SymBB\Core\UserBundle\Twig;

class UserDataExtension extends \Twig_Extension
{

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getSymbbUserData', array($this, 'getSymbbUserData')),
            new \Twig_SimpleFunction('getSymbbUserAvatar', array($this, 'getSymbbUserAvatar')),
            new \Twig_SimpleFunction('getSymbbUserSignature', array($this, 'getSymbbUserSignature'))
        );
    }
    
    public function getSymbbUserData(\SymBB\Core\UserBundle\Entity\UserInterface $user)
    {
        $data = $user->getSymbbData();
        return $data;
    }
    
    public function getSymbbUserSignature(\SymBB\Core\UserBundle\Entity\UserInterface $user)
    {
        $data = $user->getSymbbData();
        return $data->getSignature();
    }
    
    public function getSymbbUserAvatar(\SymBB\Core\UserBundle\Entity\UserInterface $user)
    {
        $data   = $user->getSymbbData();
        $avatar = $data->getAvatarUrl();
        // do not check for gravatar exist directly because its slow! we use the boolean flag
        if(empty($avatar) && $data->hasGravatar()){
            $gravatar   = new \SymBB\Core\UserBundle\GravatarApi();
            $avatar     = $gravatar->getUrl($user->getEmail());
        }
        if(empty($avatar)){
            $avatar = '/bundles/symbbtemplatesimple/images/avatar/empty.gif';
        }
        return $avatar;
    }

    public function getName()
    {
        return 'symbb_user_data';
    }
}