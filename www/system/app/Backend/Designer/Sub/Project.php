<?php
/**
 * Project controller
 * @author Kirill A Rgorov 2011
 * @package Disigner
 * @subpackage Sub
 */
class Backend_Designer_Sub_Project extends Backend_Designer_Sub
{
	/**
	 * Check if project is loaded
	 */
	public function checkloadedAction()
	{
		if($this->_session->keyExists('loaded') && $this->_session->get('loaded'))
			Response::jsonSuccess(array('file'=>$this->_session->get('file')));
		else
			Response::jsonError();
	}
	/**
	 * Load project
	 */
	public function loadAction()
	{
		$file = Request::post('file', 'string', false);

		try{
			$project = Designer_Factory::loadProject($this->_config, $file);
		}catch (Exception $e){;
			Response::jsonError($this->_lang->WRONG_REQUEST);
		}

		$this->_session->set('loaded' , true);
		$this->_session->set('project' , serialize($project));
		$this->_session->set('file' , $file);

		Response::jsonSuccess();
	}

	/**
	 * Clear report session
	 */
	public function closeAction()
	{
		$this->_session->remove('loaded');
		$this->_session->remove('project');
		$this->_session->remove('file');
		Response::jsonSuccess();
	}

	/**
	 * Save report
	 */
	public function saveAction()
	{
		$this->_checkLoaded();

		if($this->_storage->save($this->_session->get('file'), $this->_getProject()))
			Response::jsonSuccess();
		else
			Response::jsonError($this->_lang->CANT_WRITE_FS. ' ' .$this->_session->get('file'));
	}
	/**
	 * Get project config
	 */
	public function loadconfigAction()
	{
		$this->_checkLoaded();
		$config = $this->_getProject()->getConfig();

		if(isset($config['files']) && !empty($config['files'])){
			foreach ($config['files'] as &$item){
				$item = array('file'=>$item);
			}unset($item);
		}else{
		    $config['files'] = array();
		}

		if(isset($config['langs']) && !empty($config['langs']))
		{
		    foreach ($config['langs'] as &$item){
		        $item = array('name'=>$item);
		    }unset($item);
		}else{
		    $config['langs'] = array();
		}

		$locManager =  new Backend_Localization_Manager($this->_configMain);
		$langs = $locManager->getLangs(false);

		$paths = array();
		foreach ($langs as $k=>$v)
		{
		  $pos = strpos($v, '/');
		  if(strpos($v, '/')===false)
		      continue;

		  $path= substr($v, $pos+1);
		  if(!isset($paths[$path])){
		    $paths[$path] = array('name'=>$path);
		  }
		}

		$config['langsList'] = array_values($paths);
		Response::jsonSuccess($config);
	}
	/**
	 * Set project config option
	 */
	public function setconfigAction()
	{
		$project = $this->_getProject();
		$project->files = array();
		$project->langs = array();

		$names = array_keys($project->getConfig());

		foreach ($names as $name){

			if($name == 'files'){
				$value = Request::post($name, 'array', array());
				$project->$name = $value;
			}elseif($name == 'langs'){
			    $value = Request::post($name, 'array', array());
			    $project->$name = $value;
		    }else{
				$value = Request::post($name, 'string', false);
				if($value!==false)
					$project->$name = $value;
			}
		}
		$this->_storeProject();
		Response::jsonSuccess();
	}
	/**
	 * Add object to the project tree
	 */
	public function addobjectAction()
	{
		$this->_checkLoaded();
		$name = Request::post('name', 'alphanum', false);
		$class = Request::post('class', 'alphanum', false);
		$parent = Request::post('parent', 'alphanum', 0);
		$class = ucfirst($class);
		$project = $this->_getProject();

		if(!strlen($parent))
			$parent = 0;

		if($name == false)
			Response::jsonError($this->_lang->INVALID_VALUE);
		/*
		 * Check if name starts with digits
		 */
		if(intval($name)>0)
			Response::jsonError($this->_lang->INVALID_VALUE);

		/*
		 * Skip parent for window , store and model
		 */
		$rootClasses = array('Window','Store','Data_Store','Data_Store_Tree','Model');
		$isWindowComponent = strpos($class,'Component_Window_')!==false;
		if(in_array($class, $rootClasses , true) || $isWindowComponent)
			$parent = 0;
		/*
		 * Check if parent object exists and can has childs
		 */
		if(!$project->objectExists($parent) || !Designer_Project::isContainer($project->getObject($parent)->getClass()))
			$parent = 0;

		if(!$name || !$class)
			Response::jsonError($this->_lang->WRONG_REQUEST);

		if($project->objectExists($name))
			Response::jsonError($this->_lang->SB_UNIQUE);

		$class = ucfirst($class);
		$object = Ext_Factory::object($class);
		$object->setName($name);

		if($isWindowComponent)
			$object->extendedComponent(true);

		$this->_initDefaultProperties($object);

		if($isWindowComponent)
		{
			$tab = Ext_Factory::object('Panel');
			$tab->setName($object->getName().'_generalTab');
			$tab->frame=false;
		    $tab->border=false;
		    $tab->layout='anchor';
		    $tab->bodyPadding=3;
		    $tab->bodyCls='formBody';
		    $tab->anchor= '100%';
		    $tab->fieldDefaults="{
		            labelAlign: 'right',
		            labelWidth: 160,
		            anchor: '100%'
		     }";
			if(!$project->addObject($parent, $object) || !$project->addObject($object->getName(), $tab))
				Response::jsonError($this->_lang->INVALID_VALUE);
		}else{
			if(!$project->addObject($parent, $object))
				Response::jsonError($this->_lang->INVALID_VALUE);
		}

