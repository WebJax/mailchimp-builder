<?php
/**
 * Plugin Name: Mailchimp Builder
 * Description: Build newsletter based on posts and events with Mailchimp API and a predesigned newsletter.
 * Version: 1.0.0
 * Author: Din Navn
 * Text Domain: mailchimp-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'MAILCHIMP_BUILDER_VERSION', '1.0.0' );
define( 'MAILCHIMP_BUILDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAILCHIMP_BUILDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Mailchimp Builder Class
 */
class Mailchimp_Builder {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_mailchimp_generate_newsletter', array( $this, 'ajax_generate_newsletter' ) );
        add_action( 'wp_ajax_mailchimp_send_campaign', array( $this, 'ajax_send_campaign' ) );
        add_action( 'wp_ajax_mailchimp_send_newsletter', array( $this, 'ajax_send_newsletter' ) );
        add_action( 'wp_ajax_mailchimp_search_sponsors', array( $this, 'ajax_search_sponsors' ) );
        add_action( 'wp_ajax_mailchimp_search_posts', array( $this, 'ajax_search_posts' ) );
        add_action( 'wp_ajax_mailchimp_get_list_members', array( $this, 'ajax_get_list_members' ) );
        add_action( 'wp_ajax_mailchimp_send_test_email', array( $this, 'ajax_send_test_email' ) );
        add_action( 'wp_ajax_mailchimp_debug_connection', array( $this, 'ajax_debug_connection' ) );
        add_action( 'wp_ajax_mailchimp_debug_test_email', array( $this, 'ajax_debug_test_email' ) );

