<?
/**
 *
 * @package symBB
 * @copyright (c) 2013-2014 Christian Wielath
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace SymBB\Core\ForumBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
    
use \SymBB\Core\UserBundle\Entity\UserInterface;
use \SymBB\Core\ForumBundle\Entity\Topic;
use SymBB\Core\ForumBundle\Entity\Post;

class FrontendApiController extends \SymBB\Core\SystemBundle\Controller\AbstractApiController
{

    /**
     * @Route("/api/forum/{forumId}/topic/save", name="symbb_api_forum_topic_save")
     * @Method({"POST"})
     */
    public function topicSaveAction($forumId){
        
        $request = $this->get('request');
        $topicId = $request->get('id');
        
        $params = array();

        $forum              = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')->find($forumId);
        
        if(is_object($forum)){
            
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#CREATE_TOPIC', $forum, $this->getUser());
            $writeAccess = $this->get('symbb.core.access.manager')->hasAccess();
            
            if($writeAccess){
                
                if($topicId > 0){
                    $topic = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Topic', 'symbb')->find($topicId);
                    $mainPost = $topic->getMainPost();
                    $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_TOPIC#EDIT', $topic, $this->getUser());
                    $editAccess = $this->get('symbb.core.access.manager')->hasAccess();
                } else {
                    $topic = new \SymBB\Core\ForumBundle\Entity\Topic();
                    $topic->setAuthor($this->getUser());
                    $mainPost = new \SymBB\Core\ForumBundle\Entity\Post();
                    $mainPost->setTopic($topic);
                    $mainPost->setAuthor($this->getUser());
                    $topic->setMainPost($mainPost);
                    $topic->setForum($forum);
                    $editAccess = true;
                }
                
                if($editAccess){
                    
                    $mainPostData = $request->get('mainPost');
                    
                    $topic->setName($request->get('name'));
                    $mainPost->setText($mainPostData['text']);
                    
                    $em = $this->getDoctrine()->getManager('symbb');
                    $em->persist($topic);
                    $em->persist($mainPost);
                    $em->flush();
                    
                    $this->addSuccessMessage('saved successfully.');
                    
                    $params['id'] = $topic->getId();
                    
                } else {
                    $this->addErrorMessage('access denied (edit topic)');
                }
                
            } else {
                $this->addErrorMessage('access denied (create topic)');
            }
            
        } else {
            $this->addErrorMessage('forum not found');
        }
        
        return $this->getJsonResponse($params);
    }
    
    /**
     * @Route("/api/forum/{id}/topic/create", name="symbb_api_forum_topic_create")
     * @Method({"GET"})
     */
    public function topicCreateAction($id){
        $forum = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')->find($id);
        $params = array();
        $params['forum'] = $this->getForumAsArray($forum);
        $params['topic'] = $this->getTopicAsArray();
        $breadcrumbItems    = $this->get('symbb.core.forum.manager')->getBreadcrumbData($forum);
        $this->addBreadcrumbItems($breadcrumbItems);
        return $this->getJsonResponse($params);
    }
    
    /**
     * @Route("/api/forum/{id}/ignore", name="symbb_api_forum_ignore")
     * @Method({"POST"})
     */
    public function forumIgnore($id){
       
        $forum = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')
            ->find($id);
        $this->get('symbb.core.forum.manager')->ignoreForum($forum, $this->get('symbb.core.forum.flag'));

        $this->addSuccessMessage('You are now ignoring the Forum');
        $this->addCallback('refesh');

        return $this->getJsonResponse(array());
    }
    
    /**
     * @Route("/api/forum/{id}/unignore", name="symbb_api_forum_unignore")
     * @Method({"POST"})
     */
    public function forumUnignore($id){  
        
        $forum = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')
                ->find($id);

        $this->get('symbb.core.forum.manager')->watchForum($forum, $this->get('symbb.core.forum.flag'));

        $this->addSuccessMessage('You are now watching the forum again');
        $this->addCallback('refesh');

        return $this->getJsonResponse(array());
    }
    
    /**
     * @Route("/api/forum/{id}/markAsRead", name="symbb_api_forum_mark_as_read")
     * @Method({"POST"})
     */
    public function forumMarkAsRead($id){  
        $forum = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')
            ->find($id);
        $this->get('symbb.core.forum.manager')->markAsRead($forum, $this->get('symbb.core.forum.flag'));
        
        $this->addSuccessMessage('The forum has been marked as read');
        $this->addCallback('refesh');
        
        return $this->getJsonResponse(array());
    }
    
    /**
     * @Route("/api/topic/{id}/posts/{page}", name="symbb_api_forum_topic_post_list")
     * @Method({"GET"})
     */
    public function topicPostListAction($id, $page){ 
        $topic = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Topic', 'symbb')
            ->find($id);
        
        $lastPosts = $this->get('symbb.core.topic.manager')->findPosts($topic, $page);
        
        $params = array();
        foreach($lastPosts as $post){
            $params[] = $this->getPostAsArray($post);
        }
        
        return $this->getJsonResponse($params);
    }
    
    /**
     * @Route("/api/topic/{id}/data", name="symbb_api_forum_topic_show")
     * @Method({"GET"})
     */
    public function topicShowAction($id){  
        $topic = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Topic', 'symbb')
            ->find($id);
        
        $breadcrumbItems = $this->get('symbb.core.topic.manager')->getBreadcrumbData($topic, $this->get('symbb.core.forum.manager'));
        $this->addBreadcrumbItems($breadcrumbItems);
        
        $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#CREATE_POST', $topic->getForum(), $this->getUser());
        $writeAccess = $this->get('symbb.core.access.manager')->hasAccess();
        
        $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_TOPIC#EDIT', $topic, $this->getUser());
        $editAccess = $this->get('symbb.core.access.manager')->hasAccess();
        
        $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_TOPIC#DELETE', $topic, $this->getUser());
        $deleteAccess = $this->get('symbb.core.access.manager')->hasAccess();
        
        $params = array(
            'topic' => $this->getTopicAsArray($topic),
            'page' => 1,
            'access' => array(
                'create' => $writeAccess,
                'edit' => $editAccess,
                'delete' => $deleteAccess
            )
         );
        
        return $this->getJsonResponse($params);
        
    }
    
    
    /**
     * @Route("/api/forum/{parent}/data", name="symbb_api_forum_list", defaults={"parent" = 0})
     * @Method({"GET"})
     */
    public function forumListAction($parent){
        
        if((int)$parent === 0){
            $parent = null;
        }
   
        $forumEnityList = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')
            ->findBy(array('parent' => $parent, 'type' => 'forum'), array('position' => 'asc', 'id' => 'asc'));
        
        $forumList = array();
        
        $hasForumList = false;
        foreach ($forumEnityList as $key => $forum) {
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#VIEW', $forum, $this->getUser());
            $access = $this->get('symbb.core.access.manager')->hasAccess();
            if ($access) {
                $forumList[] = $this->getForumAsArray($forum);
                $hasForumList = true;
            };
        }
        
        $categoryEnityList = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')
            ->findBy(array('parent' => $parent, 'type' => 'category'), array('position' => 'asc', 'id' => 'asc'));

        $categoryList = array();
        $hasCategoryList = false;
        foreach ($categoryEnityList as $key => $forum) {
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#VIEW', $forum, $this->getUser());
            $access = $this->get('symbb.core.access.manager')->hasAccess();
            if ($access) {
                $categoryList[] = $this->getForumAsArray($forum);
                $hasCategoryList = true;
            };
        }
        
        
        $topicList = array();
        $hasTopicList = false;
        if($parent > 0){
            $parent = $this->get('doctrine')->getRepository('SymBBCoreForumBundle:Forum', 'symbb')->find($parent);
            $topics = $this->get('symbb.core.forum.manager')->findTopics($parent);
            foreach($topics as $topic){
                $topicList[] = $this->getTopicAsArray($topic);
                $hasTopicList = true;
            }
        }
        
        
        $breadcrumbItems = $this->get('symbb.core.forum.manager')->getBreadcrumbData($parent);
        $this->addBreadcrumbItems($breadcrumbItems);
        
        $params = array(
            'forum' => $this->getForumAsArray($parent),
            'categoryList' => $categoryList, 
            'forumList' => $forumList, 
            'topicList' => $topicList,
            'hasForumList' => $hasForumList, 
            'hasCategoryList' => $hasCategoryList, 
            'hasTopicList' => $hasTopicList
         );
        
        return $this->getJsonResponse($params);
    }
    
    /**
     * 
     * @param \SymBB\Core\ForumBundle\Entity\Topic $topic
     * @return array
     */
    protected function getTopicAsArray(\SymBB\Core\ForumBundle\Entity\Topic $topic = null){
        $array = array();
        $array['id'] = 0;
        $array['name'] = '';
        $array['closed'] = false;
        $array['count']['post'] = 0;
        $array['backgroundImage'] = '';
        $array['flags'] = array();
        $array['posts'] = array();
        $array['seo']['name'] = '';
        $array['access'] = array(
            'createPost' => false,
            'edit' => false,
            'delete' => false
        );
        
        if(is_object($topic)){
            $array['id'] = $topic->getId();
            $array['name'] = $topic->getName();
            $array['count']['post'] = $topic->getPostCount();
            $array['closed'] = $topic->isLocked();
            $array['backgroundImage'] = $this->get('symbb.core.user.manager')->getAvatar($topic->getAuthor());
            foreach($this->get('symbb.core.topic.flag')->findAll($topic) as $flag){
                $array['flags'][$flag->getFlag()] = $this->getFlagAsArray($flag);
            }
            $lastPosts = $this->get('symbb.core.topic.manager')->findPosts($topic);
            foreach($lastPosts as $post){
                $array['posts'][] = $this->getPostAsArray($post);
            }
            $array['seo']['name'] = $topic->getSeoName();
            
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#CREATE_POST', $topic->getForum(), $this->getUser());
            $writePostAccess = $this->get('symbb.core.access.manager')->hasAccess();
        
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_TOPIC#EDIT', $topic, $this->getUser());
            $editAccess = $this->get('symbb.core.access.manager')->hasAccess();

            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_TOPIC#DELETE', $topic, $this->getUser());
            $deleteAccess = $this->get('symbb.core.access.manager')->hasAccess();

            $array['access'] = array(
                'createPost' => $writePostAccess,
                'edit' => $editAccess,
                'delete' => $deleteAccess
            );
            
        }
        return $array;
    }
    
    /**
     * 
     * @param \SymBB\Core\ForumBundle\Entity\Forum $forum
     * @return type
     */
    protected function getForumAsArray(\SymBB\Core\ForumBundle\Entity\Forum $forum = null){
        
        $array = array();
        $array['id'] = 0;
        $array['name'] = '';
        $array['description'] = '';
        $array['ignore'] = false;
        $array['count']['topic'] = 0;
        $array['count']['post'] = 0;
        $array['backgroundImage'] = "";
        $array['flags'] = array();
        $array['children'] = array();
        $array['lastPosts'] = array();
        $array['seo']['name'] = '';
        $array['access'] = array(
            'createTopic' => false,
            'createPost' => false
        );
        
        if(is_object($forum)){
            $array['id'] = $forum->getId();
            $array['name'] = $forum->getName();
            $array['description'] = $forum->getDescription();
            $array['count']['topic'] = $forum->getTopicCount();
            $array['count']['post'] = $forum->getPostCount();
            
            foreach($this->get('symbb.core.forum.flag')->findAll($forum) as $flag){
                $array['flags'][$flag->getFlag()] = $this->getFlagAsArray($flag);
            }
            foreach($forum->getChildren() as $child){
                $array['children'][] = $this->getForumAsArray($child);
            }
            $lastPosts = $this->get('symbb.core.forum.manager')->findPosts($forum, 10);
            foreach($lastPosts as $post){
                $array['lastPosts'][] = $this->getPostAsArray($post);
            }
            $helper = $this->container->get('vich_uploader.templating.helper.uploader_helper');
            if($forum->getImageName()){
                $array['backgroundImage'] = $helper->asset($forum, 'image');
            }
            $array['seo']['name'] = $forum->getSeoName();
            
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#CREATE_TOPIC', $forum, $this->getUser());
            $writeAccess = $this->get('symbb.core.access.manager')->hasAccess();
            
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_FORUM#CREATE_POST', $forum, $this->getUser());
            $writePostAccess = $this->get('symbb.core.access.manager')->hasAccess();

            $array['access'] = array(
                'createTopic' => $writeAccess,
                'createPost' => $writePostAccess
            );
            
            $array['ignored'] = $this->get('symbb.core.forum.manager')->isIgnored($forum, $this->get('symbb.core.forum.flag'));
            
        }
        
        return $array;
    }
    
    /**
     * 
     * @param \SymBB\Core\ForumBundle\Entity\Forum\Flag $flag
     * @return array
     */
    protected function getFlagAsArray($flag){
        $array = array();
        $flagName = $flag->getFlag();
        if($flagName == 'new'){
            $array['type'] = 'success';
        } else if($flagName == 'answered'){
            $array['type'] = 'warning';
        } else {
            $array['type'] = $flagName;
        }
        $array['title'] = $this->get('translator')->trans($flagName, array(), 'symbb_frontend');
        return $array;
    }
    
    /**
     * 
     * @param \SymBB\Core\ForumBundle\Entity\Post $post
     * @return array
     */
    protected function getPostAsArray(\SymBB\Core\ForumBundle\Entity\Post $post = null){
        
        $array = array();
        $array['id'] = 0;
        $array['topic']['id'] = 0;
        $array['name'] = '';
        $array['changed'] = 0;
        $array['text'] = '';
        $array['rawText'] = '';
        $array['signature'] = '';
        $array['seo']['name'] = '';
        $array['access']['edit'] = false;
        $array['access']['delete'] = false;
            
        if(is_object($post)){
            $array['id'] = $post->getId();
            $array['topic']['id'] = $post->getTopic()->getId();
            $array['name'] = $post->getName();
            $array['changed'] = $this->getCorrectTimestamp($post->getChanged());
            $array['author'] = $this->getAuthorAsArray($post->getAuthor());
            $array['seo']['name'] = $post->getSeoName();
            $array['rawText'] = $post->getText();
            $array['text'] = $this->get('symbb.core.post.manager')->parseText($post);
            $array['signature'] = $this->get('symbb.core.user.manager')->getSignature($post->getAuthor());
            
            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_POST#EDIT', $post, $this->getUser());
            $editAccess = $this->get('symbb.core.access.manager')->hasAccess();

            $this->get('symbb.core.access.manager')->addAccessCheck('SYMBB_POST#DELETE', $post, $this->getUser());
            $deleteAccess = $this->get('symbb.core.access.manager')->hasAccess();

            $array['access'] = array(
                'edit' => $editAccess,
                'delete' => $deleteAccess
            );
        } else {
            $array['author'] = $this->getAuthorAsArray();
        }
        
        return $array;
    }
    
    /**
     * 
     * @param \SymBB\Core\UserBundle\Entity\UserInterface $author
     * @return array
     */
    protected function getAuthorAsArray(\SymBB\Core\UserBundle\Entity\UserInterface $author = null){
        $array = array();
        $array['id'] = 0;
        $array['username'] = '';
        $array['avatar'] = '';
        $array['count']['topic'] = 0;
        $array['count']['post'] = 0;
        $array['created'] = 0;
        
        if(is_object($author)){
            $array['id'] = $author->getId();
            $array['username'] = $author->getUsername();
            $array['avatar'] = $this->get('symbb.core.user.manager')->getAbsoluteAvatarUrl($author);
            $array['count']['topic'] = $this->get('symbb.core.user.manager')->getTopicCount($author);
            $array['count']['post'] = $this->get('symbb.core.user.manager')->getPostCount($author);
            $array['created'] = $this->getCorrectTimestamp($author->getCreated());
        }
        
        return $array;
    }
}