		if(in_array($class, Designer_Project::$hasDocked , true)){

			$dockObject = Ext_Factory::object('Docked');
			$dockObject->setName($name.'__docked');
			$project->addObject($name, $dockObject);
		}

		if(in_array($class, Designer_Project::$hasMenu , true))
		{
			$menuObject = Ext_Factory::object('Menu');
			$menuObject->setName($name.'__menu');
			$project->addObject($name, $menuObject);
		}

		if(strpos($object->getClass(), 'Form_Field')!==false && $object->getConfig()->isValidProperty('name'))
			$object->name = $name;


		/**
		 * Store auto configuration
		 */
		if(strpos($object->getClass(), 'Data_Store')!==false)
		{
		    $object->autoLoad = false;

		    $reader = Ext_Factory::object('Data_Reader_Json');
		    $reader->root = 'data';
		    $reader->totalProperty = 'count';
		    $reader->idProperty = 'id';

		    $proxy = Ext_Factory::object('Data_Proxy_Ajax');
		    $proxy->type = 'ajax';
		    $proxy->reader = $reader;
		    $proxy->writer =  '';
		    $proxy->startParam='pager[start]';
		    $proxy->limitParam='pager[limit]';
		    $proxy->sortParam='pager[sort]';
		    $proxy->directionParam='pager[dir]';
		    $proxy->simpleSortMode= true;
		    $object->proxy = $proxy;
		}