        // Register activation hook
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }
    
    public function init() {
        load_plugin_textdomain( 'mailchimp-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        
        // Include necessary files
        $this->include_files();
    }
    
    private function include_files() {
        require_once MAILCHIMP_BUILDER_PLUGIN_DIR . 'includes/class-mailchimp-api.php';
        require_once MAILCHIMP_BUILDER_PLUGIN_DIR . 'includes/class-newsletter-generator.php';
        require_once MAILCHIMP_BUILDER_PLUGIN_DIR . 'includes/class-admin-page.php';
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __( 'Mailchimp Builder', 'mailchimp-builder' ),
            __( 'Mailchimp Builder', 'mailchimp-builder' ),
            'manage_options',
            'mailchimp-builder',
            array( $this, 'admin_page' ),
            'dashicons-email-alt',
            30
        );
    }
    
    public function admin_page() {
        $admin_page = new Mailchimp_Builder_Admin_Page();
        $admin_page->render();
    }
    
    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_mailchimp-builder' !== $hook ) {
            return;
        }
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();
        
        wp_enqueue_script(
            'mailchimp-builder-admin',
            MAILCHIMP_BUILDER_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'media-upload', 'media-views' ),
            MAILCHIMP_BUILDER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mailchimp-builder-admin',
            MAILCHIMP_BUILDER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MAILCHIMP_BUILDER_VERSION
        );
        
        wp_localize_script( 'mailchimp-builder-admin', 'mailchimp_builder', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mailchimp_builder_nonce' ),
        ) );
    }
    
    public function ajax_generate_newsletter() {
        check_ajax_referer( 'mailchimp_builder_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'mailchimp-builder' ) );
        }
        
        $generator = new Mailchimp_Builder_Newsletter_Generator();
        // Generate preview without marking posts as sent
        $newsletter_content = $generator->generate_newsletter_html( false );
        
        wp_send_json_success( array(
            'content' => $newsletter_content
        ) );
    }
    
    public function ajax_send_campaign() {
        check_ajax_referer( 'mailchimp_builder_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'mailchimp-builder' ) );
        }

        $subject = sanitize_text_field( $_POST['subject'] );
        
        // Generate fresh newsletter content for the campaign (not from POST data)
        $generator = new Mailchimp_Builder_Newsletter_Generator();
        $newsletter_data = $generator->generate_newsletter( false );
        $content = $newsletter_data['content']; // Use the full HTML content
        $post_ids = $newsletter_data['post_ids'];
        
        if ( empty( $content ) ) {
            error_log( 'Mailchimp Send Campaign: Empty newsletter content generated' );
            wp_send_json_error( array(
                'message' => __( 'Failed to generate newsletter content for campaign.', 'mailchimp-builder' )
            ) );
        }
        
        error_log( 'Mailchimp Send Campaign: Generated content length: ' . strlen( $content ) );
        
        $api = new Mailchimp_Builder_API();
        $result = $api->create_and_send_campaign( $subject, $content );
        
        if ( $result ) {
            // Mark posts as sent only when campaign is successfully sent
            $generator->mark_posts_as_sent( $post_ids );
            
            wp_send_json_success( array(
                'message' => __( 'Newsletter sent successfully!', 'mailchimp-builder' )
            ) );
        } else {
            $error_message = $api->get_last_error() ?: __( 'Failed to send newsletter.', 'mailchimp-builder' );
            wp_send_json_error( array(
                'message' => $error_message
            ) );
        }
    }
    
    public function activate() {
        // Create default options
        $default_options = array(
            'mailchimp_api_key' => '',
            'mailchimp_list_id' => '',
            'newsletter_template' => 'default',
            'include_posts' => true,
            'include_events' => true,
            'posts_limit' => 5,
            'events_limit' => 5,
            'post_excerpt_length' => 150,
            'events_end_date' => date( 'Y-m-d', strtotime( '+3 months' ) ),
            'include_featured_images' => true
        );
        
        add_option( 'mailchimp_builder_options', $default_options );
    }
    
    public function ajax_search_sponsors() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'mailchimp_builder_nonce' ) ) {
            wp_die( 'Unauthorized' );
        }
        
        $search = sanitize_text_field( $_POST['search'] );
        $results = array();
        
        if ( strlen( $search ) >= 2 ) {
            // Search in butiksside
            $shops = get_posts( array(
                'post_type' => 'butiksside',
                'post_status' => 'publish',
                's' => $search,
                'posts_per_page' => 10,
                'orderby' => 'title',
                'order' => 'ASC'
            ) );
            
            foreach ( $shops as $shop ) {
                $results[] = array(
                    'id' => $shop->ID,
                    'title' => $shop->post_title,
                    'type' => 'butiksside'
                );
            }
            
            // Search in erhvervsside
            $businesses = get_posts( array(
                'post_type' => 'erhvervsside',
                'post_status' => 'publish',
                's' => $search,
                'posts_per_page' => 10,
                'orderby' => 'title',
                'order' => 'ASC'
            ) );
            
            foreach ( $businesses as $business ) {
                $results[] = array(
                    'id' => $business->ID,
                    'title' => $business->post_title,
                    'type' => 'erhvervsside'
                );
            }
            
            // Sort results by title
            usort( $results, function( $a, $b ) {
                return strcmp( $a['title'], $b['title'] );
            });
        }
        
        wp_send_json_success( $results );
    }
    
    public function ajax_search_posts() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'mailchimp_builder_nonce' ) ) {
            wp_die( 'Unauthorized' );
        }
        
        $search = sanitize_text_field( $_POST['search'] );
        $results = array();
        
        if ( strlen( $search ) >= 2 ) {
            // Search in posts
            $posts = get_posts( array(
                'post_type' => 'post',
                'post_status' => 'publish',
                's' => $search,
                'posts_per_page' => 15,
                'orderby' => 'date',
                'order' => 'DESC'
            ) );
            
            foreach ( $posts as $post ) {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'date' => get_the_date( 'Y-m-d', $post )
                );
            }
        }
        
        wp_send_json_success( $results );
    }
    
    public function ajax_get_list_members() {
        check_ajax_referer( 'mailchimp_builder_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Insufficient permissions.', 'mailchimp-builder' )
            ) );
        }
        
        $api = new Mailchimp_Builder_API();
        $members = $api->get_list_members( null, 50 ); // Get first 50 members
        
        if ( $members ) {
            $member_emails = array();
            foreach ( $members as $member ) {
                if ( $member['status'] === 'subscribed' ) {
                    $member_emails[] = array(
                        'email' => $member['email_address'],
                        'name' => ! empty( $member['merge_fields']['FNAME'] ) ? 
                            $member['merge_fields']['FNAME'] . ' ' . ( $member['merge_fields']['LNAME'] ?? '' ) : 
                            $member['email_address']
                    );
                }
            }
            
            wp_send_json_success( array(
                'members' => $member_emails
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Could not retrieve list members.', 'mailchimp-builder' )
            ) );
        }
    }
    
    public function ajax_send_test_email() {
        check_ajax_referer( 'mailchimp_builder_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Insufficient permissions.', 'mailchimp-builder' )
            ) );
        }
        
        $test_email = sanitize_email( $_POST['test_email'] );
        $subject = sanitize_text_field( $_POST['subject'] );
        
        if ( empty( $test_email ) || ! is_email( $test_email ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please provide a valid email address.', 'mailchimp-builder' )
            ) );
        }
        
        // Log the test email attempt
        error_log( 'Mailchimp Test Email: Starting test email to ' . $test_email );
        
        // Generate newsletter content for test without marking posts as sent
        $generator = new Mailchimp_Builder_Newsletter_Generator();
        $content = $generator->generate_newsletter_html( false );
        
        if ( empty( $content ) ) {
            error_log( 'Mailchimp Test Email: Empty newsletter content generated' );
            wp_send_json_error( array(
                'message' => __( 'Failed to generate newsletter content for test.', 'mailchimp-builder' )
            ) );
        }
        
        error_log( 'Mailchimp Test Email: Newsletter content generated, length: ' . strlen( $content ) );
        
        $api = new Mailchimp_Builder_API();
        
        // Create test campaign
        error_log( 'Mailchimp Test Email: Creating test campaign with subject: ' . $subject );
        $campaign_id = $api->create_test_campaign( $subject, $content );
        
        if ( ! $campaign_id ) {
            $error_message = $api->get_last_error() ?: __( 'Failed to create test campaign.', 'mailchimp-builder' );
            error_log( 'Mailchimp Test Email: Failed to create campaign. Error: ' . $error_message );
            wp_send_json_error( array(
                'message' => sprintf( __( 'Campaign creation failed: %s', 'mailchimp-builder' ), $error_message )
            ) );
        }
        
        error_log( 'Mailchimp Test Email: Test campaign created with ID: ' . $campaign_id );
        
        // Send test email
        error_log( 'Mailchimp Test Email: Sending test email to: ' . $test_email );
        $result = $api->send_test_email( $campaign_id, array( $test_email ) );
        
        if ( $result ) {
            error_log( 'Mailchimp Test Email: Test email sent successfully' );
            wp_send_json_success( array(
                'message' => sprintf( __( 'Test email sent successfully to %s!', 'mailchimp-builder' ), $test_email )
            ) );
        } else {
            $error_message = $api->get_last_error() ?: __( 'Unknown error during test email sending.', 'mailchimp-builder' );
            error_log( 'Mailchimp Test Email: Failed to send test email. Error: ' . $error_message );
            wp_send_json_error( array(
                'message' => sprintf( __( 'Test email sending failed: %s', 'mailchimp-builder' ), $error_message )
            ) );
        }
    }
    
    public function ajax_debug_connection() {
        check_ajax_referer( 'mailchimp_builder_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Insufficient permissions.', 'mailchimp-builder' )
            ) );
        }
        
        $api = new Mailchimp_Builder_API();
        $connection_info = $api->get_connection_info();
        
        wp_send_json_success( $connection_info );
    }
    
    public function ajax_debug_test_email() {
        check_ajax_referer( 'mailchimp_builder_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Insufficient permissions.', 'mailchimp-builder' )
            ) );
        }
        
        $test_email = sanitize_email( $_POST['test_email'] );
        $subject = sanitize_text_field( $_POST['subject'] );
        
        if ( empty( $test_email ) || ! is_email( $test_email ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please provide a valid email address.', 'mailchimp-builder' )
            ) );
        }
        
        $debug_info = array();
        
        // Step 1: Generate content
        error_log( 'Debug Test Email: Starting content generation' );
        $generator = new Mailchimp_Builder_Newsletter_Generator();
        $content = $generator->generate_newsletter_html( false );
        $debug_info['content_generated'] = ! empty( $content );
        $debug_info['content_length'] = strlen( $content );
        error_log( 'Debug Test Email: Content generated. Length: ' . strlen( $content ) );
        
        // Step 2: Test API connection
        error_log( 'Debug Test Email: Testing API connection' );
        $api = new Mailchimp_Builder_API();
        $connection_info = $api->get_connection_info();
        $debug_info['connection'] = $connection_info;
        error_log( 'Debug Test Email: Connection info: ' . json_encode( $connection_info ) );
        
        // Step 3: Try to create test campaign
        if ( $connection_info['ping_success'] && $connection_info['list_valid'] ) {
            error_log( 'Debug Test Email: Creating test campaign' );
            $campaign_id = $api->create_test_campaign( $subject, $content );
            $debug_info['campaign_created'] = ! empty( $campaign_id );
            $debug_info['campaign_id'] = $campaign_id;
            $debug_info['campaign_error'] = $api->get_last_error();
            error_log( 'Debug Test Email: Campaign creation result. ID: ' . $campaign_id . ', Error: ' . $api->get_last_error() );
            
            // Step 4: Try to send test email if campaign was created
            if ( $campaign_id ) {
                // First check if test email is subscribed to the list
                error_log( 'Debug Test Email: Checking if test email is subscribed to list: ' . $test_email );
                $subscription_info = $api->check_email_subscription( $test_email );
                $debug_info['email_subscription'] = $subscription_info;
                error_log( 'Debug Test Email: Email subscription info: ' . json_encode( $subscription_info ) );
                
                error_log( 'Debug Test Email: Attempting to send test email to: ' . $test_email );
                $result = $api->send_test_email( $campaign_id, array( $test_email ) );
                $debug_info['test_email_sent'] = (bool) $result;
                $debug_info['test_email_error'] = $result ? null : $api->get_last_error();
                $debug_info['test_email_result'] = $result;
                error_log( 'Debug Test Email: Test email result: ' . json_encode( $result ) . ', Error: ' . ( $result ? 'None' : $api->get_last_error() ) );
                
                // Step 5: Get detailed campaign information for debugging
                $campaign_details = $api->get_campaign_details( $campaign_id );
                $debug_info['campaign_details'] = $campaign_details;
                error_log( 'Debug Test Email: Campaign details: ' . json_encode( $campaign_details ) );
            }
        } else {
            error_log( 'Debug Test Email: Connection or list validation failed' );
        }
        
        // Add debug log information
        $debug_info['debug_log_info'] = $this->get_debug_log_info();
        
        wp_send_json_success( $debug_info );
    }
    
    /**
     * Get WordPress debug log information
     */
    public function get_debug_log_info() {
        $debug_info = array(
            'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
            'debug_log_path' => '',
            'debug_log_exists' => false,
            'debug_log_writable' => false
        );
        
        // Try to find debug.log location
        $possible_paths = array(
            WP_CONTENT_DIR . '/debug.log',
            ABSPATH . 'debug.log',
            ini_get( 'error_log' )
        );
        
        foreach ( $possible_paths as $path ) {
            if ( $path && file_exists( $path ) ) {
                $debug_info['debug_log_path'] = $path;
                $debug_info['debug_log_exists'] = true;
                $debug_info['debug_log_writable'] = is_writable( $path );
                break;
            }
        }
        
        return $debug_info;
    }
}

// Initialize the plugin
Mailchimp_Builder::get_instance();
