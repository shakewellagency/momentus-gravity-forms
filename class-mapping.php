<?php

class_exists( 'GFForms' ) or die();

use Gravity_Forms\Gravity_Forms\Settings\Settings;

class GF_Mapping{
    public function display() {

    }
}

require_once( ABSPATH . '/wp-admin/includes/class-wp-list-table.php' );

class GFMappingTable extends WP_List_Table {
    public $form;

    public function __construct($form) {
        $this->form = $form;

        $this->_column_headers =array(
            array(
                'cb'      => '',
                'field'    => __( 'Field', 'gravityforms' ),
                'parameter'    => __( 'Parameter', 'gravityforms' ),
                'value' => __( 'Default Value', 'gravityforms' ),
            ),
            array(),
            array( 'field' => array( 'field', false ) ),
            'field',
        );
        parent::__construct();
    }

    public function display(){
        $singular = rgar( $this->_args, 'singular' );
        $this->display_tablenav( 'top' );
    ?>
        <table class="wp-list_table widefat fixed striped table-view-list toplevel_page_gf_edit_forms">
            <thead>
			<tr>
				<?= $this->print_column_headers() ?>
			</tr>
			</thead>
			<tfoot>
			<tr>
				<?= $this->print_column_headers( false ) ?>
			</tr>
			</tfoot>
            <tbody id="the-list"<?php if ( $singular ) {
                echo " class='list:$singular'";
            } ?>>
            <?php $this->display_rows_or_placeholder(); ?>
            </tbody>
        </table>
<?php
    }

    protected function extra_tablenav( $which ) {

        if ( $which !== 'top' ) {
            return;
        }

        printf(
            '<div class="alignright"><a href="%s" class="button">%s</a></div>',
            esc_url( add_query_arg( array( 'cid' => 0 ) ) ),
            esc_html__( 'Add Mapping', 'gravityforms' )
        );

    }
}
