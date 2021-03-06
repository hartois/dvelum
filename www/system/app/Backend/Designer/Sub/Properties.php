<?php
class Backend_Designer_Sub_Properties extends Backend_Designer_Sub
{
	/**
	 * Get object properties
	 */
	public function listAction()
	{
		$this->_checkLoaded();
		$object = $this->_getObject();
		$project = $this->_getProject();

		$class = $object->getClass();
		$properties = $object->getConfig()->__toArray();

		/*
		 * Hide unused properties
		 */
		switch ($class){
			case 'Docked':
			         unset($properties['items']);
				break;
			case 'Object_Instance':
    			     unset($properties['defineOnly']);
    			     unset($properties['isExtended']);
    			     unset($properties['listeners']);
			    break;
		}

		unset($properties['extend']);

		if(isset($properties['dockedItems']))
			unset($properties['dockedItems']);

		if(isset($properties['menu']))
			unset($properties['menu']);

		Response::jsonSuccess($properties);
	}
	/**
	 * Set object property
	 */
	public function setpropertyAction()
	{
		$this->_checkLoaded();

		$object = $this->_getObject();
		$project = $this->_getProject();

		$property = Request::post('name', 'string', false);
		$value = Request::post('value', 'raw', false);


		if(!$object->isValidProperty($property))
			Response::jsonError();

		if($property === 'isExtended')
		{
		  $parent = $project->getParent($object->getName());
		  if($parent){
		    Response::jsonError($this->_lang->get('CANT_EXTEND_CHILD'));
		  }
		}

		$object->$property = $value;

		$this->_storeProject();
		Response::jsonSuccess();
	}
	/**
	 * Get list of existing ORM dictionaries
	 */
	public function listdictionariesAction()
	{
		$manager = new Dictionary_Manager();
		$list = $manager->getList();
		$data = array();
		if(!empty($list))
			foreach ($list as $k=>$v)
				$data[] = array('id'=>$v,'title'=>$v);
		Response::jsonArray($data);
	}

	/**
	 * Get list of store filds
	 */
	public function storefieldsAction()
	{
		$this->_checkLoaded();
		$object = $this->_getObject();
		$project = $this->_getProject();

		if(!$object->isValidProperty('store') || !$project->objectExists($object->store))
			Response::jsonArray(array());

		$store = $project->getObject($object->store);

		$fields = array();

		if($store->isValidProperty('model') && strlen($store->model) && $project->objectExists($store->model))
		{
			$model = $project->getObject($store->model);

			if($model->isValidProperty('fields'))
			{
			    $fields = $model->fields;
			    if(is_string($fields))
				    $fields = json_decode($model->fields , true);
			}
		}

		if(empty($fields) && $store->isValidProperty('fields'))
		{
			    $fields = $store->fields;

			    if(empty($fields))
			        $fields = array();

			    if(is_string($fields))
				    $fields = json_decode($fields , true);
		}

		$data = array();
		if(!empty($fields))
		{
			foreach ($fields as $item)
			    if(is_object($item))
    				$data[] = array('id'=>$item->name);
			    else
			        $data[] = array('id'=>$item['name']);
		}

		Response::jsonSuccess($data);
	}

	/**
	 * Get list of existing form field adapters
	 */
	public function listadaptersAction()
	{
		$data = array();
		$autoloaderPaths =  $this->_configMain['autoloader'];
		$autoloaderPaths = $autoloaderPaths['paths'];
		$files = File::scanFiles($this->_config->get('components').'/Field',array('.php'),true,File::Files_Only);

		if(!empty($files))
		{
			foreach ($files as $item)
			{
				$class = Utils::classFromPath(str_replace($autoloaderPaths, '', $item));
				$data[] = array('id'=>$class , 'title'=>str_replace($this->_config->get('components').'/', '', substr($item,0,-4)));
			}
		}
		Response::jsonArray($data);
	}

	/**
	 * Change field type
	 */
	public function changetypeAction()
	{
		$this->_checkLoaded();
		$object = $this->_getObject();
		$type = Request::post('type', 'string', false);
		$adapter = Request::post('adapter', 'string', false);
		$dictionary = Request::post('dictionary', 'string', false);

		if($type === 'Form_Field_Adapter')
		{
			$newObject = Ext_Factory::object($adapter);
			/*
			 * Invalid adapter
			 */
			if(!$adapter || !strlen($adapter) || !class_exists($adapter))
				Response::jsonError($this->_lang->INVALID_VALUE , array('adapter'=>$this->_lang->INVALID_VALUE ));

			if($adapter==='Ext_Component_Field_System_Dictionary')
			{
				/*
				 * Inavalid dictionary
				 */
				if(!$dictionary || !strlen($dictionary))
					Response::jsonError($this->_lang->INVALID_VALUE , array('dictionary'=>$this->_lang->INVALID_VALUE));

				$newObject->dictionary = $dictionary;
				$newObject->displayField = 'title';
				$newObject->valueField = 'id';

			}
		}
		else
		{
			$newObject = Ext_Factory::object($type);
			/*
			 * No changes
			 */
			if($type === $object->getClass())
				Response::jsonSuccess();
		}

		Ext_Factory::copyProperties($object , $newObject);
		$newObject->setName($object->getName());
		$this->_getProject()->replaceObject($object->getName() , $newObject);
		$this->_storeProject();
		Response::jsonSuccess();
	}

}
