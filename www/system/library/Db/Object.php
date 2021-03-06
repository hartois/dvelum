<?php
/*
 * DVelum project http://code.google.com/p/dvelum/ , http://dvelum.net
 * Copyright (C) 2011  Kirill A Egorov kirill.a.egorov@gmail.com
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
 * Database Object class. ORM element.
 * @author Kirill Egorov 2011  DVelum project http://code.google.com/p/dvelum/ , http://dvelum.net
 * @package Db_Object
 * @uses  Config , Model , Data_Filter , Zend_Db_Adapter , Utils
 */
class Db_Object
{
   /**
	* Error log adapter
	* @var Log
	*/
	static protected $_log = false;

    protected $_name;
    /**
     * @var Db_Object_Config
     */
    protected $_config;

    protected $_id;
    protected $_primaryKey;
    protected $_data = array();
    protected $_updates = array();
    protected $_errors = array();

    /**
     * Insert ID
     * @var integer
     */
    protected $_insertId = false;

    /**
     * Access Control List Adapter
     * @var Db_Object_Acl
     */
    protected $_acl = false;

    /**
     * @var Model
     */
    protected $_model;

    /**
     * Loaded version of VC object
     * @var integer
     */
    protected $_version = 0;

    /**
     * The object constructor takes its name and identifier,
     * (the parameter is not required), if absent,
     * there will be created a new object. If ORM lacks the object with the specified
     * identifier, an Exception will show up
     * Using this method is highly undesirable,
     * the factory method Db_Object::factory() is more advisable to use
     * @param string $name
     * @param bool|int $id - optional
     * @throws Exception
     */
    public function __construct($name, $id = false)
    {
        $this->_name = strtolower($name);
        $this->_id = $id;

        $this->_config = Db_Object_Config::getInstance($name);
        $this->_primaryKey = $this->_config->getPrimaryKey();
        $this->_model = Model::factory($name);
        $this->_acl = $this->_config->getAcl();

        if($this->_id){
           $this->_checkCanRead();
           $this->_loadData();
        }else{
           if($this->_acl)
            $this->_checkCanCreate();
        }
    }

    /**
     * Load object data
     * @throws Exception
     */
    protected function _loadData()
    {
       $data =  $this->_model->getItem($this->_id);

       if(empty($data))
            throw new Exception('Cannot find object '.$this->_name.':'.$this->_id);

       $this->_setRawData($data);
    }

    /**
     * Set raw data from storage
     * @param array $data
     */
    protected function _setRawData(array $data)
    {
        unset($data[$this->_primaryKey]);
        foreach($data as $field => &$value)
        {
            if($this->_config->isMultiLink($field) && strlen($value))
                $value = unserialize($value);

            if($this->getConfig()->isBoolean($field))
            {
                if($value)
                    $value = true;
                else
                    $value = false;
            }
        }
        unset($value);
        $this->_data = $data;
    }

    /**
     * Get object fields
     * @return array
     */
    public function getFields()
    {
        return array_keys($this->_config->get('fields'));
    }

    /**
     * Get the object data, returns the associative array ‘field name’
     * @return array
     */
    public function getData()
    {
      if($this->_acl)
          $this->_checkCanRead();

        $data = $this->_data;
        $data[$this->_primaryKey] = $this->_id;

        foreach ($this->_updates as $k=>$v)
        	$this->_data[$k] = $v;

        return $data;
    }
    /**
     * Get object name
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get object identifier
     * @return integer
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Get the full name of the database storing the object data (with prefix)
     * @return string
     */
    public function getTable()
    {
    	return $this->_model->table();
    }

    /**
     * Check if there are object property changes
	   * not saved in the database
     * @return boolean
     */
    public function hasUpdates()
    {
         return !empty($this->_updates);
    }
    /**
     * Get ORM configuration object (data structure helper)
     * @return Db_Object_Config
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * Get updated, but not saved object data
     * @return array
     */
    public function getUpdates()
    {
        if($this->_acl)
          $this->_checkCanRead();

        return $this->_updates;
    }

