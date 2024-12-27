<?php
/*
Plugin Name: Gravity Forms Momentous Feed Add-On
Plugin URI: https://www.shakewell.agency/
Description: An add-on for Momentous
Version: 1.0
Author: Shakewell
Author URI: https://www.shakewell.agency/
*/

define( 'GF_MOMENTOUS_FEED_ADDON_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'load' ), 5 );

add_action('gform_after_submission', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'processSubmission' ), 5);





class GF_Momentous_Feed_AddOn_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
            return;
        }

        require_once( 'class-gfmomentousfeedaddon.php' );

        GFAddOn::register( 'GFMomentousFeedAddOn' );
    }

    public static function processSubmission($form) {
        $object = gf_momentous_feed_addon();
        $mapping = $object->get_mapped_fields($form['form_id']);
        $values = $object->process_mapped_fields($mapping, $form);
        $object->send($values);
    }
}

function gf_momentous_feed_addon() {
    return GFMomentousFeedAddOn::get_instance();
}
