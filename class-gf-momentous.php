<?php

defined( 'ABSPATH' ) || die();

GFForms::include_feed_addon_framework();

class GF_Momentous extends GFAddOn {

    const MOMENTOUS_CLIENT_ID_LABEL = 'Client Id';
    const MOMENTOUS_CLIENT_ID_NAME = 'client_id';
    const MOMENTOUS_URL_LABEL = 'API url';
    const MOMENTOUS_URL_NAME = 'api_url';
    const MOMENTOUS_CLIENT_KEY_LABEL = 'Client Key';
    const MOMENTOUS_CLIENT_KEY_NAME = 'client_key';
    const MOMENTOUS_CLIENT_SECRET_LABEL = 'Client Secret';
    const MOMENTOUS_CLIENT_SECRET_NAME = 'client_secret';

    protected $_version = GF_MOMENTOUS_VERSION;
    protected $_min_gravityforms_version = GF_MOMENTOUS_MIN_GF_VERSION;
    protected $_slug = 'momentous';
    protected $_path = 'momentous/momentous.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms Momentous Add-On';
    protected $_short_title = 'Momentous';

    private static $_instance = null;


    public function plugin_settings_fields() {

        return array(
            array(
                'title'       => __( 'Settings', 'gravityforms-momentous' ),
                'description' => '<p>' . esc_html__( 'Sync data to Momentous API', 'gravityforms-momentous' ) . '</p>',
                'fields'      => array(
                    array(
                        'name'              => self::MOMENTOUS_URL_NAME,
                        'label'             => esc_html__( self::MOMENTOUS_URL_LABEL, 'gravityforms-momentous' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'required'          => true,
                        'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
                    ),
                    array(
                        'name'              => self::MOMENTOUS_CLIENT_ID_NAME,
                        'label'             => esc_html__( self::MOMENTOUS_CLIENT_ID_LABEL, 'gravityforms-momentous' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'required'          => true,
                        'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
                    ),
                    array(
                        'name'              => self::MOMENTOUS_CLIENT_KEY_NAME,
                        'label'             => esc_html__( self::MOMENTOUS_CLIENT_KEY_LABEL, 'gravityforms-momentous' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'required'          => true,
                        'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
                    ),
                    array(
                        'name'              => self::MOMENTOUS_CLIENT_SECRET_NAME,
                        'label'             => esc_html__( self::MOMENTOUS_CLIENT_SECRET_LABEL, 'gravityforms-momentous' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'required'          => true,
                        'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
                    ),
                ),
            ),
        );
    }

    public function form_settings( $form ) {
        require_once 'class-mapping.php';
        $table = new GFMappingTable($form);
        ?>
<div class="gform-settings-panel">
    <header class="gform-settings-panel__header">
		<h4 class="gform-settings-panel__title">Entity Mapping</h4>
	</header>
    <?= $table->display(); ?>
</div>

<?php
    }
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
}
