<?php
/**
 *
 * @package symBB
 * @copyright (c) 2013 Christian Wielath
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace SymBB\Core\AdminBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAware;

class Builder extends ContainerAware
{

    public function mainMenu(FactoryInterface $factory, array $options)
    {
        $menu = $factory->createItem('root');
        $menu->setChildrenAttributes(array('class' => 'nav navbar-nav'));

        $menu->addChild('Overview', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');
        $menu->addChild('Forummanagment', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');
        $menu->addChild('Usermanagment', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');
        $menu->addChild('Style', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');
        $menu->addChild('Options', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');
        $menu->addChild('Extensions', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');
        $menu->addChild('Maintenance', array('route' => '_symbb_acp'))->setExtra('translation_domain', 'symbb_backend');

        return $menu;

    }
}