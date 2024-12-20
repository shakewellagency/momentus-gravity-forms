<?php

class_exists('GFForms') or die();


require_once(ABSPATH . '/wp-admin/includes/class-wp-list-table.php');

class GFMappingTable extends WP_List_Table
{
    private $_mapping;

    private $_columns;

    function __construct($args = array())
    {
        parent::__construct($args);
        $this->_column_headers =[
             [
                 'cb' => '<input type="checkbox" />',
                 'entity' => 'Entity',
                 'parameter' => 'Parameter',
                 'field' => 'Field',
                 'value' => 'Default value'
             ]
        ];
    }

    function get_columns() {
        return $this->_column_headers[0];
    }

    function prepare_items()
    {
        $this->items = isset($this->_mapping) ? $this->_mapping : [];
    }
}
