<?php
class Backend_Designer_Sub_Objects extends Backend_Designer_Sub
{
	/**
	 * Get panels tree
	 */
	public function visualListAction()
	{
		$this->_checkLoaded();
		$project = $this->_getProject();
		Response::jsonArray($this->_fillContainers($project->getTree()));
	}
	/**
	 * Get list of project objects by object type
	 */
	public function listAction()
	{
		$this->_checkLoaded();
		$acceptedTypes = array('visual','stores','models','menu');
		$type = Request::post('type', 'string', false);
		$project = $this->_getProject();

		if(!in_array($type , $acceptedTypes , true))
			Response::jsonError($this->_lang->WRONG_REQUEST);

		switch ($type)
		{

			case 'stores' :
			                $addInstances = Request::post('instances', Filter::FILTER_BOOLEAN, false);
							$stores = $project->getStores();
							$list = array();

							$cfg = $project->getConfig();
							if(!empty($stores))
							{
								foreach($stores as $object)
								{
									$name = $object->getName();
									$title = $name;
									$class = $object->getClass();

									if($class === 'Data_Store_Tree')
										$title.= ' (Tree)';

									$list[] = array('id'=>$name, 'title'=>$title , 'objClass'=>$class);

									// append instance token
									if($addInstances && $object->isExtendedComponent())
									   $list[] = array('id'=>Designer_Project_Code::$NEW_INSTANCE_TOKEN.' ' . $name, 'title'=>Designer_Project_Code::$NEW_INSTANCE_TOKEN.' ' . $name, 'objClass'=>$cfg['namespace'] .'.' . $name);
								}
							}
							Response::jsonSuccess($list);
							break;

			case 'models' :
							$models = array_keys($project->getModels());
							$list = array();
							if(!empty($models))
								foreach($models as $name)
									$list[] = array('id'=>$name, 'title'=>$name , 'objClass'=>'Model');
							Response::jsonSuccess($list);
							break;
			case 'menu'  :
			                $menu = array_keys($project->getMenu());
							$list = array();
							if(!empty($menu))
								foreach($menu as $name)
									$list[] = array('id'=>$name, 'title'=>$name , 'objClass'=>'Menu');
							Response::jsonSuccess($list);
		}
	}
 	/**
     * Fill childs data array for tree panel
     * @param Tree $tree
     * @param mixed $root
     * @return array
     */
    protected function _fillContainers(Tree $tree , $root = 0 , $exceptStorage = true)
    {
    	   $exceptions = array('Store' , 'Data_Store' , 'Data_Store_Tree' , 'Model');
           $result = array();
           $childs = $tree->getChilds($root);

           if(empty($childs))
                return array();

           foreach($childs as $k=>$v)
           {
           		$object = $v['data'];
           		$objectClass = $object->getClass();
           		$objectName = $object->getName();

           		if($exceptStorage && in_array($objectClass , $exceptions , true))
           			continue;

           		$item = new stdClass();
                $item->id = $v['id'];

                $inst = '';
                $ext = '';

                if($object->isInstance())
                {
                  $inst = ' <span class="extInstanceLabel" data-qtip="Object instance">instance of </span>' . $object->getObject()->getName() ;
                }elseif($object->isExtendedComponent()){
                  $ext = ' <span class="extCmpLabel" data-qtip="Extended component">ext</span> ';
                  if($object->defineOnly){
                    $objectName = '<span class="extClassLabel" data-qtip="defineOnly:true">'.$objectName.'</span>';
                  }
                }

                $item->text = $ext . $objectName . ' ('.$objectClass.')' . $inst;
                $item->expanded = true;
                $item->objClass = $objectClass;
                $item->isInstance = $object->isInstance();
                $item->leaf=true;
                $item->iconCls = self::getIconClass($objectClass);
                $item->allowDrag = Designer_Project::isDraggable($objectClass);

                if($objectClass == 'Docked'){
                 	$item->iconCls = 'objectDocked';
                 	$item->text = 'Docked Items';
                }elseif ( $objectClass == 'Menu'){
                    $item->text = 'Menu Items';
                    $item->iconCls = 'menuItemsIcon';
                }

                if(Designer_Project::isContainer($objectClass) && !$object->isInstance()){
	               	$item->leaf = false;
                	$item->children = array();
                }

                if($tree->hasChilds($v['id']))
                   $item->children = $this->_fillContainers($tree ,  $v['id'] , $exceptStorage);

                $result[] = $item;
          }
          return $result;
    }
    /**
     * Get css icon clas for object
     * @param string $objClass
     */
    static public function getIconClass($objClass)
    {
    	$config = array(
    	'Docked'=>'objectDocked',
    	'Window'=>'objectWindowIcon',
    	'Text'=> 'textFieldIcon',
    	'Textarea'=>'textareaIcon',
    	'Checkbox'=>'checkboxIcon',
		'Checkboxgroup'=>'checkboxGroupIcon',
    	'Container' =>'containerIcon',
		'Time'=>'clockIcon',
		'Date'=>'dateIcon',
		'Display'=>'displayfieldIcon',
		'Fieldset'=>'fieldsetIcon',
		'Fieldcontainer'=>'fieldContainerIcon',
		'File'=>'fileIcon',
		'Htmleditor'=>'htmlEditorIcon',
		'Picker'=>'pickerIcon',
		'Radio'=>'radioIcon',
    	'Radiogroup'=>'radioGroupIcon',
        'Number'=>'numberFieldIcon',

  	    'Panel'=>'panelIcon',
  	    'Tabpanel'=>'tabIcon',
  	    'Grid'=>'gridIcon',

  	    'Form'=>'formIcon',
  	    'Form_Field_Text'=>'textFieldIcon',
  	    'Form_Field_Number'=>'textFieldIcon',
  	    'Form_Field_Hidden'=>'hiddenFieldIcon',
  	    'Form_Field_Checkbox'=>'checkboxIcon',
  	    'Form_Field_Textarea'=>'textareaIcon',
  	    'Form_Field_Htmleditor'=>'htmlEditorIcon',
  	    'Form_Field_File'=>'fileIcon',
  	    'Form_Field_Radio'=>'radioIcon',
  	    'Form_Field_Time'=>'clockIcon',
  	    'Form_Field_Date'=>'dateIcon',
  	    'Form_Fieldset'=>'fieldsetIcon',
  	    'Form_Field_Display'=>'displayfieldIcon',
  	    'Form_Fieldcontainer'=>'fieldContainerIcon',
  	    'Form_Checkboxgroup'=>'checkboxGroupIcon',
  	    'Form_Radiogroup'=>'radioGroupIcon',
  	    'Form_Field_Combobox'=>'comboboxFieldIcon',

  	    'Button'=>'buttonIcon',
  	    'Tree'=>'treeIcon',
  	    'Window'=>'windowIcon',
  	    'Store'=>'storeIcon',
  	    'Data_Store'=>'storeIcon',
  	    'Data_Store_Tree'=>'storeIcon',
  	    'Model'=>'modelIcon',

  	    'Component_Window_System_Crud'=>'objectWindowIcon',
  	    'Component_Window_System_Crud_Vc'=>'objectWindowIcon',
  	    'Component_Field_System_Searchfield'=>'olinkIcon',
  	    'Component_Field_System_Dictionary'=>'comboboxFieldIcon',
  	    'Component_Field_System_Medialibhtml'=>'textMediaFieldIcon',
  	    'Component_Field_System_Medialibitem'=>'resourceFieldIcon',
  	    'Component_Field_System_Related'=>'gridIcon',
  	    'Component_Field_System_Objectlink'=>'olinkIcon',
  	    'Component_Field_System_Objectslist'=>'gridIcon',

  	    'Toolbar'=>'toolbarIcon',
  	    'Toolbar_Separator'=>'toolbarSeparatorIcon',
  	    'Toolbar_Spacer'=>'toolbarSpacerIcon',
  	    'Toolbar_Fill'=>'toolbarFillIcon',
  	    'Toolbar_Textitem'=>'toolbarTextitemIcon',

    	'Menu' =>'menuItemsIcon',
    	'Menu_Separator' =>'menuSeparatorIcon',
    	'Menu_Item' =>'toolbarTextitemIcon',
    	'Menu_Datepicker' =>'dateIcon',
    	'Menu_Colorpicker' =>'colorPickerIcon',
    	'Menu_Checkitem' =>'checkboxIcon',

    	);

    	if(Designer_Project::isWindowComponent($objClass))
              return 'objectWindowIcon';

    	if(isset($config[$objClass])){
    		return $config[$objClass];
    	}else{
    		if(Designer_Project::isContainer($objClass))
    			return 'objectIcon';
    		else
    			return 'objectLeafIcon';
    	}
    }
    /**
     * Sort Objects tree
     */
    public function sortAction()
    {
    	$this->_checkLoaded();
        $id = Request::post('id','string',false);
        $newParent = Request::post('newparent','string',false);
        if(!strlen($newParent))
        	$newParent = 0;
        $order = Request::post('order', 'array' , array());
        $project = $this->_getProject();

        if(!$id  || !$project->objectExists($id))
            Response::jsonError($this->_lang->WRONG_REQUEST .' code1');

        $project->changeParent($id, $newParent);
        $count = 0;
        foreach ($order as $name)
        {
        	if(!$project->setItemOrder($name, $count))
        		Response::jsonError($this->_lang->WRONG_REQUEST.' code2');

        	$count ++;
        }
        $project->resortItems($newParent);

        $this->_storeProject();
        Response::jsonSuccess();
    }
    /**
     * Remove object from project
     */
    public function removeAction()
    {
    	$this->_checkLoaded();
    	$id = Request::post('id','string',false);

    	if(!$id || !strlen($id))
    		Response::jsonError($this->_lang->WRONG_REQUEST);

    	$project = $this->_getProject();

    	$object = $project->getObject($id);
    	$oClass = $object->getClass();
    	unset($object);
    	if($project->removeObject($id))
    	{
    		/*
    		 * Remove object links
    		 */
    		$propertiesToClean = array();
	    	switch ($oClass){
	    		case 'Store': $propertiesToClean[] = 'store';
	    			break;
	    		case 'Model': $propertiesToClean[] = 'model';
	    			break;
	    	}
	    	if(!empty($propertiesToClean))
	    	{
	    		$objects = $project->getObjects();
	    		foreach ($objects as $object)
	    		{
	    		    // remove object instances
	    		    if($object->isInstance() && $object->getObject()->getName() === $id){
	    		         $project->removeObject($object->getName());
	    		    }

	    			foreach ($propertiesToClean as $property)
	    			{
	    				if($object->isValidProperty($property) && $object->$property===$id)
	    					$object->$property='';
	    			}
	    		}
	    	}
	    	$project->getEventManager()->removeObjectEvents($id);
    		$this->_storeProject();
    		Response::jsonSuccess();
    	}else{
    		Response::jsonError($this->_lang->CANT_EXEC);
    	}
    }
    /**
     * Get related projects
     * @param Designer_Project $project
     * @param array & $list - result
     */
    protected function getRelatedProjects($project , & $list)
    {
      $projectConfig = $project->getConfig();

      if(isset($projectConfig['files']) && !empty($projectConfig['files']))
      {
          foreach ($projectConfig['files'] as $file)
          {
              if(File::getExt($file) === '.js' || File::getExt($file) === '.css')
                continue;

              $projectFile = $this->_config->get('configs') . $file;
              $subProject = Designer_Factory::loadProject($this->_config,  $projectFile);
              $list[] = array('project' =>$subProject , 'file'=>$file);
              $this->getRelatedProjects($subProject, $list);
          }
      }
    }
    /**
     * Get related project items Tree list
     */
    public function relatedprojectlistAction()
    {
      $this->_checkLoaded();
      $project = $this->_getProject();

      $relatedProjects = array();
      $this->getRelatedProjects($project, $relatedProjects);

      if(empty($relatedProjects))
        Response::jsonSuccess(array());

      $result = array();

      foreach ($relatedProjects as $item)
      {
        $projectConfig = $item['project']->getConfig();

        $o = new stdClass();
        $o->id =  $item['file'];
        $o->text = $item['file'] .' classes:  '.$projectConfig['namespace'].' run: '.$projectConfig['runnamespace'];
        $o->expanded = false;
        $o->objClass = '';
        $o->leaf=false;
        $o->iconCls = '';
        $o->allowDrag = false;
        $o->children = $this->_fillContainers($item['project']->getTree() , 0 , false);
        $result[] = $o;
      }

      Response::jsonArray($result);
    }

}