		$this->_storeProject();
		Response::jsonSuccess();
	}
	/**
	 * Add object instance to the project
	 */
	public function addinstanceAction()
	{
	  $parent = Request::post('parent', 'alphanum', '');
	  $name = Request::post('name', 'alphanum', false);
	  $instance = Request::post('instance', 'alphanum', false);
	  
	  $errors = array();
	  
	  if(empty($name))
	    $errors['name'] = $this->_lang->get('CANT_BE_EMPTY');
	    
	  $project = $this->_getProject();
	  
	  if(!$project->objectExists($instance))
	    $errors['instance'] = $this->_lang->get('INVALID_VALUE');
	  	  
	  $instanceObject = $project->getObject($instance);
	  
	  if($instanceObject->isInstance() || !Designer_Project::isVisibleComponent($instanceObject->getClass()))	  
	    $errors['instance'] = $this->_lang->get('INVALID_VALUE');
	  	  
	  /*
	   * Skip parent for window , store and model
	   */
	  $rootClasses = array('Window','Store','Data_Store','Data_Store_Tree','Model');
	  $isWindowComponent = strpos($instanceObject->getClass(),'Component_Window_')!==false;
	  
	  if(in_array($instanceObject->getClass(), $rootClasses , true) || $isWindowComponent)
	      $parent = 0;
	  /*
	   * Check if parent object exists and can has childs
	  */
	  if(!$project->objectExists($parent) || !Designer_Project::isContainer($project->getObject($parent)->getClass()))
	      $parent = 0;
	    
	  if($project->objectExists($name))
	    $errors['name'] = $this->_lang->get('SB_UNIQUE');
	    
	  if(!empty($errors))
	    Response::jsonError($this->_lang->get('FILL_FORM') , $errors);
	  
	  $object = Ext_Factory::object('Object_Instance');
	  $object->setObject($instanceObject);
	  $object->setName($name);
	  
	  if(!$project->addObject($parent, $object))
	      Response::jsonError($this->_lang->get('CANT_EXEC'));
	  
	  $this->_storeProject();
	  Response::jsonSuccess();	  
	}
	/**
	 * Generate and add components
	 */
	public function addtemplateAction()
	{

	  $this->_checkLoaded();
	  $name = Request::post('name', 'alphanum', false);
	  $adapter = Request::post('adapter', 'alphanum', false);
	  $parent = Request::post('parent', 'alphanum', 0);
	  
	  if(!class_exists($adapter))
	    Response::jsonError($this->_lang->get('WRONG_REQUEST').' invalid adapter '.$adapter);
	  
	  $adapterObject = new $adapter();
	  
      if(!$adapterObject instanceof Backend_Designer_Generator_Component)
        Response::jsonError($this->_lang->get('WRONG_REQUEST').' invalid adapter interface');
       
	  $project = $this->_getProject();
	  
	  if(!strlen($parent))
	      $parent = 0;
	  
	  if($name == false)
	      Response::jsonError($this->_lang->INVALID_VALUE);
	  /*
	   * Check if name starts with digits
	  */
	  if(intval($name)>0)
	      Response::jsonError($this->_lang->INVALID_VALUE);
	  
	  /*
	   * Check if parent object exists and can has childs
	  */
	  if(!$project->objectExists($parent) || !Designer_Project::isContainer($project->getObject($parent)->getClass()))
	      $parent = 0;

	  if(!$adapterObject->addComponent($project, $name , $parent))
	    Response::jsonError($this->_lang->get('CANT_EXEC'));
	  
	  $this->_storeProject();
	  Response::jsonSuccess();
	}
	
	/**
	 * Set default properties for new object
	 * @param Ext_Object $object
	 * @return void
	 */
	protected function _initDefaultProperties(Ext_Object $object)
	{
		$oClass = $object->getClass();
		switch ($oClass){
			case 'Window':
					$object->width = 300;
					$object->height = 300;
				break;
			case 'Button':
					$object->text = $object->getName();
				break;
			case 'Grid':
					$object->columnLines = true;
				break;

		}

		if(strpos($oClass , 'Component_Window_')!==false){
				$object->width = 700;
				$object->height = 700;

		}
	}

	/**
	 * Files list
	 */
	public function fslistAction()
	{
		$path = Request::post('node', 'string', '');
		$path = str_replace('.','', $path);

		$dirPath = $this->_config->get('js_path');

		if(!is_dir($dirPath))
			Response::jsonArray(array());

		$files = File::scanFiles($dirPath . $path, array('.js','.css') , false , File::Files_Dirs);

		if(empty($files))
			Response::jsonArray(array());

		$list = array();

		foreach($files as $k=>$fpath)
		{
			$text  = basename($fpath);
			if($text ==='.svn')
				continue;

			$obj = new stdClass();
			$obj->id =str_replace($dirPath, '', $fpath);
			$obj->text = $text;

			if(is_dir($fpath))
			{
				$obj->expanded = false;
				$obj->leaf = false;
			}
			else
			{
				$obj->leaf = true;
			}
			$list[] = $obj;
		}
		Response::jsonArray($list);
	}

	public function projectlistAction()
	{
		$path = Request::post('node', 'string', '');
		$path = str_replace('.','', $path);

		$dirPath = $this->_config->get('configs');

		if(!is_dir($dirPath))
			Response::jsonArray(array());

		$files = File::scanFiles($dirPath . $path, array('.dat') , false , File::Files_Dirs);

		if(empty($files))
			Response::jsonArray(array());

		$list = array();

		foreach($files as $k=>$fpath)
		{
			$text  = basename($fpath);
			if($text ==='.svn')
				continue;

			$obj = new stdClass();
			$obj->id =str_replace($dirPath, '', $fpath);
			$obj->text = $text;

			if(is_dir($fpath))
			{
				$obj->expanded = false;
				$obj->leaf = false;
			}
			else
			{
				$obj->leaf = true;
			}
			$list[] = $obj;
		}
		Response::jsonArray($list);
	}
	/**
	 * Get list of project components that can be instantiated
	 */
	public function caninstantiateAction()
	{
	  $list = array();
	  $project = $this->_getProject();
	  $items = $project->getObjects();
	  
	  foreach ($items as $name => $object)
	  {
	     if(!$object->isInstance() && $object->isExtendedComponent() && Designer_Project::isVisibleComponent($object->getClass())){
	       $list[] = array('name'=>$name);
	     }  
	  }
	  Response::jsonSuccess($list);
	}

}