<?
/**
*
* @package symBB
* @copyright (c) 2013 Christian Wielath
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace SymBB\Core\EventBundle\Event;

class TemplateFormPostEvent extends BaseTemplateEvent
{
    

    protected $form;


    public function __construct($env, $form) {
        $this->form = $form;
        $this->env = $env;
    }
    
    public function getForm(){
        return $this->form;
    }
    
}
