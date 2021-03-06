<?php
/*
 * DVelum project http://code.google.com/p/dvelum/ , http://dvelum.net
 * Copyright (C) 2011-2013  Kirill A Egorov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/**
 * Designer project class.
 * @author Kirill Egorov 2011
 * @package Designer
 */
class Designer_Project
{
	protected static $_containers = array(
			'Panel' ,
			'Tabpanel' ,
			'Toolbar' ,
			'Form_Fieldset' ,
			'Form_Fieldcontainer' ,
	        'Form_Checkboxgroup',
	        'Form_Radiogroup',
			'Form' ,
			'Window' ,
			'Grid' ,
			'Docked',
			'Tree',
	        'Menu',
	        'Container',

	       // menu containers
    	    'Button',
    	    'Menu_Checkitem',
    	    'Menu_Item',
    	    'Menu_Separator'
	);

	public static $hasDocked = array(
			'Panel' ,
			'Tabpanel' ,
			'Form' ,
			'Window' ,
			'Grid',
			'Tree'
	);

	public static $hasMenu = array(
		    'Button',
	        'Menu_Checkitem',
	        'Menu_Item',
	        'Menu_Separator'
	);

	public static $defines = array(
			'Window' ,
			'Model'
	)//'Form','Window','Store','Model' Designer_Project::$nonDraggable
	;

	public static $configContainers = array(
			'Form' ,
			'Fieldcontainer' ,
			'Fieldset' ,
			'Window'
	);

	protected static $_nonDraggable = array(
			'Window' ,
			'Store' ,
			'Model',
			'Data_Store_Tree',
			'Data_Store'
	);

	public static $storeClasses = array(
		'Data_Store',
		'Data_Store_Tree',
		'Store'
	);

	/**
	 * Objects tree
	 * @var Tree
	 */
	protected $_tree;

	/**
	 * Project config
	 * @var array
	 */
	protected $_config = array(
			'namespace' => 'appClasses' ,
			'runnamespace' => 'appRun' ,
			'actionjs' => '',
			'files'=>array(),
	        'langs'=>array()
	);
	/**
	 * Events Manager
	 * @var Designer_Project_Events
	 */
	protected $_eventManager = false;
	/**
	 * Methods Manager
	 * @var Designer_Project_Methods
	 */
	protected $_methodManager = false;

	public function __construct()
	{
		$this->_tree = new Tree();
	}

	/**
	 * Check if object is Window comonent
	 * @param string $class
	 * @return boolean
	 */
	static public function isWindowComponent($class)
	{
		if(strpos($class , 'Component_Window') !== false)
			return true;
		else
			return false;
	}

	/**
	 * Check if object can has parent
	 * @param string $class
	 * @return boolean
	 */
	static public function isDraggable($class)
	{
		if(in_array($class , self::$_nonDraggable , true) || self::isWindowComponent($class))
			return false;
		else
			return true;
	}

	/**
	 * Check if object is container
	 * @param string $class
	 * @return boolean
	 */
	static public function isContainer($class)
	{
		if(in_array($class , self::$_containers , true) || self::isWindowComponent($class))
			return true;
		else
			return false;
	}

	static public function isVisibleComponent($class)
	{
	  if(in_array($class , self::$storeClasses , true) || $class=='Model' && strpos($class, 'Data_') !== false){
	    return false;
	  }
	  return true;
	}

	/**
	 * Add Ext_Object to the project
	 * @param string $parent - parant object name or "0" for root
	 * @param Ext_Object $object
	 * @return boolean - success flag
	 */
	public function addObject($parent , Ext_Object $object)
	{
		if(strlen($parent) && $parent !== 0 && !$this->objectExists($parent) || in_array($object->getClass(),self::$_nonDraggable,true))
			$parent = 0;

		return $this->_tree->addItem($object->getName() , $parent , $object);
	}

	/**
	 * Get project events Manager
	 * @return Designer_Project_Events
	 */
	public function getEventManager()
	{
		if($this->_eventManager === false)
			$this->_eventManager = new Designer_Project_Events();
		return 	$this->_eventManager;
	}

	/**
	 * Get project methods Manager
	 * @return Designer_Project_Methods
	 */
	public function getMethodManager()
	{
	  if($this->_methodManager === false)
	      $this->_methodManager = new Designer_Project_Methods();
	  return $this->_methodManager;
	}

	/**
	 * Remove object from project
	 * @param string $name
	 * @return boolean - success flag
	 */
	public function removeObject($name)
	{
		return $this->_tree->removeItem($name);
	}

	/**
	 * Replace object
	 * @param string $name - old object name
	 * @param Ext_Object $newObject
	 */
	public function replaceObject($name , Ext_Object $newObject)
	{
		$this->_tree->updateItem($name , $newObject);
	}

	/**
	 * Change object parent
	 * @param string $name - object name
	 * @param sting $newParent - new parent object name
	 * @return boolean - success flag
	 */
	public function changeParent($name , $newParent)
	{
		return $this->_tree->changeParent($name , $newParent);
	}

	/**
	 * Get project config
	 * @return array
	 */
	public function getConfig()
	{
		return $this->_config;
	}

	public function __get($name)
	{
		if(!isset($this->_config[$name]))
			trigger_error('Invalid config property requested');
		return $this->_config[$name];
	}

	public function __set($name , $value)
	{
		$this->_config[$name] = $value;
	}

