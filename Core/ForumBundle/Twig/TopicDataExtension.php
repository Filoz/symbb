<?
/**
*
* @package symBB
* @copyright (c) 2013 Christian Wielath
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace SymBB\Core\ForumBundle\Twig;

class TopicDataExtension extends \Twig_Extension
{

    protected $paginator;
    protected $em;
    protected $topicFlagHandler;

    public function __construct($em, $paginator, $container, $topicFlagHandler) {
        $this->paginator        = $paginator;
        $this->em               = $em;
        $this->container        = $container;
        $this->topicFlagHandler = $topicFlagHandler;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getTopicPagination', array($this, 'getTopicPagination')),
            new \Twig_SimpleFunction('checkSymbbForNewPostFlag', array($this, 'checkSymbbForNewPostFlag')),
            new \Twig_SimpleFunction('checkSymbbForAnsweredPostFlag', array($this, 'checkSymbbForAnsweredPostFlag')),
            new \Twig_SimpleFunction('checkSymbbForFlag', array($this, 'checkForFlag')),
        );
    }
    
    public function getTopicPagination($forum){
        
        $qb     = $this->em->createQueryBuilder();
        $qb     ->select('t')
                ->from('SymBB\Core\ForumBundle\Entity\Topic', 't')
                ->where('t.forum = '.$forum->getId())
                ->orderBy('t.changed', 'DESC');
        $dql    = $qb->getDql(); 
        $query  = $this->em->createQuery($dql);

        $pagination = $this->paginator->paginate(
            $query,
            $this->container->get('request')->query->get('page', 1)/*page number*/,
            $forum->getEntriesPerPage()/*limit per page*/
        );
        
        $pagination->setTemplate($this->getTemplateBundleName('forum').':Pagination:pagination.html.twig');

        return $pagination;
    }
    
    public function checkSymbbForNewPostFlag($element)
    {
        return $this->checkForFlag($element, 'new');
    }
    
    public function checkSymbbForAnsweredPostFlag($element)
    {
        return $this->checkForFlag($element, 'answered');
    }
    
    public function checkForFlag($element, $flag)
    {
        return $this->topicFlagHandler->checkFlag($element, $flag);
    }
    
    protected function getTemplateBundleName($for = 'forum'){
        $config = $this->container->getParameter('symbb_config');
        return $config['template'][$for];
    }
        
        
    public function getName()
    {
        return 'symbb_forum_topic_data';
    }
        
}