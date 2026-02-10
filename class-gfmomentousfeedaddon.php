<?php

/**
 * @author Shakewell Team
 * @copyright Copyright (c) Shakewell (https://www.shakewell.agency/)
 */
GFForms::include_feed_addon_framework();
class GFMomentousFeedAddOn extends GFFeedAddOn
{

    const MOMENTOUS_CLIENT_ID_LABEL = 'Client Id';
    const MOMENTOUS_CLIENT_ID_NAME = 'client_id';
    const MOMENTOUS_URL_LABEL = 'API url';
    const MOMENTOUS_URL_NAME = 'api_url';
    const MOMENTOUS_CLIENT_KEY_LABEL = 'Client Key';
    const MOMENTOUS_CLIENT_KEY_NAME = 'client_key';
    const MOMENTOUS_CLIENT_SECRET_LABEL = 'Client Secret';
    const MOMENTOUS_CLIENT_SECRET_NAME = 'client_secret';
    const MOMENTOUS_ASYNC_SENDING_NAME = 'async';
    const MOMENTOUS_ASYNC_SENDING_LABEL = 'Enable Asynchronous sending';
    const MOMENTOUS_FAILED_EMAIL_NOTIFICATION_LABEL = 'Email to receive CRON failure alerts';
    const MOMENTOUS_FAILED_EMAIL_NOTIFICATION_NAME = 'email_cron_failure_alerts';
    const MOMENTOUS_LEAD_EMAILS_LABEL = 'Lead Notification Emails (comma-separated)';
    const MOMENTOUS_LEAD_EMAILS_NAME = 'lead_notification_emails';
    const REQUEST_TABLE = 'momentous_requests';



    protected $_version                  = GF_MOMENTOUS_FEED_ADDON_VERSION;
    protected $_min_gravityforms_version = '1.9.16';
    protected $_slug                     = 'momentous';
    protected $_path                     = 'gravityforms-momentous/momentousaddon.php';
    protected $_full_path                = __FILE__;
    protected $_title                    = 'Gravity Forms Momentous Add-On';
    protected $_short_title              = 'Momentous Add-On';

    private static $_instance = null;

    public function init()
    {
        parent::init();
        $this->add_delayed_payment_support(
            array(
                'option_label' => esc_html__('Subscribe contact to service x only when payment is received.', 'momentous'),
            )
        );

        // Add metabox for abandoned entries on entry detail page
        add_filter('gform_entry_detail_meta_boxes', array($this, 'add_abandoned_entry_metabox'), 10, 3);

        // Register AJAX handlers for manual resend and lead notification
        add_action('wp_ajax_momentous_manual_resend', array($this, 'ajax_manual_resend'));
        add_action('wp_ajax_momentous_notify_leads', array($this, 'ajax_notify_leads'));

        // Enqueue scripts for entry detail page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_entry_detail_scripts'));
    }

