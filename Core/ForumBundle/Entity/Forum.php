<?php
/**
*
* @package symBB
* @copyright (c) 2013 Christian Wielath
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace SymBB\Core\ForumBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="forums")
 * @ORM\Entity()
 */
class Forum extends \SymBB\Core\AdminBundle\Entity\Base\CrudAbstract
{
    /**
     * @ORM\Column(type="integer", unique=true)
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=10))
     */
    protected $type = 'forum';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $link;
    
    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $countLinkCalls = false;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;
    
    /**
     * @ORM\OneToMany(targetEntity="Forum", mappedBy="parent", cascade={"persist", "remove"})
     * @ORM\OrderBy({"position" = "ASC", "name" = "ASC"})
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Forum", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     */
    protected $parent;
    
    /**
     * @ORM\OneToMany(targetEntity="Topic", mappedBy="forum")
     * @ORM\OrderBy({"changed" = "ASC", "created" = "ASC"})
     */
    protected $topics;
    
    /**
     * @ORM\OneToMany(targetEntity="SymBB\Core\ForumBundle\Entity\Forum\Flag", mappedBy="forum")
     */
    private $flags;
    
    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $active = true;
    
    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $showSubForumList = false;
    
    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $entriesPerPage = 20;
    
    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $position = 0;

    protected $topicCount = null;
    protected $postCount = null;


    public function __construct() {
        $this->children = new ArrayCollection();
    }

    ############################################################################
    # Default Get and Set
    ############################################################################
    public function getLink(){return $this->link;}
    public function setLink($value){$this->link = $value;}
    public function getCountLinkCalls(){return $this->countLinkCalls;}
    public function setCountLinkCalls($value){$this->countLinkCalls = $value;}
    public function getDescription(){return $this->description;}
    public function setDescription($value){$this->description = $value;}
    public function isActive(){return $this->active;}
    public function setActive($value){$this->active = $value;}
    public function getType(){return $this->type;}
    public function setType($value){$this->type = $value;}
    public function hasShowSubForumList(){return $this->showSubForumList;}
    public function setShowSubForumList($value){$this->showSubForumList = $value;}
    public function getEntriesPerPage(){return $this->entriesPerPage;}
    public function setEntriesPerPage($value){$this->entriesPerPage = $value;}
    public function setParent($object){$this->parent = $object;}
    public function getParent(){return $this->parent;}
    public function getChildren(){return $this->children;}
    public function getTopics(){return $this->topics;}
    public function getFlags(){return $this->flags;}
    ############################################################################
    

    public function getTopicCount(){
        $count = $this->topicCount;
        if($count === null){
            $topics = $this->getTopics();
            $count  = $this->topicCount = count($topics);
        }
        return $count;
    }
    
    public function getPostCount(){
        $count = $this->postCount;
        if($count === null){
            $topics = $this->getTopics();
            $count  = 0;
            foreach($topics as $topic){
                $posts  = $topic->getPosts();
                $count  += count($posts);
            }
            $this->topicCount = $count;
        }
        return $count;
    }
    
    public function getLastPost(){
        $lastPost = null;
        $topics = $this->getTopics();
        foreach($topics as $topic){
            $currLastPost = $topic->getPosts()->last();
            if(!$lastPost || $currLastPost->getChanged() > $lastPost->getChanged()){
                $lastPost = $currLastPost;
            }
        }
        return $lastPost;
    }
    
    public function hasTopics(){
        $topics = $this->getTopics();
        if($topics->count() > 0){
            return true;
        }
        return false;
    }
}