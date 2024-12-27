<?php

/**
 * @author Shakewell Team
 * @copyright Copyright (c) Shakewell (https://www.shakewell.agency/)
 */
GFForms::include_feed_addon_framework();
class GFMomentousFeedAddOn extends GFFeedAddOn {

    const MOMENTOUS_CLIENT_ID_LABEL = 'Client Id';
    const MOMENTOUS_CLIENT_ID_NAME = 'client_id';
    const MOMENTOUS_URL_LABEL = 'API url';
    const MOMENTOUS_URL_NAME = 'api_url';
    const MOMENTOUS_CLIENT_KEY_LABEL = 'Client Key';
    const MOMENTOUS_CLIENT_KEY_NAME = 'client_key';
    const MOMENTOUS_CLIENT_SECRET_LABEL = 'Client Secret';
    const MOMENTOUS_CLIENT_SECRET_NAME = 'client_secret';


    protected $_version                  = GF_MOMENTOUS_FEED_ADDON_VERSION;
    protected $_min_gravityforms_version = '1.9.16';
    protected $_slug                     = 'momentous';
    protected $_path                     = 'gravityforms-momentous/momentousaddon.php';
    protected $_full_path                = __FILE__;
    protected $_title                    = 'Gravity Forms Momentous Feed Add-On';
    protected $_short_title              = 'Momentous Feed Add-On';

    private static $_instance = null;

    public function init() {
        parent::init();

        $this->add_delayed_payment_support(
            array(
                'option_label' => esc_html__( 'Subscribe contact to service x only when payment is received.', 'momentous' ),
            )
        );
    }

    public function scripts()
    {
        $scripts = [
            [
                'handle' => 'momentous_js',
                'src' => $this->get_base_url() . '/js/momentous.js',
                'version' => $this->_version,
                'deps' => ['jquery'],
                'enqueue' => array(
                    [
                        'admin_page' => array('form_settings'),
                        'tab' => 'momentous'
                    ]
                )
            ]
        ];
        return array_merge(parent::scripts(), $scripts);
    }