	/**
	 * Set item order
	 * @param mixed $id
	 * @param integer $order
	 * @return boolean - success flag
	 */
	public function setItemOrder($id , $order)
	{
		return $this->_tree->setItemOrder($id , $order);
	}

	/**
	 * Resort tree Items
	 * @param mixed $parentId - optional, resort only item childs
	 * default - false (resort all items)
	 */
	public function resortItems($parentId = false)
	{
		$this->_tree->sortItems($parentId);
	}

	/**
	 * Check if object exists
	 * @param string $name
	 * @return boolean
	 */
	public function objectExists($name)
	{
		return $this->_tree->itemExists($name);
	}

	/**
	 * Get all objects from project tree
	 * @return array;  object indexed by name
	 */
	public function getObjects()
	{
		$items = $this->_tree->getItems();
		$data = array();
		if(!empty($items))
			foreach($items as $config)
				$data[$config['id']] = $config['data'];
		return $data;
	}

	/**
	 * Get objects tree
	 * @return Tree
	 */
	public function getTree()
	{
		return $this->_tree;
	}

	/**
	 * Get object by name
	 * @param string $name
	 * @return Ext_Object
	 */
	public function getObject($name)
	{
		$objData = $this->_tree->getItem($name);
		return $objData['data'];
	}

	/**
	 * Get list of Store objects
	 * @return array
	 */
	public function getStores($treeStores = true)
	{
		return $this->getObjectsByClass(array('Store','Data_Store','Data_Store_Tree'));
	}

	/**
	 * Get list of Model objects
	 * @return array
	 */
	public function getModels()
	{
		return $this->getObjectsByClass('Model');
	}

	/**
	 * Get list of Menu objects
	 * @return array
	 */
	public function getMenu()
	{
	    return $this->getObjectsByClass('Menu');
	}

	/**
	 * Get list of Grid objects
	 * @return array
	 */
	public function getGrids()
	{
		return $this->getObjectsByClass('Grid');
	}

	/**
	 * Get objects by class
	 * @param string|array $class
	 * @return array, indexed by object name
	 */
	public function getObjectsByClass($class)
	{
		if(!is_array($class))
			$class = array($class);

		$class = array_map('ucfirst', $class);

		$items = $this->_tree->getItems();

		if(empty($items))
			return array();

		$result = array();

		foreach($items as $config)
			if(in_array($config['data']->getClass() , $class , true))
				$result[$config['id']] = $config['data'];

		return $result;
	}

	/**
	 * Check if object has childs.
	 * @param string $name
	 * @return boolean
	 */
	public function hasChilds($name)
	{
		return $this->_tree->hasChilds($name);
	}
	/**
	 * Get object childs
	 * @param string $name
	 * @return array
	 */
	public function getChilds($name)
	{
		return $this->_tree->getChilds($name);
	}
	/**
	 * Get parent object
	 * @param string $name - object name
	 * @return string | false
	 */
	public function getParent($name)
	{
	    $parentId = $this->_tree->getParentId($name);

	    if($parentId && $this->objectExists($parentId))
	      return $parentId;
	    else
	      return false;
	}

	/**
	 * Compile project js code
	 * @param array $replace - optional
	 * @return string
	 */
	public function getCode($replace = array())
	{
		$codeGen = new Designer_Project_Code($this);
		if(!empty($replace))
		    return Designer_Factory::replaceCodeTemplates($replace, $codeGen->getCode());
		else
			return $codeGen->getCode();
	}

	/**
	 * Get object javascript source code
	 * @param string $name
	 * @param array $replace
	 * @return string
	 */
	public function getObjectCode($name , $replace = array())
	{
		$codeGen = new Designer_Project_Code($this);

		if(!empty($replace))
		{
			$k = array();
			$v = array();
			foreach ($replace as $item)
			{
				$k[] = $item['tpl'];
				$v[] = $item['value'];
			}
			return str_replace($k , $v , $codeGen->getObjectCode($name));
		}
		else
		{
			return $codeGen->getObjectCode($name);
		}
	}

	/**
	 * Get item data
	 * @param mixed $id
	 * @return array
	 */
	public function getItemData($id){
		return $this->_tree->getItemData($id);
	}
	/**
	 * Get root panels list
	 * @return array
	 */
	public function getRootPanels()
	{
		$list = $this->_tree->getChilds(0);
		$names = array();

		if(empty($list))
			return array();

		foreach($list as $k => $v)
		{
			$object = $v['data'];
			$class = $object->getClass();

			if($class === 'Object_Instance')
				$class = $object->getObject()->getClass();

			if(in_array($class , Designer_Project::$_containers , true) && $class !== 'Window' && $class!='Menu' && !Designer_Project::isWindowComponent($class))
				$names[] = $object->getName();
		}
		return $names;
	}

	/**
	 * Get ActionJs filte path
	 * @return string
	 */
	public function getActionsFile()
	{
		return str_replace(array('./','//') , '/' , $this->actionjs);
	}
	/**
	 * Create unique component id
	 * @param string $prefix
	 * @return string
	 */
	public function uniqueId($prefix)
	{
	  if(!$this->objectExists($prefix)){
	    return $prefix;
	  }
	  
	  $postfix = 1; 
	  while ($this->objectExists($prefix.$postfix)){
	    $postfix++;
	  }
	  return $prefix.$postfix;
	}
}