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
        
        $content = sanitize_textarea_field( $_POST['content'] );
        $subject = sanitize_text_field( $_POST['subject'] );
        
        // Get the posts that are currently being sent
        $generator = new Mailchimp_Builder_Newsletter_Generator();
        $newsletter_data = $generator->generate_newsletter( false );
        $post_ids = $newsletter_data['post_ids'];
        
        $api = new Mailchimp_Builder_API();
        $result = $api->create_and_send_campaign( $subject, $content );
        
        if ( $result ) {
            // Mark posts as sent only when campaign is successfully sent
            $generator->mark_posts_as_sent( $post_ids );
            
            wp_send_json_success( array(
                'message' => __( 'Newsletter sent successfully!', 'mailchimp-builder' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to send newsletter.', 'mailchimp-builder' )
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
        
        // Generate newsletter content for test without marking posts as sent
        $generator = new Mailchimp_Builder_Newsletter_Generator();
        $content = $generator->generate_newsletter_html( false );
        
        $api = new Mailchimp_Builder_API();
        
        // Create test campaign
        $campaign_id = $api->create_test_campaign( $subject, $content );
        
        if ( ! $campaign_id ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to create test campaign.', 'mailchimp-builder' )
            ) );
        }
        
        // Send test email
        $result = $api->send_test_email( $campaign_id, array( $test_email ) );
        
        if ( $result ) {
            wp_send_json_success( array(
                'message' => sprintf( __( 'Test email sent successfully to %s!', 'mailchimp-builder' ), $test_email )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to send test email.', 'mailchimp-builder' )
            ) );
        }
    }
}

// Initialize the plugin
Mailchimp_Builder::get_instance();
