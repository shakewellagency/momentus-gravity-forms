<?php
/*
Plugin Name: Gravity Forms Momentous Feed Add-On
Plugin URI: https://www.shakewell.agency/
Description: An add-on for Momentous
Version: 1.1
Author: Shakewell
Author URI: https://www.shakewell.agency/
*/

define('GF_MOMENTOUS_FEED_ADDON_VERSION', '1.1');

add_action('gform_loaded', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'load' ), 5);

add_action('gform_after_submission', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'processSubmission' ), 5);

register_activation_hook(__FILE__, array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'create_table' ));

register_deactivation_hook(__FILE__, array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'delete_table' ));


class GF_Momentous_Feed_AddOn_Bootstrap
{

    public static function load()
    {

        if (! method_exists('GFForms', 'include_feed_addon_framework')) {
            return;
        }
        require_once('class-gfmomentousfeedaddon.php');
        GFAddOn::register('GFMomentousFeedAddOn');
    }

    public static function create_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'momentous_requests';

        $sql = "CREATE TABLE " . $table_name . " (
             id         int auto_increment,
            entity      VARCHAR(50) not null,
            body        LONGTEXT    not null,
            status      varchar(10) null,
            response    LONGTEXT    null,
            created_at  TIMESTAMP   not null,
            executed_at TIMESTAMP   null,
            request_group BIGINT not null,
            constraint PRIMARY_ID_KEY
                primary key (id)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public static function delete_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'momentous_requests';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    public static function processSubmission($form)
    {
        $object = gf_momentous_feed_addon();
        $mapping = $object->get_mapped_fields($form['form_id']);
        $values = $object->process_mapped_fields($mapping, $form);
        $object->send($values);
    }
}

function gf_momentous_feed_addon()
{
    return GFMomentousFeedAddOn::get_instance();
}
