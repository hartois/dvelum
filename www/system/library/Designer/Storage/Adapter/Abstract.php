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
 * Abstract Base for Designer_Storage 
 * @author Kirill A Egorov 2012
 */
abstract class Designer_Storage_Adapter_Abstract
{
	/**
	 * Adapter config
	 * @var Config_Abstract - optional
	 */
	protected $_config;
	
	/**
	 * @param array $config, optional
	 */
	public function __construct($config = false)
	{
		$this->_config = $config;
	}
	/**
	 * Load Db_Query object
	 * @param string $id
	 * @throws Exception
	 * @return Db_Query
	 */
	abstract public function load($id);

	/**
	 * Save Db_Query object
	 * @param string $id
	 * @param Db_Query $obj
	 * @return boolean
	 */
	abstract public function save($id , Designer_Project $obj);

	/**
	 * Delete Designer_Project object
	 * @param string $id
	 * @return boolean
	 */
	abstract public function delete($id);

	/**
	 * Pack object
	 * @param Designer_Project $query
	 * @return string
	 */
	protected function _pack(Designer_Project $query)
	{
		return base64_encode(serialize($query));
	}

	/**
	 * Unpack object
	 * @param string $data
	 * @throws Exception
	 * @return Designer_Project
	 */
	protected function _unpack($data)
	{
		$query = unserialize(base64_decode($data));
		
		if(! $query instanceof Designer_Project)
			throw new Exception('Invalid data type');
		
		return $query;
	}
}