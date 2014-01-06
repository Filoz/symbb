<?php
/**
*
* @package symBB
* @copyright (c) 2013 Christian Wielath
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace SymBB\Core\ForumBundle\Acl\MaskBuilder\Forum;

use \Symfony\Component\Security\Acl\Permission\MaskBuilder as BaseMaskBuilder;

class Basic extends BaseMaskBuilder {
    
    const MASK_VIEW         = 1;          // 1 << 0
    const MASK_CREATE_TOPIC = 2;          // 1 << 0
    const MASK_CREATE_POST  = 4;          // 1 << 1
    const MASK_EDIT_TOPIC   = 8;          // 1 << 2
    const MASK_EDIT_POST    = 16;          // 1 << 3
    const MASK_DELETE_TOPIC = 32;         // 1 << 4
    const MASK_DELETE_POST  = 64;         // 1 << 5
    const MASK_MASTER       = 128;         // 1 << 4
    const MASK_OWNER        = 256;         // 1 << 5
    const MASK_IDDQD        = 1073741823; // 1 << 0 | 1 << 1 | ... | 1 << 30

    const CODE_VIEW         = 'V_T';
    const CODE_CREATE_TOPIC = 'C_T';
    const CODE_CREATE_POST  = 'C_P';
    const CODE_EDIT_TOPIC   = 'E_T';
    const CODE_EDIT_POST    = 'E_P';
    const CODE_DELETE_TOPIC = 'D_T';
    const CODE_DELETE_POST  = 'D_P';
    const CODE_OWNER        = 'O';
    const CODE_MASTER       = 'M';
    
}