    public function plugin_settings_fields()
    {

        return array(
            array(
                'title' => __('Settings', 'gravityforms-momentous'),
                'description' => '<p>' . esc_html__('Sync data to Momentous API', 'gravityforms-momentous') . '</p>',
                'fields' => array(
                    array(
                        'name' => self::MOMENTOUS_URL_NAME,
                        'label' => esc_html__(self::MOMENTOUS_URL_LABEL, 'gravityforms-momentous'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'feedback_callback' => array($this, 'plugin_settings_fields_feedback_callback'),
                    ),
                    array(
                        'name' => self::MOMENTOUS_CLIENT_ID_NAME,
                        'label' => esc_html__(self::MOMENTOUS_CLIENT_ID_LABEL, 'gravityforms-momentous'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'feedback_callback' => array($this, 'plugin_settings_fields_feedback_callback'),
                    ),
                    array(
                        'name' => self::MOMENTOUS_CLIENT_KEY_NAME,
                        'label' => esc_html__(self::MOMENTOUS_CLIENT_KEY_LABEL, 'gravityforms-momentous'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'feedback_callback' => array($this, 'plugin_settings_fields_feedback_callback'),
                    ),
                    array(
                        'name' => self::MOMENTOUS_CLIENT_SECRET_NAME,
                        'label' => esc_html__(self::MOMENTOUS_CLIENT_SECRET_LABEL, 'gravityforms-momentous'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'feedback_callback' => array($this, 'plugin_settings_fields_feedback_callback'),
                    ),
                ),
            ),
        );
    }

    public function feed_settings_fields() {

        $formId = rgget('id');
        $form = GFAPI::get_form($formId);
        $formFields = [];
        if (isset($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!empty($field['label'])) {
                    $formFields[] = [
                        'label' => $field['label'],
                        'value' => $field['id']
                    ];
                }
            }
        }
        return [
            [
                'title' => esc_html__('Entity Mapping', 'gravityforms-momentous'),
                'class' => 'entity-mapping-section',
                'fields' => [
                    [
                        'label' => esc_html__("Momentous Entity", 'gravityforms-momentous'),
                        'type' => 'select',
                        'name' => 'entity',
                        'class' => 'momentous-entity-selector',
                        'tooltip' => esc_html__('Please select an entity', 'gravityforms-momentous'),
                        'choices' => [
                            [
                                'label' => esc_html__('-- Please select --', 'gravityforms-momentous'),
                                'value' => ''
                            ],
                            [
                                'label' => esc_html__('Account', 'gravityforms-momentous'),
                                'value' => 'accounts'
                            ],
                            [
                                'label' => esc_html__('Opportunity', 'gravityforms-momentous'),
                                'value' => 'opportunities'
                            ]
                        ]
                    ],
                ]
            ],
            [
                'title' => esc_html__('Field Mapping', 'gravityforms-momentous'),
                'style' => 'display:none',
                'class' => 'field-mapping-section',
                'fields' => [
                    [
                        'label' => esc_html__($form['title'] . ' fields', 'gravityforms-momentous'),
                        'type' => 'select',
                        'name' => 'form_field',
                        'tooltip' => esc_html__('This is the tooltip', 'gravityforms-momentous'),
                        'choices' => $formFields
                    ],
                    [
                        'label' => esc_html__('Momentous entity fields', 'gravityforms-momentous'),
                        'type' => 'select',
                        'name' => 'entity_field',
                        'class' => 'momentous-entity-field-selector',
                        'tooltip' => esc_html__('', 'gravityforms-momentous'),
                    ],
                    [
                        'label' => esc_html__('Default value', 'gravityforms-momentous'),
                        'type' => 'text',
                        'name' => 'default_value',
                        'tooltip' => esc_html__('', 'gravityforms-momentous'),
                    ],
                ]
            ],
        ];
    }

    public function process_feed($feed, $entry, $form) {
        //Silence is golden
    }



    public function get_mapped_fields($formId) {
        global $wpdb;
        $form_filter = is_numeric( $formId ) ? $wpdb->prepare( 'AND form_id=%d', absint( $formId ) ) : '';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gf_addon_feed
                               WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC",  $this->get_slug()
        );
        $results = $wpdb->get_results( $sql, ARRAY_A );
        $mapping = [];
        foreach($results as $result) {
            $meta = json_decode($result['meta'], true);
            $entity = $meta['entity'];
            $entry = [
                'form_field' => $meta['form_field'],
                'entity_field' => $meta['entity_field'],
                'default_value' => $meta['default_value']
            ];
            if (!isset($mapping[$entity])) {
                $mapping[$entity] =[];
            }
            $mapping[$entity][]= $entry;
        }
        return $mapping;
    }

    public function process_mapped_fields($mapping, $inputs) {
        $result = [];
        foreach ($mapping as $entity => $fields) {
            foreach($fields as $field) {
                $result[$entity][$field['entity_field']] = !empty(rgar($inputs, $field['form_field'])) ? rgar($inputs, $field['form_field']) : $field['default_value'];
            }
        }
        return $result;
    }

    public function send($requests) {
        require_once 'includes/class-gf-momentous-api.php';
        $settings = $this->get_saved_plugin_settings();
        $api = new GF_Momentous_API($settings);
        foreach ($requests as $endpoint => $body) {
            $api->request(ucfirst($endpoint), $body, 'POST');
        }
    }

    private function get_saved_plugin_settings() {
        $prefix  = $this->is_gravityforms_supported( '2.5' ) ? '_gform_setting' : '_gaddon_setting';
        $api_url = rgpost( "{$prefix}_api_url" );
        $api_key = rgpost( "{$prefix}_api_key" );

        $settings = $this->get_plugin_settings();
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        if ( ! $this->is_plugin_settings( $this->_slug ) || ! ( $api_url && $api_key ) ) {
            return $settings;
        }

        $settings['api_url'] = esc_url( $api_url );
        $settings['api_key'] = sanitize_title( $api_key );

        return $settings;
    }

    public function feed_list_columns() {
        return array(
            'entity'  => esc_html__( 'Entity', 'momentous' ),
            'entity_field' => esc_html__( 'Parameter', 'momentous' ),
            'form_field' => esc_html__( 'Form field', 'momentous' ),
            'default_value' => esc_html__( 'Default value', 'momentous' ),
        );
    }



    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFMomentousFeedAddOn();
        }

        return self::$_instance;
    }
}