    /**
     * Set the object identifier (existing DB ID)
     * @param integer $id
     */
    public function setId($id)
    {
        if($this->_acl)
            $this->_checkCanEdit();

        $this->_id = (integer) $id;
    }

    /**
     * Commit the object data changes (without saving)
     * @return void
     */
    public function commitChanges()
    {
        if($this->_acl)
            $this->_checkCanEdit();

        if(empty($this->_updates))
            return;

         foreach ($this->_updates as $k=>$v)
             $this->_data[$k] = $v;
    }

    /**
     * Check if the object field exists
     * @param string $name
     * @return boolean
     */
    public function fieldExists($name)
    {
        return $this->_config->fieldExists($name);
    }

    /**
     * Get the related object name for the field
	   * (available if the object field is a link to another object)
     * @param string $field - field name
     * @return string
     */
    public function getLinkedObject($field)
    {
        if(!$this->_config->isLink($field))
            return false;

        $cfg = $this->_config->getFieldConfig($field);
        return $cfg['link_config']['object'];
    }

    /**
     * Validate link field
     * @param string $name
     * @param mixed $value
     * @return boolean
     */
    protected  function _validateLink($name , $value)
    {
        $propConf = $this->_config->getFieldConfig($name);
        switch ($propConf['link_config']['link_type'])
        {
            case 'object':
            case 'multy':
                   return self::objectExists($propConf['link_config']['object'], $value);
                break;
            case 'dictionary':
            	$dictionary = Dictionary::getInstance($propConf['link_config']['object']);
            	return $dictionary->isValidKey($value);
            	break;
        }
        return false;
    }

    /**
     * Check if the listed objects exist
     * @param string $name
     * @param mixed integer/array $ids
     * @return boolean
     */
    static public function objectExists($name , $ids)
    {
    	if(!Db_Object_Config::configExists($name))
    		return false;

        try {
            $cfg = Db_Object_Config::getInstance($name);
        }catch (Exception $e){
            return false;
        }

        if(!is_array($ids))
            $ids = array($ids);

        $model = Model::factory($name);
        $data = $model->getItems($ids);

        if(empty($data))
            return false;


        $data = Utils::fetchCol($cfg->getPrimaryKey(), $data);

        foreach ($ids as $v)
            if(!in_array(intval($v) , $data , true))
                 return false;
        return true;
    }

    /**
     * Set the object properties using the associative array of fields and values
     * @param array $values
     * @throws Exception
     * @return void
     */
    public function setValues(array $values)
    {
        if(!empty($values))
            foreach ($values as $k => $v)
                $this->set($k, $v);
    }

    /**
     * Set the object field val
     * @param string $name
     * @param mixed $value
     * @return bool
     * @throws Exception
     */
    public function set($name , $value)
    {
        if($this->_acl)
            $this->_checkCanEdit();

        $propConf = $this->_config->getFieldConfig($name);
        $validator = $this->getConfig()->getValidator($name);

       if($validator && !call_user_func_array(array($validator , 'validate') , array($value)))
           throw new Exception('Invalid value for field '. $name);

       /*
        * Validate value by fields type in config
        */
       if($this->_config->isMultiLink($name))
       {
            if(is_array($value) && !empty($value[0])){
                 if(!$this->_validateLink($name , $value))
                     throw new Exception('Invalid property value');
                  $value = $this->_collectLinksData($name , $value);
            } else {
                $value = '';
            }
        }
        elseif ($this->_config->isDictionaryLink($name))
        {
          if($this->_config->isRequired($name) && !strlen($value))
            throw new Exception('Field '. $name.' cannot be empty');

          if(strlen($value))
          {
            $fieldConfig = $this->_config->getFieldConfig($name);
            $dictionary = Dictionary::getInstance($fieldConfig['link_config']['object']);

            if(!$dictionary->isValidKey($value))
              throw new Exception('Invalid dictionary value ['.$name.']');
          }
        }
        elseif ($this->_config->isLink($name))
        {

        	if(is_object($value))
        		$value = $value->__toString();

          if(is_array($value))
              throw new Exception('Invalid value for field '. $name);

        	if($this->_config->isRequired($name) && !strlen($value))
        		throw new Exception('Field '. $name.' cannot be empty');

        	$value = intval($value);

        	if($value != 0 && !$this->_validateLink($name, $value))
        		throw new Exception('Invalid value for field '. $name);

        	if($value == 0)
        	    $value = null;

        }
        // mysql strict mode patch
        elseif($this->_config->isBoolean($name))
        {
        	$value = intval((boolean)$value);
        }
        elseif (is_null($value) && $this->_config->isNull($name))
        {
            $value = null;
        }
        else
        {
        	$value = Db_Object_Property::filter($propConf, $value);
        }

        if(isset($propConf['db_len']) && $propConf['db_len']){
            if(strlen($value) > $propConf['db_len'])
                throw new Exception('The field value exceeds the allowable length ['.$name.']');
            if($propConf['db_type'] == 'bit' && (strlen($value) > $propConf['db_len'] || strlen($value) < $propConf['db_len']))
				throw new Exception('Invalid length for bit value ['.$name.']');
        }

        if(isset($this->_data[$name] ) && $this->_data[$name]  === $value){
            if(isset($this->_updates[$name]))
                unset($this->_updates[$name]);
            return true;
        }

        $this->_updates[$name] = $value;

        return true;
    }

