<?php
/**
 * Mailchimp API Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Mailchimp_Builder_API {
    
    private $api_key;
    private $list_id;
    private $server;
    private $base_url;
    
    public function __construct() {
        $options = get_option( 'mailchimp_builder_options', array() );
        $this->api_key = isset( $options['mailchimp_api_key'] ) ? $options['mailchimp_api_key'] : '';
        $this->list_id = isset( $options['mailchimp_list_id'] ) ? $options['mailchimp_list_id'] : '';
        
        if ( $this->api_key ) {
            $this->server = substr( $this->api_key, strpos( $this->api_key, '-' ) + 1 );
            $this->base_url = 'https://' . $this->server . '.api.mailchimp.com/3.0/';
        }
    }
    
    /**
     * Make API request to Mailchimp
     */
    private function make_request( $endpoint, $method = 'GET', $data = null ) {
        if ( empty( $this->api_key ) ) {
            return false;
        }
        
        $url = $this->base_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( 'user:' . $this->api_key ),
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );
        
        if ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
            $args['body'] = json_encode( $data );
        }
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'Mailchimp API Error: ' . $response->get_error_message() );
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $code = wp_remote_retrieve_response_code( $response );
        
        if ( $code >= 400 ) {
            error_log( 'Mailchimp API Error: HTTP ' . $code . ' - ' . $body );
            return false;
        }
        
        return json_decode( $body, true );
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $result = $this->make_request( 'ping' );
        return $result !== false;
    }
    
    /**
     * Get all lists
     */
    public function get_lists() {
        $result = $this->make_request( 'lists' );
        return $result ? $result['lists'] : array();
    }
    
    /**
     * Create a new campaign
     */
    public function create_campaign( $subject, $content ) {
        if ( empty( $this->list_id ) ) {
            return false;
        }
        
        $campaign_data = array(
            'type' => 'regular',
            'recipients' => array(
                'list_id' => $this->list_id
            ),
            'settings' => array(
                'subject_line' => $subject,
                'title' => $subject . ' - ' . date( 'Y-m-d H:i:s' ),
                'from_name' => get_bloginfo( 'name' ),
                'reply_to' => get_option( 'admin_email' ),
                'auto_footer' => false,
                'inline_css' => false,
            )
        );
        
        $campaign = $this->make_request( 'campaigns', 'POST', $campaign_data );
        
        if ( ! $campaign ) {
            return false;
        }
        
        // Set campaign content
        $content_data = array(
            'html' => $content
        );
        
        $content_result = $this->make_request( 
            'campaigns/' . $campaign['id'] . '/content', 
            'PUT', 
            $content_data 
        );
        
        return $content_result ? $campaign['id'] : false;
    }
    
    /**
     * Send a campaign
     */
    public function send_campaign( $campaign_id ) {
        return $this->make_request( 'campaigns/' . $campaign_id . '/actions/send', 'POST' );
    }
    
    /**
     * Create and send campaign in one go
     */
    public function create_and_send_campaign( $subject, $content ) {
        $campaign_id = $this->create_campaign( $subject, $content );
        
        if ( ! $campaign_id ) {
            return false;
        }
        
        return $this->send_campaign( $campaign_id );
    }
    
    /**
     * Get campaign report
     */
    public function get_campaign_report( $campaign_id ) {
        return $this->make_request( 'reports/' . $campaign_id );
    }
}
