<?
/**
*
* @package symBB
* @copyright (c) 2013 Christian Wielath
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace SymBB\Core\ForumBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use SymBB\Core\UserBundle\Acl\PermissionMap;
use SymBB\Core\UserBundle\Acl\MaskBuilder;


class FrontendTopicController  extends Controller 
{
    
    protected $templateBundle = null;
    protected $topic = null;
    protected $post = null;
    protected $forum = null;

    /**
     * show a Topic
     * @param type $name
     * @param type $id
     * @return type
     */
    public function showAction($name, $id){
        
        $topic          = $this->getTopicById($id);
        
        $accessService  = $this->get('symbb.core.user.access');
        $accessService->addAccessCheck('view', $topic);
        $accessService->addAccessCheck('view', $topic->getForum());
        $accessService->checkAccess();
        
        $post   = null;
        $form   = null;
        $view   = null;
        if(is_object($this->getUser())){
            $form   = $this->getPostForm($topic, $post); 
            $view   = $form->createView();
        }
        
        $em     = $this->get('doctrine')->getManager('symbb');
        $qb     = $em->createQueryBuilder();
        $qb     ->select('p')
                ->from('SymBB\Core\ForumBundle\Entity\Post', 'p')
                ->where('p.topic = '.$topic->getId())
                ->orderBy('p.created', 'ASC');
        $dql    = $qb->getDql();
        $query  = $em->createQuery($dql);

        $page = $this->get('request')->query->get('page', 1);
        
        $lastPageFirst = false;
        if($page == 'last'){
            $page = 1;
            $lastPageFirst = true;
        }
        
        
        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $this->get('request')->query->get('page', 1)/*page number*/,
            $topic->getForum()->getEntriesPerPage()
        );
        
        // TODO optimizer but try to not use plain doctrine query "count" because we will net
        // other database implementation of knp paginator... eventually we will insert later a elasticsearch or so...
        if($lastPageFirst){
            $paginationData = $pagination->getPaginationData();
            $lastPage = $paginationData['endPage'];
            $query  = $em->createQuery($dql);
            $pagination = $paginator->paginate(
                $query,
                $lastPage,
                $topic->getForum()->getEntriesPerPage()
            );
        }
        
        $pagination->setTemplate($this->getTemplateBundleName('forum').':Pagination:pagination.html.twig');
        
        $this->get('symbb.core.forum.topic.flag')->removeFlag($topic, 'new');
        
        $params                 = array();
        $params['topic']        = $topic;
        $params['form']         = $view;
        $params['pagination']   = $pagination;

        return $this->render($this->getTemplateBundleName('forum').':Topic:show.html.twig', $params);
    }
    
    public function editAction($topic){ 
        $topic      = $this->getTopicById($topic); 
        $forum      = $topic->getForum();
        return $this->doEdit($forum, $topic);
    }
    
    public function newAction($forum){
        $forum      = $this->getForumById($forum);      
        return $this->doEdit($forum);
    }
    
    public function doEdit(\SymBB\Core\ForumBundle\Entity\Forum $forum, $topic = null ){

        // user can only create new topic in "forum" not in "category" or "link"
        if($forum->getType() !== 'forum'){
            return $this->forward('SymBBCoreForumBundle:Frontend:forumShow', array(
                'name' => $forum->getSeoName(),
                'id'  => $forum->getId()
            ));
        }
        
        $accessService  = $this->get('symbb.core.user.access');
        $accessService->addAccessCheck('write', $forum);
        $accessService->checkAccess();
        

        $form       = null;
        $saved      = $this->handleTopicRequest($form, $forum, $topic);

        $params = array('forum' => $forum, 'topic' => $topic);
        $params['form']     = $form->createView();
        $params['saved']    = $saved;
        
        return $this->render($this->getTemplateBundleName('forum').':Topic:new.html.twig', $params);
    }
    
    public function deleteAction($topic){
        
        $topic = $this->getTopicById($topic);
        
        $accessService  = $this->get('symbb.core.user.access');
        $accessService->addAccessCheck('delete', $topic);
        $accessService->addAccessCheck('delete', $topic->getForum());
        $accessService->checkAccess();
        
        $em         = $this->getDoctrine()->getManager('symbb');
        $topic      = $this->getTopicById($topic);
        $forum      = $topic->getForum();
        $em->remove($topic);
        $em->flush();
        return $this->render($this->getTemplateBundleName('forum').':Topic:delete.html.twig', array('forum' => $forum));
    }
    
    /**
     * 
     * @param type $topic
     * @return \SymBB\Core\ForumBundle\Entity\Forum
     */
    protected function getForumById($forum){
        if($this->forum === null){
            $this->forum      = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')->find($forum);
        }
        return $this->forum;
    }
    
    /**
     * 
     * @param type $topic
     * @return \SymBB\Core\ForumBundle\Entity\Topic
     */
    protected function getTopicById($topic){
        if($this->topic === null){
            $this->topic      = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Topic', 'symbb')->find($topic);
        }
        return $this->topic;
    }
    
    /**
     * 
     * @param type $post
     * @return \SymBB\Core\ForumBundle\Entity\Post
     */
    protected function getPostById($post){
        if($this->post === null){
            $this->post      = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Post', 'symbb')->find($post);
        }
        return $this->post;
    }

    /**
     * handle the Post Request and save it
     * @param type $form
     * @param \SymBB\Core\ForumBundle\Entity\Topic $topic
     * @param type $post
     * @return type
     */
    protected function handleTopicRequest(&$form, \SymBB\Core\ForumBundle\Entity\Forum &$forum, &$topic){
        
        if(!is_object($topic)){
            
            $post   = new \SymBB\Core\ForumBundle\Entity\Post();
            $post->setAuthor($this->getUser());
            
            $topic  = new \SymBB\Core\ForumBundle\Entity\Topic();
            $topic->setAuthor($this->getUser());
            $topic->setForum($forum);
            $post->setTopic($topic);
            $topic->setMainPost($post);
        }
        
        $form   = $this->getTopicForm($forum, $post, $topic);

        $event      = new \SymBB\Core\EventBundle\Event\EditTopicEvent($topic, $form);
        $this->get('event_dispatcher')->dispatch('symbb.topic.controller.handle.request', $event);
        
        $event      = new \SymBB\Core\EventBundle\Event\EditPostEvent($topic->getMainPost(), $form->get('mainPost'));
        $this->get('event_dispatcher')->dispatch('symbb.post.controller.handle.request', $event);
        
        $form->handleRequest($this->get('request'));
        
        if ($form->isValid()) {
            
            $em         = $this->getDoctrine()->getManager('symbb');
            
            $event      = new \SymBB\Core\EventBundle\Event\EditTopicEvent($topic, $form);
            $this->get('event_dispatcher')->dispatch('symbb.topic.controller.save', $event);
            
            $event      = new \SymBB\Core\EventBundle\Event\EditPostEvent($topic->getMainPost(), $form->get('mainPost'));
            $this->get('event_dispatcher')->dispatch('symbb.post.controller.save', $event);
            
            $em->persist($topic);
            if($post){
                $em->persist($post);
            }
            $em->flush();
            
            $accessService  = $this->get('symbb.core.user.access');
            $accessService->grantAccess('owner', $topic);
            if($post){
                $accessService->grantAccess('owner', $post);
            }
            
            $this->get('symbb.core.forum.topic.flag')->insertFlags($topic, 'new');
            
            return true;
        }
        return false;
    }

    /**
     * generate a new Form object
     * @param type $topic
     * @param type $post
     * @return type
     */
    protected function getTopicForm($forum, &$post, &$topic){
        
        // check if form was submited
        if($topic->getId() > 0){
            $url        = $this->generateUrl('_symbb_forum_topic_edit', array('topic' => $topic->getId()));
        } else {
            $url        = $this->generateUrl('_symbb_forum_topic_new', array('forum' => $forum->getId()));
        }
        
        $dispatcher = $this->get('event_dispatcher');
        $form       = $this->createForm(new \SymBB\Core\ForumBundle\Form\Type\TopicType($url, $topic, $dispatcher, $this->get('translator')), $topic);
   
        return $form;
    }
    
    
    /**
     * generate a new Form object
     * @param type $topic
     * @param type $post
     * @return type
     */
    protected function getPostForm($topic){
        
        $post       = \SymBB\Core\ForumBundle\Entity\Post::createNew($topic, $this->getUser());
        $url        = $this->generateUrl('_symbb_new_post', array('topic' => $topic->getId()));
        $dispatcher = $this->get('event_dispatcher');
        $form       = $this->createForm(new \SymBB\Core\ForumBundle\Form\Type\PostType($url, $post, $dispatcher, $this->get('translator')), $post);
        
        if($this->get('symbb.core.forum.topic.flag')->checkFlag($topic, 'notify')){
            $form->get('notifyMe')->setData(true);
        }
        
        return $form;
    }

    
    /**
     * get the Template Bundle name
     * @param string $for
     * @return string
     */
    protected function getTemplateBundleName($for = 'forum'){
        if($this->templateBundle === null){
            $config = $this->container->getParameter('symbb_config');
            $this->templateBundle = $config['template'][$for];
        }
        return $this->templateBundle;
    }
    
    
}