    protected function _collectLinksData($field , $ids)
    {
        $ids = array_map('intval', $ids);
        $linkedObject = new Db_Object($this->getLinkedObject($field));
        $pKey = $linkedObject->getConfig()->getPrimaryKey();
        /*
         * Init object model
         */
        $model = Model::factory($linkedObject->getName());
        /*
         * Find title field for link
         */
        $title = $pKey;
        $lt = $linkedObject->getConfig()->getLinkTitle();
        if($lt)
            $title = $lt;

        $data = $model->getItems($ids , array('id'=>$pKey , 'title'=>$title));

        if(!empty($data))
            $data = Utils::rekey('id', $data);

        $result = array();
        foreach ($ids as $v){
            if(!isset($data[$v]))
                throw new Exception('Invalid link');

            $result[$v] = array('id'=>$v , 'title'=>$data[$v]['title']);
        }
        return $result;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @throws Exception
     * @return void
     */
    public function __set($key , $value)
    {
    	if($key===$this->_primaryKey)
    		$this->setId($value);
    	else
        	$this->set($key, $value);
    }

    public function __isset($key)
    {
      if($key === $this->_primaryKey)
        return isset($this->_id);

      if(!isset($this->_data[$key]) && !isset($this->_updates[$key]))
        return false;

      return true;
    }
    /**
     * @param string $key
     * @throws Exception
     * @return mixed
     */
    public function __get($key)
    {
    	if($key===$this->_primaryKey)
    		return $this->getId();

    	return $this->get($key);
    }

    /**
     * Get the object field value
 	 * If field value was updated method returns new value
     * otherwise returns old value
     * @param string $name - field name
     * @throws Exception
     * @return mixed
     */
    public function get($name)
    {
         if($this->_acl)
            $this->_checkCanRead();

         if(!$this->fieldExists($name))
            throw new Exception('Invalid property requested ['.$name.']');

         $value = null;

         if(isset($this->_data[$name]))
              $value = $this->_data[$name];

         if(isset($this->_updates[$name]))
              $value = $this->_updates[$name];

         return $value;
    }

    /**
     * Get the initial object field value (received from the database)
     * whether the field value was updated or not
     * @param string $name - field name
     * @throws Exception
     * @return mixed
     */
    public function getOld($name)
    {
      if($this->_acl)
          $this->_checkCanRead();

      if(!$this->fieldExists($name))
          throw new Exception('Invalid property requested ['.$name.']');
      return $this->_data[$name];
    }

   /**
    * Save changes
    * @param boolean $log  - log changes
    * @param boolean $useTransaction — using a transaction when changing data is optional.
    * If data update in your code is carried out within an external transaction
    * set the value to  false,
    * otherwise, the first update will lead to saving the changes
    * @return boolean;
    */
    public function save($log = true , $useTransaction = true)
    {
        if($this->_acl){
            try{
              $this->_checkCanEdit();
            }catch (Exception $e){
              $this->_errors[] = $e->getMessage();

              if(self::$_log)
                  self::$_log->log($e->getMessage());
              return false;
            }
        }

    	$store  = $this->_model->getStore();
    	if(self::$_log)
    		$store->setLog(self::$_log);

    	if($this->_config->isReadOnly())
    	{
    	    $text = 'ORM :: cannot save readonly object '. $this->_config->getName();
    	    $this->_errors[] = $text;
    	    if(self::$_log)
    	        self::$_log->log($text);
    	    return false;
    	}

    	$emptyFields = $this->_hasRequired();
    	if($emptyFields!==true)
    	{
    	    $text = 'ORM :: Fields can not be empty. '.$this->getName().' ['.implode(',', $emptyFields).']';
    	    $this->_errors[] = $text;
    		if(self::$_log)
    			self::$_log->log($text);
    		return false;
    	}

    	$values = $this->validateUniqueValues();

    	if(!empty($values))
    	{
  	        foreach($values as $k => $v)
  	        {
  	            $text = 'The Field value should be unique '.$k . ':' . $v;
  	            $this->_errors[] = $text;
  	        }

    	    if(self::$_log)
    	        self::$_log->log($this->getName() . ' ' . implode(', ' , $this->_errors));

    	    return false;
    	}

      try {
            if(!$this->getId()){

                if($this->_config->isRevControl()){
                    $this->date_created = date('Y-m-d H:i:s');
                    $this->date_updated = date('Y-m-d H:i:s');
                    $this->author_id = User::getInstance()->id;
                }

                $id = $store->insert($this , $log , $useTransaction);
                $this->setId($id);
                return (integer) $id;
            } else {

                if($this->_config->isRevControl()){
                    $this->date_updated = date('Y-m-d H:i:s');
                    $this->editor_id = User::getInstance()->id;
                }
                return (integer) $store->update($this , $log , $useTransaction);
            }
       }catch (Exception $e){
            $this->_errors[] = $e->getMessage();
        	if(self::$_log)
        		self::$_log->log($e->getMessage());
            return false;
       }
    }

    /**
     * Deleting an object
	 * @param boolean $useTransaction — using a transaction when changing data is optional.
	 * If data update in your code is carried out within an external transaction
	 * set the value to  false,
	 * otherwise, the first update will lead to saving the changes
	 * @return boolean - success
     */
    public function delete($useTransaction = true)
    {
        if($this->_acl){
          try{
              $this->_checkCanDelete();
          }catch (Exception $e){
              $this->_errors[] = $e->getMessage();

              if(self::$_log)
                  self::$_log->log($e->getMessage());
              return false;
          }
        }
        $store  = $this->_model->getStore();
        return $store->delete($this, $useTransaction);
    }

    /**
     * Serialize multilink properties
     * @param array $data
     * @return array
     */
    public function serializeLinks($data)
    {
        foreach ($data as $k=>&$v)
            if($this->_config->isMultiLink($k) && !empty($v))
                $v = serialize($v);

        return $data;
    }

    /**
     * Validate unique fields, object field groups
	   * Returns errors array or returns false, is used for ExtJS forms
     * @property boolean $new
     * @return mixed false / array
     */
    public function validateUniqueValues()
    {
        $uniqGroups = array();

        foreach ($this->_config->get('fields') as $k=>$v)
        {
            if($k===$this->_primaryKey)
                continue;

            if(!$this->_config->isUnique($k))
            	continue;

            $value  = $this->get($k);
            if(is_array($value))
                $value = serialize($value);

            if(is_array($v['unique']))
            {
                foreach ($v['unique'] as $val)
                {
                    if(!isset($uniqGroups[$val]))
                        $uniqGroups[$val] = array();

                 	$uniqGroups[$val][$k] = $value;
                }
            }
            else
            {
            	$v['unique'] = strval($v['unique']);

            	if(!isset($uniqGroups[$v['unique']]))
                    $uniqGroups[$v['unique']] = array();

                $uniqGroups[$v['unique']][$k] = $value;
            }
        }

        if(empty($uniqGroups))
            return false;

        $db = $this->_model->getDbConnection();

        foreach ($uniqGroups as $group)
        {
             $sql = $db->select()
                        ->from($this->_model->table() , array('count'=>'COUNT(*)'));

             if($this->getId())
                 $sql->where(' '.$db->quoteIdentifier($this->_primaryKey).' != ?', $this->getId());

             foreach ($group as $k=>$v){
                 if($k===$this->_primaryKey)
             	 	  continue;
                 $sql->where($db->quoteIdentifier($k) . ' =?' , $v);
             }

             $count = $db->fetchOne($sql);

             if($count > 0){
                 foreach ($group as $k=>&$v){
                     $v = Lang::lang()->get('SB_UNIQUE');
                 }unset($v);
                 return $group;
             }
         }
         return false;
    }

    /**
     * Convert object into string representation
     * @return string
     */
    public function __toString()
    {
        return strval($this->getId());
    }

    /**
     * Factory method of object creation is preferable to use, cf. method  __construct() description
     *
     * @param string $name
     * @param integer | array $id
     * @throws Exception
     * @return Db_Object | array
     */
    static public function factory($name , $id = false)
    {
       if(!is_array($id))
          return new Db_Object($name , $id);

       $list = array();
       $model = Model::factory($name);
       $data = $model->getItems($id);

       foreach ($data as $item)
       {
          $o = new Db_Object($name);
          $o->setId($item[$o->_primaryKey]);
          $o->_setRawData($item);
          $list[$item[$o->_primaryKey]] = $o;
       }
       return $list;
    }

    /**
     * Enable error log. Set log adapter
     * @param Log $log
     */
    static public function setLog(Log $log)
    {
        self::$_log = $log;
    }

   /**
    * Check for required fields
    * @return boolean|array
    */
    protected function _hasRequired()
    {
        $emptyFields = array();
        $fields = $this->getFields();

        foreach ($fields as $name)
        {
          if(!$this->_config->isRequired($name))
              continue;

          $val = $this->get($name);
          if(!strlen((string)$val))
              $emptyFields[]= $name;
		}

		if(empty($emptyFields))
            return true;
		else
            return $emptyFields;
    }
    /**
     * Get errors
     * @return array
     */
    public function getErrors()
    {
      return $this->_errors;
    }

    protected function _checkCanRead()
    {
      if($this->_acl && !$this->_acl->canRead($this))
          throw new Exception('You do not have permission to view data in this object ['.$this->getName().':'.$this->getId().'].');
    }

    protected function _checkCanEdit()
    {
      if($this->_acl && !$this->_acl->canEdit($this))
          throw new Exception('You do not have permission to edit data in this object ['.$this->getName().':'.$this->getId().'].');
    }

    protected function _checkCanDelete()
    {
        if($this->_acl && !$this->_acl->canDelete($this))
            throw new Exception('You do not have permission to delete this object ['.$this->getName().':'.$this->getId().'].');
    }

    protected function _checkCanCreate()
    {
        if($this->_acl && !$this->_acl->canCreate($this))
            throw new Exception('You do not have permission to create object ['.$this->getName().'].');
    }

    protected function _checkCanPublish()
    {
    	if($this->_acl && !$this->_acl->canPublish($this))
    		throw new Exception('You do not have permission to publish object ['.$this->getName().'].');
    }
    /**
     * Unpublish VC object
     * @param boolean $log  - log changes
     * @param boolean $useTransaction — using a transaction when changing data is optional.
     * @return boolean
     */
    public function unpublish($log = true , $useTransaction = true)
    {
    	if($this->_acl){
            try{
              $this->_checkCanPublish();
            }catch (Exception $e){
              $this->_errors[] = $e->getMessage();

              if(self::$_log)
                  self::$_log->log($e->getMessage());
              return false;
            }
        }

    	$store  = $this->_model->getStore();
    	if(self::$_log)
    		$store->setLog(self::$_log);

    	$this->published_version = 0;
    	$this->published = false;
    	return $store->unpublish($this , $log , $useTransaction);
    }

    /**
     * Publish VC object
     * @param int $version - optional, default current version
     * @param boolean $log  - log changes
     * @param boolean $useTransaction — using a transaction when changing data is optional.
     * @return boolean
     */
    public function publish($version = false , $log = true , $useTransaction = true)
    {
    	if($this->_acl){
    		try{
    			$this->_checkCanPublish();
    		}catch (Exception $e){
    			$this->_errors[] = $e->getMessage();

    			if(self::$_log)
    				self::$_log->log($e->getMessage());
    			return false;
    		}
    	}

    	$store  = $this->_model->getStore();

    	if(self::$_log)
    		$store->setLog(self::$_log);

    	if($version && $version !== $this->getVersion())
    	{
    		try{
    			$this->loadVersion($version);
    		}
    		catch (Exception $e)
    		{
    			$this->_errors[] = $e->getMessage();

    			if(self::$_log)
    				self::$_log->log($e->getMessage());
    			return false;
    		}
    	}
    	$this->published = true;

    	if(empty($this->date_published))
    		$this->set('date_published' , date('Y-m-d H:i:s'));

    	$this->editor_id = User::getInstance()->id;
    	$this->published_version = $this->getVersion();
    	return $store->publish($this , $log , $useTransaction);
    }
    /**
     * Get loaded version
     * @return integer
     */
    public function getVersion()
    {
    	return $this->_version;
    }
    /**
     * Load version
     * @param integer $vers
     * @return boolean
     * @throws Exception
     */
    public function loadVersion($vers)
    {
    	$this->rejectChanges();
    	$versionObject  = $this->_model->getStore()->getVersionObjectName();

    	$vc = Model::factory($versionObject);

    	$data = $vc->getData($this->getName() , $this->getId() , $vers);

    	$pKey = $this->_config->getPrimaryKey();

    	if(isset($data[$pKey]))
    		unset($data[$pKey]);

    	if(empty($data))
    		throw new Exception('Cannot load version for ' . $this->getName() . ':' . $this->getId() . '. v:' . $vers);

    	foreach($data as $k => $v)
    	{
    		if($this->fieldExists($k))
    		{
    			if($this->_config->isMultiLink($k) && ! empty($v))
    				$v = array_keys($v);

    			try{
    				if(!$this->_config->isSystemField($k))
    					$this->set($k , $v);
    			}catch(Exception $e){
    			   throw new Exception('Cannot load version data ' . $this->getName() . ':' . $this->getId() . '. v:' . $vers.'. This version contains incompatible data. ' . $e->getMessage());
    			}
    		}
    	}
    	$this->_version = $vers;
    }
    /**
     * Reject changes
     */
    public function rejectChanges()
    {
    	$this->_updates = array();
    }
    /**
     * Save object as new version
     * @param boolean $log  - log changes
     * @param boolean $useTransaction — using a transaction when changing data is optional.
     * @return boolean
     */
    public function saveVersion($log = true , $useTransaction = true)
    {
    	if(!$this->_config->isRevControl()){
    		return $this->save($log ,$useTransaction);
    	}

    	if($this->_acl)
    	{
    		try{
    			$this->_checkCanEdit();
    		}catch (Exception $e){
    			$this->_errors[] = $e->getMessage();

    			if(self::$_log)
    				self::$_log->log($e->getMessage());
    			return false;
    		}
    	}

    	if(!$this->getId())
    	{
    		$this->published = false;
    		$this->author_id = User::getInstance()->getId();
    		if(!$this->save(true , $useTransaction))
    			return false;
    	}

    	$store  = $this->_model->getStore();

    	if(self::$_log)
    		$store->setLog(self::$_log);

		$vers = $store->addVersion($this , $log , $useTransaction);

		if($vers){
			$this->_version = $vers;
			return true;
		}else{
			return false;
		}
    }

    /**
     * Get Access control List
     * @return Db_Object_Acl | false
     */
    public function getAcl()
    {
        return $this->_acl;
    }
    /**
     * Set insert id for object (Should not exist in the database)
     * @param int $id
     */
    public function setInsertId($id)
    {
    	$this->_insertId = $id;
    }
    /**
     * Get insert ID
     * @return integer
     */
    public function getInssertId()
    {
    	return $this->_insertId;
    }
}
