<?php

defined('ABSPATH') || die();

GFForms::include_feed_addon_framework();

class GF_Momentous extends GFAddOn
{

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


    public function init()
    {
        parent::init();
        //Attach events.
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

    public function form_settings_fields($form)
    {
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
                                'value' => 'account'
                            ],
                            [
                                'label' => esc_html__('Opportunity', 'gravityforms-momentous'),
                                'value' => 'opportunity'
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
                        'tooltip' => esc_html__('', 'gravityforms-momentous'),
                    ],
                    [
                        'label' => esc_html__('Default value', 'gravityforms-momentous'),
                        'type' => 'text',
                        'name' => 'default_value',
                        'tooltip' => esc_html__('', 'gravityforms-momentous'),
                    ],
                    [
                        'label' => esc_html__('', 'gravityforms-momentous'),
                        'type' => 'button',
                        'name' => 'default_value',
                        'tooltip' => esc_html__('', 'gravityforms-momentous'),
                    ]
                ]
            ],
            [
                'title' => esc_html__('Active Field Mapping', 'gravityforms-momentous'),
                'class' => 'field-active-mapping-section',
                'fields' => [
                    [
                        'label' => esc_html__( '', 'simpleaddon' ),
                        'type' => 'mapped_field_table_type',
                        'name' => 'default_field_table',
                    ]
                ]
            ]
        ];
    }
    public function settings_mapped_field_table_type( $field ) {
        require_once('class-mapping.php');
        $mapping = new GFMappingTable();
        $mapping->display();
    }



    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
}
