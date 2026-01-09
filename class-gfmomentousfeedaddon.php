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

            // Determine failure reason
            $failure_reason = 'Unknown error';
            if ($result['accounts_call_status'] != 200) {
                $failure_reason = "Accounts API returned {$result['accounts_call_status']}";
            } elseif ($result['opportunities_call_status'] != 200) {
                $failure_reason = "Opportunities API returned {$result['opportunities_call_status']}";
            }

            // Add retry note
            $this->add_momentus_note(
                $entry_id,
                "Retry Attempt Started\n\nRetrying failed request (ID: {$result['id']})\nPrevious failure: {$failure_reason}\nLast attempt: {$result['last_attempt_at']}",
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

                        $this->add_momentus_note(
                            $entry_id,
                            "Retry Failed - Opportunities API\n\nHTTP Status: {$response['response']['code']}\nResponse Time: {$duration}s\n\nError Response:\n{$response['body']}\n\nThe request will be retried again later.",
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
                    'created_at' => date('Y-m-d h:i:s', time())
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

            $this->add_momentus_note(
                $entry_id,
                "Momentous Sync Failed\n\nFinal Status: FAILED\n• Accounts API: {$accounts_mark} ({$accounts_status})\n• Opportunities API: {$opportunities_mark} ({$opportunities_status}){$error_details}\n\nDatabase Record ID: {$id}\nCompleted: {$completion_time}\n\nThis request will be retried automatically.",
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
                'completed_at' => date('Y-m-d h:i:s', time())
            ], ['id' => $id]);
        } else if ($state == 'failed') {
            $wpdb->update($table_name, [
                'status' => $state,
            ], ['id' => $id]);
        } else if ($state == 'retrying') {
            $wpdb->update($table_name, [
                'status' => $state,
                'last_attempt_at' => date('Y-m-d h:i:s', time())
            ], ['id' => $id]);
        } else {
            $wpdb->update($table_name, [
                'status' => $state,
                'executed_at' => date('Y-m-d h:i:s', time())
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

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFMomentousFeedAddOn();
        }

        return self::$_instance;
    }
}
