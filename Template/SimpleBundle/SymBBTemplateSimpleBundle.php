<?php
/**
 *
 * @package symBB
 * @copyright (c) 2013-2014 Christian Wielath
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace SymBB\Template\SimpleBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymBBTemplateSimpleBundle extends Bundle
{

    public function extendBundle()
    {
        return 'SymBBTemplateDefaultBundle';
    }
}