    /**
     * Enqueue scripts for entry detail page
     */
    public function enqueue_entry_detail_scripts($hook)
    {
        if ($hook !== 'forms_page_gf_entries') {
            return;
        }
        if (empty($_GET['view']) || $_GET['view'] !== 'entry') {
            return;
        }
        wp_enqueue_script(
            'momentous_entry_detail_js',
            $this->get_base_url() . '/js/momentous-entry-detail.js',
            array('jquery'),
            $this->_version,
            true
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

                    array(
                        'name' => self::MOMENTOUS_ASYNC_SENDING_NAME,
                        'label' => esc_html__('Send settings', 'gravityforms-momentous'),
                        'type' => 'checkbox',
                        'class' => 'medium',
                        'feedback_callback' => array($this, 'plugin_settings_fields_feedback_callback'),
                        "choices" => [
                            [
                                "label" => __(self::MOMENTOUS_ASYNC_SENDING_LABEL, "gravityforms-momentous"),
                                "name"  => self::MOMENTOUS_ASYNC_SENDING_NAME,
                            ]
                        ]
                    ),
                    array(
                        'name' => self::MOMENTOUS_FAILED_EMAIL_NOTIFICATION_NAME,
                        'label' => esc_html__(self::MOMENTOUS_FAILED_EMAIL_NOTIFICATION_LABEL, 'gravityforms-momentous'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'feedback_callback' => array($this, 'plugin_settings_fields_feedback_callback'),
                    ),
                    array(
                        'name' => self::MOMENTOUS_LEAD_EMAILS_NAME,
                        'label' => esc_html__(self::MOMENTOUS_LEAD_EMAILS_LABEL, 'gravityforms-momentous'),
                        'type' => 'text',
                        'class' => 'large',
                        'required' => false,
                        'tooltip' => esc_html__('Comma-separated list of emails to receive manual entry notifications when automatic sync fails', 'gravityforms-momentous'),
                    ),
                ),
            ),
        );
    }
    public function feed_settings_fields()
    {

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
    public function process_feed($feed, $entry, $form)
    {
        //Silence is golden
    }
    public function get_mapped_fields($formId, $entry_id = null)
    {
        global $wpdb;
        $form_filter = is_numeric($formId) ? $wpdb->prepare('AND form_id=%d', absint($formId)) : '';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gf_addon_feed
                               WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC",
            $this->get_slug()
        );
        $results = $wpdb->get_results($sql, ARRAY_A);
        $mapping = [];
        foreach ($results as $result) {
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
        $this->log_debug('Mapped field are ' . var_export($mapping, true));
        return $mapping;
    }
    public function process_mapped_fields($mapping, $inputs, $entry_id = null, $form_id = null)
    {
        $result = [];
        foreach ($mapping as $entity => $fields) {
            foreach ($fields as $field) {
                $formField = $field['form_field'];
                $matches = array_filter(array_keys($inputs), fn($v) => intval($v) == $formField && fmod($v, 1) !=0);
                if (count($matches) === 0) {
                    $value = rgar($inputs, $formField);
                    // Preserve "0" values, only treat truly blank as empty
                    if (is_array($value)) {
                        $value = implode(', ', array_map('trim', $value));
                    } else {
                        $value = trim((string) $value);
                    }
                    $result[$entity][$field['entity_field']] = ($value === '') ? $field['default_value'] : $value;
                } else {
                    $result[$entity][$field['entity_field']] = $this->process_checkbox_value($inputs, $formField, $matches);
                }
            }
        }
        $this->log_debug('Processed fields are ' . var_export($result, true));
        return $result;
    }

    public function process_checkbox_value($inputs, $formField, $checkboxIdxs)
    {
        $form = GFAPI::get_form($inputs['form_id']);
        $checkboxes = GFAPI::get_fields_by_type($form, array( 'consent', 'checkbox' ), true);
        $value = false;
        $multiValues = [];
        foreach ($checkboxes as $checkbox) {
            if ($checkbox->id == $formField) {
                foreach ($checkboxIdxs as $checkboxIdx) {
                    if (!empty($inputs[$checkboxIdx]) && !$value) {
                        if (preg_match('/non-boolean/', $checkbox->cssClass)) {
                            $multiValues[] = $inputs[$checkboxIdx];
                        } else {
                            $value = true;
                        }
                    }
                }
                break;
            }
        }
        if (!empty($multiValues)) {
            return implode(',', $multiValues);
        }
        return $value;
    }

    public function process_failed_async_requests()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        $statement = $wpdb->prepare('SELECT * FROM ' . $table_name . ' where status=%s LIMIT 1', 'failed');
        $results = $wpdb->get_results($statement, ARRAY_A);
        $this->log_debug(__METHOD__ . ' processing > ' .  var_export($results, true));
        foreach ($results as $result) {
            $entry_id = isset($result['entry_id']) ? $result['entry_id'] : null;
            $form_id = isset($result['form_id']) ? $result['form_id'] : null;
            $retry_count = isset($result['retry_count']) ? intval($result['retry_count']) : 0;

            // Check if max retries exceeded (5 attempts)
            if ($retry_count >= 5) {
                $this->set_async_processing_state($result['id'], 'abandoned');

                // Determine failure reason
                $failure_reason = 'Unknown error';
                $error_details = '';
                if ($result['accounts_call_status'] != 200) {
                    $failure_reason = "Accounts API returned {$result['accounts_call_status']}";
                    $error_details = $result['accounts_response'];
                } elseif ($result['opportunities_call_status'] != 200) {
                    $failure_reason = "Opportunities API returned {$result['opportunities_call_status']}";
                    $error_details = $result['opportunities_response'];
                }

                // Add abandoned note to entry
                $this->add_momentus_note(
                    $entry_id,
                    "Request Abandoned - Max Retries Exceeded\n\nDatabase Record ID: {$result['id']}\nTotal Attempts: {$retry_count}\nFinal Failure Reason: {$failure_reason}\n\nThis request has been abandoned after 5 failed attempts. Manual intervention required.\n\nLast Error Response:\n{$error_details}",
                    'ERROR'
                );

                // Send admin notification
                $this->send_abandoned_notification($result, $failure_reason, $error_details, $entry_id, $form_id);

                $this->log_debug(__METHOD__ . ' abandoned > Request ID: ' . $result['id'] . ' after ' . $retry_count . ' attempts');
                continue;
            }

            // Increment retry count
            $retry_count++;
            $this->increment_retry_count($result['id']);

            // Determine failure reason
            $failure_reason = 'Unknown error';
            if ($result['accounts_call_status'] != 200) {
                $failure_reason = "Accounts API returned {$result['accounts_call_status']}";
            } elseif ($result['opportunities_call_status'] != 200) {
                $failure_reason = "Opportunities API returned {$result['opportunities_call_status']}";
            }

            // Add retry note
            $current_time = current_time('Y-m-d H:i:s');
            $this->add_momentus_note(
                $entry_id,
                "Retry Attempt Started (Attempt {$retry_count} of 5)\n\nRetrying failed request (ID: {$result['id']})\nPrevious failure: {$failure_reason}\nLast attempt: {$result['last_attempt_at']}\nCurrent attempt: {$current_time}\nRetry interval: 5 minutes",
                'WARNING'
            );

            $this->set_async_processing_state($result['id'], 'retrying');
            $requests = json_decode($result['body'], true);
            if ($result['accounts_call_status'] != 200) {
                $messages = [];
                $this->send($requests, $messages, $entry_id, $form_id);
                $this->process_messages_sent($result['id'], $messages, $entry_id, $form_id);
            } else {
                //Process Opportunities
                $accountsResponse = json_decode($result['accounts_response'], true);
                $postBody = json_decode($result['body'], true);
                if (isset($accountsResponse['AccountCode']) && isset($postBody['opportunities'])) {
                    $opportunitiesBody = $postBody['opportunities'];
                    $opportunitiesBody['AccountCode'] = $accountsResponse['AccountCode'];

                    $settings = $this->get_saved_plugin_settings();
                    $api_url = isset($settings['api_url']) ? $settings['api_url'] : 'Not configured';

                    // Add note before retry
                    $this->add_momentus_note(
                        $entry_id,
                        "Retrying Opportunities API Call\n\nEndpoint: POST {$api_url}/Opportunities\nAccountCode: {$accountsResponse['AccountCode']}\n\nAttempting to retry Opportunities creation...",
                        'INFO'
                    );

                    $start_time = microtime(true);
                    $response = $this->send_post('Opportunities', $opportunitiesBody);
                    $duration = round(microtime(true) - $start_time, 2);

                    // Handle WP_Error (connection timeout, DNS failure, etc.)
                    if (is_wp_error($response)) {
                        $error_message = $response->get_error_message();
                        $this->set_async_processing_column($result['id'], 'opportunities_call_status', 0);
                        $this->set_async_processing_column($result['id'], 'opportunities_response', 'WP_Error: ' . $error_message);
                        $this->set_async_processing_state($result['id'], 'failed');
                        $this->log_debug(__METHOD__ . ' WP_Error on Opportunities retry: ' . $error_message);

                        $next_retry = wp_date('Y-m-d H:i:s', time() + 300);
                        $this->add_momentus_note(
                            $entry_id,
                            "Retry Failed - Opportunities API (Connection Error)\n\nResponse Time: {$duration}s\n\nError: {$error_message}\n\nNext retry scheduled: {$next_retry} (in 5 minutes)",
                            'ERROR'
                        );
                        continue;
                    }

                    $this->set_async_processing_column($result['id'], 'opportunities_call_status', $response['response']['code']);
                    $this->set_async_processing_column($result['id'], 'opportunities_response', $response['body']);
                    if ($response['response']['code'] === 200) {
                        $this->set_async_processing_state($result['id'], 'complete');
                        $this->log_debug(__METHOD__ . ' completed > ' .  var_export($result, true));

                        $this->add_momentus_note(
                            $entry_id,
                            "Retry Succeeded - Opportunities API\n\nHTTP Status: 200 OK\nResponse Time: {$duration}s\n\nOpportunity created successfully after retry",
                            'SUCCESS'
                        );
                    } else {
                        $this->set_async_processing_state($result['id'], 'failed');
                        $this->log_debug(__METHOD__ . ' failed > ' .  var_export($result, true));

                        $next_retry = wp_date('Y-m-d H:i:s', time() + 300);
                        $this->add_momentus_note(
                            $entry_id,
                            "Retry Failed - Opportunities API\n\nHTTP Status: {$response['response']['code']}\nResponse Time: {$duration}s\n\nError Response:\n{$response['body']}\n\nNext retry scheduled: {$next_retry} (in 5 minutes)",
                            'ERROR'
                        );
                    }
                }
            }
        }
    }

    public function process_async_requests()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        $statement = $wpdb->prepare('SELECT * FROM ' . $table_name . ' where status=%s LIMIT 1', 'new');
        $results = $wpdb->get_results($statement, ARRAY_A);
        $this->log_debug(__METHOD__ . ' processing > ' .  var_export($results, true));
        foreach ($results as $result) {
            $entry_id = isset($result['entry_id']) ? $result['entry_id'] : null;
            $form_id = isset($result['form_id']) ? $result['form_id'] : null;

            // Add note that CRON has picked up the request
            $this->add_momentus_note(
                $entry_id,
                "Async Request Processing Started\n\nCRON job picked up queued request\nDatabase Record ID: {$result['id']}\nStatus: new → processing",
                'INFO'
            );

            $this->set_async_processing_state($result['id']);
            $requests = json_decode($result['body'], true);
            $messages = [];
            $this->send($requests, $messages, $entry_id, $form_id);
            $this->process_messages_sent($result['id'], $messages, $entry_id, $form_id);
        }
    }
    public function process_request($requests, $entry_id = null, $form_id = null)
    {
        $settings = $this->get_saved_plugin_settings();
        if (isset($requests['accounts']) && isset($requests['opportunities'])) {
            $accounts_count = count($requests['accounts']);
            $opportunities_count = count($requests['opportunities']);

            if ($settings['async'] == 1) {
                // Async mode
                $this->add_momentus_note(
                    $entry_id,
                    "Submission Queued for Momentous\n\nEntry ID: {$entry_id} | Form ID: {$form_id}\nMode: ASYNC (will be processed by CRON)\n\nField Mappings:\n• Accounts: {$accounts_count} fields mapped\n• Opportunities: {$opportunities_count} fields mapped\n\nStatus: Queued - will process within 1 minute",
                    'INFO'
                );

                $timeId = time();
                $this->save_requests(array(
                    'body'  => json_encode($requests),
                    'entry_id' => $entry_id,
                    'form_id' => $form_id,
                    'status' => 'new',
                    'created_at' => current_time('Y-m-d H:i:s')
                ));

                // Get the inserted ID
                global $wpdb;
                $inserted_id = $wpdb->insert_id;

                $this->add_momentus_note(
                    $entry_id,
                    "Request Queued Successfully\n\nDatabase Record ID: {$inserted_id}\nThe request will be processed by the CRON job within 1 minute.",
                    'INFO'
                );
            } else {
                // Sync mode
                $this->add_momentus_note(
                    $entry_id,
                    "Submission Received - Processing Immediately\n\nEntry ID: {$entry_id} | Form ID: {$form_id}\nMode: SYNC (immediate processing)\n\nField Mappings:\n• Accounts: {$accounts_count} fields mapped\n• Opportunities: {$opportunities_count} fields mapped",
                    'INFO'
                );

                $this->send($requests, null, $entry_id, $form_id);
            }
        }
    }

    public function send_post($endpoint, $data)
    {
        require_once 'includes/class-gf-momentous-api.php';
        $settings = $this->get_saved_plugin_settings();
        $api = new GF_Momentous_API($settings);
        return $api->request($endpoint, $data, 'POST');
    }

    public function send($requests, &$messages = null, $entry_id = null, $form_id = null)
    {
        require_once 'includes/class-gf-momentous-api.php';
        $settings = $this->get_saved_plugin_settings();
        $api = new GF_Momentous_API($settings);
        $wasSuccessful = false;

        $api_url = isset($settings['api_url']) ? $settings['api_url'] : 'Not configured';

        // Add note before Accounts API call
        $this->add_momentus_note(
            $entry_id,
            "Sending Data to Momentous API - Accounts\n\nEndpoint: POST {$api_url}/Accounts\nAuthentication: JWT token generated successfully\n\nRequest Data Summary:\n• Organization: " . (isset($requests['accounts']['Organization']) ? $requests['accounts']['Organization'] : 'N/A') . "\n• Class: " . (isset($requests['accounts']['Class']) ? $requests['accounts']['Class'] : 'N/A') . "\n• FirstName: " . (isset($requests['accounts']['FirstName']) ? $requests['accounts']['FirstName'] : 'N/A'),
            'INFO'
        );

        $start_time = microtime(true);
        $response = $api->request('Accounts', $requests['accounts'], 'POST');
        $accounts_duration = round(microtime(true) - $start_time, 2);

        // Handle WP_Error (connection timeout, DNS failure, etc.)
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('ACCOUNTS API WP_Error: ' . $error_message);

            if ($messages !== null) {
                $messages['accounts'] = [
                    'status' => 0,
                    'body' => 'WP_Error: ' . $error_message
                ];
            }

            $this->add_momentus_note(
                $entry_id,
                "Accounts API Call Failed - Connection Error\n\nEndpoint: POST {$api_url}/Accounts\nResponse Time: {$accounts_duration}s\n\nError: {$error_message}",
                'ERROR'
            );
            return;
        }

        if (isset($response['response']) && isset($response['response']['code'])) {
            if ($response['response']['code'] == 200) {
                $resp = json_decode($response['body'], true);
                if ($messages !== null) {
                    $messages['accounts'] = [
                        'status' => $response['response']['code'],
                        'body' => $response['body']
                    ];
                }
                $this->log_debug('ACCOUNTS API ENDPOINT SUCCESS ' . $response['body']);

                $account_code = isset($resp['AccountCode']) ? $resp['AccountCode'] : 'N/A';
                $organization = isset($resp['Organization']) ? $resp['Organization'] : 'N/A';

                // Add success note for Accounts API
                $this->add_momentus_note(
                    $entry_id,
                    "Accounts API Call Succeeded\n\nEndpoint: POST {$api_url}/Accounts\nHTTP Status: 200 OK\nResponse Time: {$accounts_duration}s\n\nResponse Summary:\n• AccountCode: {$account_code}\n• Organization: {$organization}\n\nProceeding to create Opportunity with this AccountCode...",
                    'SUCCESS'
                );

                if (isset($resp['AccountCode'])) {
                    $opportunityBody = $requests['opportunities'];
                    $opportunityBody['Account'] = $resp['AccountCode'];

                    // Add note before Opportunities API call
                    $this->add_momentus_note(
                        $entry_id,
                        "Sending Data to Momentous API - Opportunities\n\nEndpoint: POST {$api_url}/Opportunities\nAccountCode: {$resp['AccountCode']}\n\nRequest includes AccountCode from Accounts response",
                        'INFO'
                    );

                    $start_time = microtime(true);
                    $oppResponse =  $api->request('Opportunities', $opportunityBody, 'POST');
                    $opp_duration = round(microtime(true) - $start_time, 2);

                    // Handle WP_Error (connection timeout, DNS failure, etc.)
                    if (is_wp_error($oppResponse)) {
                        $error_message = $oppResponse->get_error_message();
                        $this->log_debug('OPPORTUNITIES API WP_Error: ' . $error_message);

                        if ($messages !== null) {
                            $messages['opportunities'] = [
                                'status' => 0,
                                'body' => 'WP_Error: ' . $error_message
                            ];
                        }

                        $this->add_momentus_note(
                            $entry_id,
                            "Opportunities API Call Failed - Connection Error\n\nEndpoint: POST {$api_url}/Opportunities\nResponse Time: {$opp_duration}s\n\nError: {$error_message}",
                            'ERROR'
                        );
                        return;
                    }

                    if ($messages !== null) {
                        $messages['opportunities'] = [
                            'status' => $oppResponse['response']['code'],
                            'body' => $oppResponse['body']
                        ];
                    }
                    if (isset($oppResponse['response']) && isset($oppResponse['response']['code'])) {
                        if ($oppResponse['response']['code'] == 200) {
                            $this->log_debug("OPPORTUNITIES API ENDPOINT SUCCESS" . $oppResponse['body']);

                            // Add success note for Opportunities API
                            $this->add_momentus_note(
                                $entry_id,
                                "Opportunities API Call Succeeded\n\nEndpoint: POST {$api_url}/Opportunities\nHTTP Status: 200 OK\nResponse Time: {$opp_duration}s\n\nOpportunity created successfully with AccountCode: {$resp['AccountCode']}",
                                'SUCCESS'
                            );
                        } else {
                            $this->log_debug('OPPORTUNITIES API ENDPOINT ERROR CODE:  ' .  $oppResponse['response']['code'] . ' ' . $oppResponse['response']['message']);

                            // Add error note for Opportunities API
                            $error_body = $oppResponse['body'];
                            $this->add_momentus_note(
                                $entry_id,
                                "Opportunities API Call Failed\n\nEndpoint: POST {$api_url}/Opportunities\nHTTP Status: {$oppResponse['response']['code']} {$oppResponse['response']['message']}\n\nError Response:\n{$error_body}\n\nRequest included AccountCode: {$resp['AccountCode']}",
                                'ERROR'
                            );
                        }
                    }
                }
            } else {
                if ($messages !== null) {
                    $messages['accounts'] = [
                        'status' => $response['response']['code'],
                        'body' => $response['body']
                    ];
                }
                $this->log_debug('ACCOUNTS API ENDPOINT ERROR CODE:  ' .  $response['response']['code'] . ' ' . $response['response']['message']);

                // Add error note for Accounts API
                $error_body = $response['body'];
                $this->add_momentus_note(
                    $entry_id,
                    "Accounts API Call Failed\n\nEndpoint: POST {$api_url}/Accounts\nHTTP Status: {$response['response']['code']} {$response['response']['message']}\nResponse Time: {$accounts_duration}s\n\nError Response:\n{$error_body}",
                    'ERROR'
                );
            }
        }
    }

    private function process_messages_sent($id, $messages, $entry_id = null, $form_id = null)
    {
        $hasError = false;
        $accounts_status = 'N/A';
        $opportunities_status = 'N/A';
        $accounts_code = 'N/A';

        foreach ($messages as $entity => $message) {
            $this->set_async_processing_column($id, $entity . '_call_status', $message['status']);
            $this->set_async_processing_column($id, $entity . '_response', $message['body']);

            if ($entity == 'accounts') {
                $accounts_status = $message['status'];
                if ($message['status'] == 200) {
                    $resp = json_decode($message['body'], true);
                    if (isset($resp['AccountCode'])) {
                        $accounts_code = $resp['AccountCode'];
                    }
                }
            } elseif ($entity == 'opportunities') {
                $opportunities_status = $message['status'];
            }

            if ($message['status'] !== 200) {
                $hasError = true;
            }
        }

        $completion_time = current_time('Y-m-d H:i:s');

        if (!$hasError) {
            $this->set_async_processing_state($id, 'completed');
            $this->log_debug(__METHOD__ . ' completed > ' . $id);

            // Add final success note
            $this->add_momentus_note(
                $entry_id,
                "Momentous Sync Completed Successfully\n\nFinal Status: COMPLETED\n• Accounts API: SUCCESS (200) - AccountCode: {$accounts_code}\n• Opportunities API: SUCCESS (200)\n\nDatabase Record ID: {$id}\nCompleted: {$completion_time}",
                'SUCCESS'
            );
        } else {
            $this->set_async_processing_state($id, 'failed');
            $this->log_debug(__METHOD__ . ' failed > ' . $id);

            // Determine which API failed
            $accounts_mark = ($accounts_status == 200) ? 'SUCCESS' : 'FAILED';
            $opportunities_mark = ($opportunities_status == 200) ? 'SUCCESS' : 'FAILED';

            // Add final error note
            $error_details = '';
            if ($accounts_status != 200) {
                $error_details = "\n\nError Details:\nAccounts API returned HTTP {$accounts_status}";
            } elseif ($opportunities_status != 200) {
                $error_details = "\n\nError Details:\nOpportunities API returned HTTP {$opportunities_status}";
            }

            $next_retry = wp_date('Y-m-d H:i:s', time() + 300);
            $this->add_momentus_note(
                $entry_id,
                "Momentous Sync Failed\n\nFinal Status: FAILED\n• Accounts API: {$accounts_mark} ({$accounts_status})\n• Opportunities API: {$opportunities_mark} ({$opportunities_status}){$error_details}\n\nDatabase Record ID: {$id}\nCompleted: {$completion_time}\n\nNext retry scheduled: {$next_retry} (in 5 minutes)",
                'ERROR'
            );
        }
    }

    private function set_async_processing_column($id, $column, $value)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        $wpdb->update($table_name, [
            $column => $value
        ], ['id' => $id]);
    }

    private function set_async_processing_state($id, $state = 'processing')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        if ($state == 'completed') {
            $wpdb->update($table_name, [
                'status' => $state,
                'completed_at' => current_time('Y-m-d H:i:s')
            ], ['id' => $id]);
        } else if ($state == 'failed') {
            $wpdb->update($table_name, [
                'status' => $state,
            ], ['id' => $id]);
        } else if ($state == 'retrying') {
            $wpdb->update($table_name, [
                'status' => $state,
                'last_attempt_at' => current_time('Y-m-d H:i:s')
            ], ['id' => $id]);
        } else {
            $wpdb->update($table_name, [
                'status' => $state,
                'executed_at' => current_time('Y-m-d H:i:s')
            ], ['id' => $id]);
        }
    }

    private function save_requests($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'momentous_requests';
        $wpdb->insert($table_name, $data);
    }

    /**
     * Add a note to a Gravity Forms entry
     * @param int $entry_id The entry ID
     * @param string $message The note message
     * @param string $type The note type: INFO, SUCCESS, ERROR, WARNING
     */
    private function add_momentus_note($entry_id, $message, $type = 'INFO')
    {
        if (empty($entry_id)) {
            return;
        }

        try {
            $timestamp = current_time('Y-m-d H:i:s');
            $formatted_message = "[{$type}] {$message}\n\nTimestamp: {$timestamp}";

            GFAPI::add_note(
                $entry_id,
                0, // user_id (0 = system)
                'Momentous Add-On',
                $formatted_message
            );
        } catch (Exception $e) {
            // Fail silently - don't break the sync
            $this->log_debug("[Entry {$entry_id}] Failed to add note: {$e->getMessage()}");
        }

        // Also log to debug log
        $this->log_debug("[Entry {$entry_id}] [{$type}] {$message}");
    }

    /**
     * Increment the retry count for a request
     * @param int $id The request ID
     */
    private function increment_retry_count($id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET retry_count = retry_count + 1 WHERE id = %d",
            $id
        ));
    }

    /**
     * Send admin notification when a request is abandoned
     * @param array $request The request record
     * @param string $failure_reason The failure reason
     * @param string $error_details The error details
     * @param int $entry_id The entry ID
     * @param int $form_id The form ID
     */
    private function send_abandoned_notification($request, $failure_reason, $error_details, $entry_id, $form_id)
    {
        $settings = $this->get_plugin_settings();
        $to = !empty($settings['email_cron_failure_alerts']) ? $settings['email_cron_failure_alerts'] : get_option('admin_email');

        $subject = 'Momentous Request Abandoned - Manual Intervention Required';

        $entry_url = admin_url("admin.php?page=gf_entries&view=entry&id={$form_id}&lid={$entry_id}");
        $created_at = isset($request['created_at']) ? $request['created_at'] : 'Unknown';
        $retry_count = isset($request['retry_count']) ? $request['retry_count'] : 0;

        $message = "<p><strong>Momentous Request Abandoned</strong></p>";
        $message .= "<p>A Momentous sync request has been abandoned after {$retry_count} failed attempts.</p>";
        $message .= "<h3>Request Details:</h3>";
        $message .= "<ul>";
        $message .= "<li><strong>Database Record ID:</strong> {$request['id']}</li>";
        $message .= "<li><strong>Entry ID:</strong> <a href='{$entry_url}'>{$entry_id}</a></li>";
        $message .= "<li><strong>Form ID:</strong> {$form_id}</li>";
        $message .= "<li><strong>Created:</strong> {$created_at}</li>";
        $message .= "<li><strong>Total Retry Attempts:</strong> {$retry_count}</li>";
        $message .= "</ul>";
        $message .= "<h3>Failure Information:</h3>";
        $message .= "<p><strong>Reason:</strong> {$failure_reason}</p>";

        if (!empty($error_details)) {
            $message .= "<h4>Error Response:</h4>";
            $message .= "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>" . esc_html($error_details) . "</pre>";
        }

        $message .= "<hr>";
        $message .= "<p><strong>Action Required:</strong></p>";
        $message .= "<ul>";
        $message .= "<li>Review the error details above</li>";
        $message .= "<li><a href='{$entry_url}'>View the Gravity Forms entry</a></li>";
        $message .= "<li>Correct any data issues</li>";
        $message .= "<li>Manually sync to Momentous if needed</li>";
        $message .= "</ul>";
        $message .= "<p><em>Time: " . current_time('Y-m-d H:i:s') . "</em></p>";

        wp_mail($to, $subject, $message, ['Content-Type: text/html']);

        $this->log_debug("Abandoned notification sent to {$to} for request ID: {$request['id']}");
    }

    private function get_saved_plugin_settings()
    {
        $prefix  = $this->is_gravityforms_supported('2.5') ? '_gform_setting' : '_gaddon_setting';
        $api_url = rgpost("{$prefix}_api_url");
        $api_key = rgpost("{$prefix}_api_key");

        $settings = $this->get_plugin_settings();
        if (! is_array($settings)) {
            $settings = array();
        }

        if (! $this->is_plugin_settings($this->_slug) || ! ( $api_url && $api_key )) {
            return $settings;
        }

        $settings['api_url'] = esc_url($api_url);
        $settings['api_key'] = sanitize_title($api_key);

        return $settings;
    }
    public function feed_list_columns()
    {
        return array(
            'entity'  => esc_html__('Entity', 'momentous'),
            'entity_field' => esc_html__('Parameter', 'momentous'),
            'form_field' => esc_html__('Form field', 'momentous'),
            'default_value' => esc_html__('Default value', 'momentous'),
        );
    }
    public function get_column_value_form_field($item)
    {
        if (isset($item['form_id']) && isset($item['meta'])) {
            if (isset($item['meta']['form_field'])) {
                $field = GFAPI::get_field($item['form_id'], $item['meta']['form_field']);
                return $field['label'];
            }
        }
    }
    public function get_column_value_entity($item)
    {
        if (isset($item['form_id']) && isset($item['meta'])) {
            if (isset($item['meta']['entity'])) {
                return ucfirst($item['meta']['entity']);
            }
        }
    }

    // =========================================================================
    // SYNC RECOVERY: Resend & Notify buttons for abandoned entries
    // =========================================================================

    /**
     * Get abandoned request for an entry
     * @param int $entry_id The Gravity Forms entry ID
     * @return array|null The abandoned request record or null
     */
    public function get_abandoned_request_for_entry($entry_id)
    {
        if (empty($entry_id)) {
            return null;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE entry_id = %d AND status = 'abandoned' AND retry_count >= 5 ORDER BY id DESC LIMIT 1",
                $entry_id
            ),
            ARRAY_A
        );
    }

    /**
     * Register metabox for abandoned entries (below Notes)
     */
    public function add_abandoned_entry_metabox($meta_boxes, $entry, $form)
    {
        $abandoned_request = $this->get_abandoned_request_for_entry(rgar($entry, 'id'));
        if (!$abandoned_request) {
            return $meta_boxes;
        }

        $meta_boxes['momentous_sync_recovery'] = array(
            'title'    => esc_html__('Momentus Sync Recovery', 'gravityforms-momentous'),
            'callback' => array($this, 'render_abandoned_entry_metabox'),
            'context'  => 'normal',
            'priority' => 'low',
        );

        return $meta_boxes;
    }

    /**
     * Render the sync recovery metabox
     */
    public function render_abandoned_entry_metabox($args)
    {
        $entry = $args['entry'];
        $entry_id = rgar($entry, 'id');

        $abandoned_request = $this->get_abandoned_request_for_entry($entry_id);
        if (!$abandoned_request) {
            return;
        }

        $manual_retry_count = isset($abandoned_request['manual_retry_count']) ? intval($abandoned_request['manual_retry_count']) : 0;
        $manual_notified_at = isset($abandoned_request['manual_notified_at']) ? $abandoned_request['manual_notified_at'] : null;
        $max_manual_retries = 5;

        // Determine error details
        $error_summary = 'Unknown error';
        $error_details = '';
        if ($abandoned_request['accounts_call_status'] != 200) {
            $error_summary = "Accounts API returned HTTP {$abandoned_request['accounts_call_status']}";
            $error_details = $abandoned_request['accounts_response'];
        } elseif ($abandoned_request['opportunities_call_status'] != 200) {
            $error_summary = "Opportunities API returned HTTP {$abandoned_request['opportunities_call_status']}";
            $error_details = $abandoned_request['opportunities_response'];
        }

        // Button states
        $resend_disabled = $manual_retry_count >= $max_manual_retries;
        $resend_text = $resend_disabled
            ? __('Manual resend limit reached', 'gravityforms-momentous')
            : __('Resend to Momentus', 'gravityforms-momentous');

        $notify_disabled = !empty($manual_notified_at);
        $notify_text = $notify_disabled
            ? sprintf(__('Already notified on %s', 'gravityforms-momentous'), wp_date('Y-m-d H:i', strtotime($manual_notified_at)))
            : __('Notify Leads for Manual Entry', 'gravityforms-momentous');

        // Nonces
        $resend_nonce = wp_create_nonce('momentous_manual_resend_' . $entry_id);
        $notify_nonce = wp_create_nonce('momentous_notify_leads_' . $entry_id);

        // Inline script data
        $script_data = array(
            'entry_id'     => $entry_id,
            'request_id'   => $abandoned_request['id'],
            'resend_nonce' => $resend_nonce,
            'notify_nonce' => $notify_nonce,
            'ajaxurl'      => admin_url('admin-ajax.php'),
        );

        ?>
        <div class="momentous-sync-recovery-box">
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px;">
                <strong><?php esc_html_e('Warning:', 'gravityforms-momentous'); ?></strong>
                <?php esc_html_e('This entry failed to sync after 5 automatic retry attempts.', 'gravityforms-momentous'); ?>
            </div>

            <div style="margin-bottom: 15px;">
                <strong><?php esc_html_e('Last Error:', 'gravityforms-momentous'); ?></strong>
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin-top: 5px; font-family: monospace; font-size: 12px; max-height: 150px; overflow-y: auto;">
                    <div><strong><?php echo esc_html($error_summary); ?></strong></div>
                    <?php if (!empty($error_details)): ?>
                        <pre style="margin: 5px 0 0 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($error_details); ?></pre>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <span><?php printf(esc_html__('Manual resends: %d/%d', 'gravityforms-momentous'), $manual_retry_count, $max_manual_retries); ?></span>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="momentous-resend-btn" class="button button-primary" <?php echo $resend_disabled ? 'disabled' : ''; ?>>
                    <?php echo esc_html($resend_text); ?>
                </button>
                <button type="button" id="momentous-notify-btn" class="button button-secondary" <?php echo $notify_disabled ? 'disabled' : ''; ?>>
                    <?php echo esc_html($notify_text); ?>
                </button>
            </div>

            <?php if ($manual_retry_count >= $max_manual_retries && empty($manual_notified_at)): ?>
                <div style="background: #d1ecf1; border-left: 4px solid #0dcaf0; padding: 12px; margin-top: 15px;">
                    <?php esc_html_e('Manual resend limit reached. Please use "Notify Leads" to request manual data entry.', 'gravityforms-momentous'); ?>
                </div>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
            var momentousEntryData = <?php echo wp_json_encode($script_data); ?>;
        </script>
        <?php
    }

    /**
     * AJAX handler for manual resend
     */
    public function ajax_manual_resend()
    {
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'momentous_manual_resend_' . $entry_id)) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }

        $entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => 'Entry not found.'));
        }

        $abandoned_request = $this->get_abandoned_request_for_entry($entry_id);
        if (!$abandoned_request) {
            wp_send_json_error(array('message' => 'No abandoned request found for this entry.'));
        }

        $manual_retry_count = isset($abandoned_request['manual_retry_count']) ? intval($abandoned_request['manual_retry_count']) : 0;
        if ($manual_retry_count >= 5) {
            wp_send_json_error(array('message' => 'Manual resend limit reached. Please use "Notify Leads" instead.'));
        }

        // Reset the request for retry using raw SQL (wpdb->update can't set NULL on TIMESTAMP columns)
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name}
             SET status = %s,
                 retry_count = %d,
                 manual_retry_count = %d,
                 executed_at = NULL,
                 completed_at = NULL,
                 last_attempt_at = NULL
             WHERE id = %d",
            'failed',
            0,
            $manual_retry_count + 1,
            $abandoned_request['id']
        ));

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update request.'));
        }

        $current_user = wp_get_current_user();
        $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        $timestamp = current_time('Y-m-d H:i:s');

        $this->add_momentus_note(
            $entry_id,
            "Manual Resend Triggered\n\nTriggered by: {$user_name}\nTime: {$timestamp}\nManual resend attempt: " . ($manual_retry_count + 1) . "/5\n\nThe request has been queued for retry.",
            'INFO'
        );

        $this->log_debug(__METHOD__ . ' Manual resend triggered for entry ' . $entry_id . ' by ' . $user_name);

        wp_send_json_success(array('message' => 'Resend queued successfully. The request will be processed shortly.'));
    }

    /**
     * AJAX handler for notify leads
     */
    public function ajax_notify_leads()
    {
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'momentous_notify_leads_' . $entry_id)) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }

        $entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => 'Entry not found.'));
        }

        $abandoned_request = $this->get_abandoned_request_for_entry($entry_id);
        if (!$abandoned_request) {
            wp_send_json_error(array('message' => 'No abandoned request found for this entry.'));
        }

        if (!empty($abandoned_request['manual_notified_at'])) {
            wp_send_json_error(array('message' => 'Leads have already been notified for this entry.'));
        }

        // Get form info
        $form_id = $abandoned_request['form_id'];
        $form = GFAPI::get_form($form_id);
        $form_name = $form ? $form['title'] : 'Unknown Form';

        // Determine email recipients
        $settings = $this->get_plugin_settings();
        $lead_emails = !empty($settings[self::MOMENTOUS_LEAD_EMAILS_NAME]) ? $settings[self::MOMENTOUS_LEAD_EMAILS_NAME] : '';
        if (empty($lead_emails)) {
            $lead_emails = !empty($settings['email_cron_failure_alerts']) ? $settings['email_cron_failure_alerts'] : get_option('admin_email');
        }

        $entry_url = admin_url("admin.php?page=gf_entries&view=entry&id={$form_id}&lid={$entry_id}");
        $submission_date = isset($entry['date_created']) ? $entry['date_created'] : 'Unknown';
        $enquiry_fields = $this->get_key_enquiry_fields($entry, $form);

        // Error summary
        $error_summary = 'Unknown error';
        $error_details = '';
        if ($abandoned_request['accounts_call_status'] != 200) {
            $error_summary = "Accounts API returned HTTP {$abandoned_request['accounts_call_status']}";
            $error_details = $abandoned_request['accounts_response'];
        } elseif ($abandoned_request['opportunities_call_status'] != 200) {
            $error_summary = "Opportunities API returned HTTP {$abandoned_request['opportunities_call_status']}";
            $error_details = $abandoned_request['opportunities_response'];
        }

        $subject = sprintf('Manual Entry Required - %s (Entry #%d)', $form_name, $entry_id);

        $message = "<h2>Manual Entry Required</h2>";
        $message .= "<p>A Gravity Forms submission failed to sync to Momentus after multiple attempts. Manual data entry is required.</p>";

        $message .= "<h3>Submission Details</h3>";
        $message .= "<ul>";
        $message .= "<li><strong>Form:</strong> " . esc_html($form_name) . "</li>";
        $message .= "<li><strong>Entry ID:</strong> {$entry_id}</li>";
        $message .= "<li><strong>Submission Date:</strong> " . esc_html($submission_date) . "</li>";
        $message .= "</ul>";

        $message .= "<h3>Enquiry Information</h3>";
        $message .= "<ul>";
        foreach ($enquiry_fields as $label => $value) {
            $message .= "<li><strong>" . esc_html($label) . ":</strong> " . esc_html($value) . "</li>";
        }
        $message .= "</ul>";

        $message .= "<h3>Error Summary</h3>";
        $message .= "<p><strong>" . esc_html($error_summary) . "</strong></p>";
        if (!empty($error_details)) {
            $message .= "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>" . esc_html($error_details) . "</pre>";
        }

        $message .= "<h3>Actions Required</h3>";
        $message .= "<ol>";
        $message .= "<li><a href='{$entry_url}'>View the full entry in Gravity Forms</a></li>";
        $message .= "<li>Manually enter the data into Momentus</li>";
        $message .= "</ol>";

        $message .= "<hr><p><em>Sent at: " . current_time('Y-m-d H:i:s') . "</em></p>";

        $email_sent = wp_mail($lead_emails, $subject, $message, array('Content-Type: text/html'));

        if (!$email_sent) {
            wp_send_json_error(array('message' => 'Failed to send notification email. Check your mail configuration.'));
        }

        // Update manual_notified_at
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        $wpdb->update(
            $table_name,
            array('manual_notified_at' => current_time('Y-m-d H:i:s')),
            array('id' => $abandoned_request['id']),
            array('%s'),
            array('%d')
        );

        $current_user = wp_get_current_user();
        $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        $timestamp = current_time('Y-m-d H:i:s');

        $this->add_momentus_note(
            $entry_id,
            "Manual Entry Notification Sent\n\nNotification sent to: {$lead_emails}\nTriggered by: {$user_name}\nTime: {$timestamp}\n\nLeads have been notified to manually enter this data into Momentus.",
            'INFO'
        );

        $this->log_debug(__METHOD__ . ' Lead notification sent for entry ' . $entry_id . ' to ' . $lead_emails . ' by ' . $user_name);

        wp_send_json_success(array('message' => 'Notification sent successfully.'));
    }

    /**
     * Extract key enquiry fields from entry for notification email
     */
    private function get_key_enquiry_fields($entry, $form)
    {
        $fields = array();
        if (!$form || empty($form['fields'])) {
            return $fields;
        }

        $key_labels = array('name', 'email', 'phone', 'venue', 'date', 'time', 'message', 'notes', 'company', 'organization');

        foreach ($form['fields'] as $field) {
            $label = strtolower($field->label);
            $field_type = $field->type;

            $is_key_field = false;
            foreach ($key_labels as $key_label) {
                if (strpos($label, $key_label) !== false) {
                    $is_key_field = true;
                    break;
                }
            }

            if ($is_key_field || in_array($field_type, array('name', 'email', 'phone'))) {
                $value = '';
                if ($field_type === 'name') {
                    $parts = array();
                    foreach (array('3', '6') as $suffix) {
                        $sub_value = rgar($entry, $field->id . '.' . $suffix);
                        if (!empty($sub_value)) {
                            $parts[] = $sub_value;
                        }
                    }
                    $value = implode(' ', $parts);
                } else {
                    $value = rgar($entry, $field->id);
                }

                if (!empty($value)) {
                    $fields[$field->label] = $value;
                }
            }
        }

        return array_slice($fields, 0, 10, true);
    }

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFMomentousFeedAddOn();
        }

        return self::$_instance;
    }
}
