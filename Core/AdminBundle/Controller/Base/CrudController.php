<?

namespace SymBB\Core\AdminBundle\Controller\Base;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


abstract class CrudController extends Controller 
{
    private $formEntity;
    protected $entityBundle = 'SymBBCoreForumBundle';
    protected $templateBundle = 'SymBBTemplateAcpBundle';
    protected $entityName = '';
    protected $formClass = '';

    

    public function listAction($parent = null){
        $entityList     = $this->findListEntities($parent);
        $breadcrum      = false;
        $path           = '_'.$this->entityBundle.'_'.$this->entityName;
        $path           = strtolower($path);
        
        if($parent){
            $repository             = $this->getRepository();
            $parentEntity           = $repository->find($parent);
            if(is_object($parentEntity)){
                $breadcrum      = array();
                $names          = $parentEntity->getExtendNameArray();
                $uri            = $this->get('router')->generate($path.'_list');
                $breadcrum[]    = '<a href="'.$uri.'">'.$this->get('translator')->trans('Übersicht', array(), 'list').'</a>';
                foreach($names as $id => $name){
                    $uri = $this->get('router')->generate($path.'_list_child', array('parent' => $id));
                    $breadcrum[] = '<a href="'.$uri.'">'.$name.'</a>';
                }
                $breadcrum = implode(' - ', $breadcrum);
            }
        }
        $params = array('entityList' => $entityList, 'breadcrum' => $breadcrum);
        $params = $this->addListParams($params);
        
        return $this->render(
            $this->templateBundle.':'.$this->entityName.':list.html.twig',
            $params
        );
	}
    
    public function sortAction(){
        $request                = $this->getRequest();
        $return                 = array('success' => 0);
        if($request->isMethod('POST'))
        {
            $repository = $this->getRepository();
            $em         = $this->get('doctrine')->getEntityManager('symbb');
            $entries    = (array)$request->get('entries');
            $i          = 0;
            foreach($entries as $entry){
                $entry      = explode('_', $entry);
                $entityId   = end($entry);
                $entity     = $repository->findOneById($entityId);
                if($entity){
                    $entity->setPosition($i);
                    $em->persist($entity);
                    $i++;
                }
            }
            $em->flush();
            $return['success'] = 1;
        }
        $json = json_encode($return);
        $response = new \Symfony\Component\HttpFoundation\Response($json);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function newAction()
	{
        return $this->editAction();
	}
    
    public function editAction()
	{
        $request = $this->getRequest();
        if($request->isMethod('POST'))
        {
            return $this->saveAction();
        } else {
            $form   = $this->getForm();
            $entity = $this->getFormEntity();
            return $this->editCallback($form, $entity);
        }
	}
    

    public function saveAction()
    {
        $request                = $this->getRequest();
        $form                   = $this->getForm();
        
        if($request->isMethod('POST'))
        {
            $form->bind($request);
            $entity = $this->getFormEntity();

            if($form->isValid() && $request->get('save'))
            {
                $em = $this->get('doctrine')->getEntityManager('symbb');
                $em->persist($entity);
                $em->flush();
                
                return $this->listAction();
            }
            else
            {
                return $this->editCallback($form, $entity);
            }
        }
    }
    
        /**
     * Entity object for the form
     * Dont load the object twice and load from this method
     * 
     * @return Object
     */
    protected function getFormEntity()
    {
        if($this->formEntity === null)
        {
            $request                = $this->getRequest();
            $entityId               = (int)$request->get('id');
            $repository             = $this->getRepository();

            if($entityId > 0)
            {
                // edit form
                $entity				= $repository->findOneById($entityId);
            }
            else
            {    
                // new form, return empty entity
                $entity_class_name  = $repository->getClassName();
                $entity				= new $entity_class_name();
            }

            $this->formEntity      = $entity;
        }
        
        return $this->formEntity;
    }

    protected function getForm()
    {
        $entity                 = $this->getFormEntity();        
        $form = $this->createForm(new $this->formClass, $entity);
        return $form;
    }
    
    
    protected function addListParams($params){
        return $params;
    }
    
    protected function addFormParams($params){
        return $params;
    }

    protected function getRepository(){
        $repo = $this->get('doctrine')->getRepository($this->entityBundle.':'.$this->entityName, 'symbb');
        return $repo;
    }
    
    protected function findListEntities($parent = null){
        if($parent === null){
            $entityList = $this->getRepository()->findAll();  
        } else {
            $entityList = $this->getRepository()->findBy(array('parent' => $parent));  
        }
        return $entityList;
    }
    
    protected function editCallback($form, $entity){
        
        $params = array(
                        'entity' => $entity,
                        'form'  => $form->createView(),
                    );
        $params = $this->addFormParams($params);
        
        return $this->render($this->templateBundle.':'.$this->entityName.':edit.html.twig', $params);
    }
}