<?php

// If uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'pushbot_api_suffix' );
delete_option( 'pushbot_options' );
