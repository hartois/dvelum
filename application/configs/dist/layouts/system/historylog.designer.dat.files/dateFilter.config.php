<?php return array (
  'id' => 'dateFilter',
  'class' => 'Ext_Component_Filter',
  'extClass' => 'Component_Filter',
  'name' => 'dateFilter',
  'state' => 
  array (
    'config' => 
    array (
      'store' => 'dataStore',
      'storeField' => 'date',
      'local' => false,
      'autoFilter' => true,
      'isExtended' => false,
    ),
    'viewObject' => 
    array (
      'class' => 'Form_Field_Date',
      'state' => 
      array (
        'config' => 
        array (
          'format' => 'd.m.Y',
          'submitFormat' => 'Y-m-d',
          'width' => 90.0,
        ),
      ),
    ),
  ),
); 