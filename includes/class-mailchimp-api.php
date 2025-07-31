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
    private $last_error;
    
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
        // Clear previous error
        $this->last_error = null;
        
        if ( empty( $this->api_key ) ) {
            $this->last_error = 'API key is missing';
            error_log( 'Mailchimp API: API key is missing' );
            return false;
        }
        
        $url = $this->base_url . $endpoint;
        error_log( 'Mailchimp API: Making ' . $method . ' request to: ' . $url );
        
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
            error_log( 'Mailchimp API: Request body: ' . $args['body'] );
        }
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            $this->last_error = 'Connection error: ' . $response->get_error_message();
            error_log( 'Mailchimp API Error: ' . $response->get_error_message() );
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $code = wp_remote_retrieve_response_code( $response );
        
        error_log( 'Mailchimp API: Response code: ' . $code );
        error_log( 'Mailchimp API: Response body: ' . $body );
        
        if ( $code >= 400 ) {
            $error_data = json_decode( $body, true );
            $this->last_error = isset( $error_data['detail'] ) ? $error_data['detail'] : 'HTTP ' . $code . ' error';
            
            // Add more specific error information if available
            if ( isset( $error_data['title'] ) ) {
                $this->last_error = $error_data['title'] . ': ' . $this->last_error;
            }
            
            error_log( 'Mailchimp API Error: HTTP ' . $code . ' - ' . $body );
            return false;
        }
        
        // Handle successful responses
        if ( $code >= 200 && $code < 300 ) {
            // For 204 No Content responses, return true instead of null
            if ( $code === 204 ) {
                return true;
            }
            
            // For other successful responses with content, return parsed JSON
            if ( ! empty( $body ) ) {
                return json_decode( $body, true );
            }
            
            // For successful responses with empty body, return true
            return true;
        }
        
        // For unexpected status codes
        $this->last_error = 'Unexpected HTTP status code: ' . $code;
        error_log( 'Mailchimp API: Unexpected status code: ' . $code );
        return false;
    }
    
    /**
     * Get the last error message
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        $result = $this->make_request( 'ping' );
        return $result !== false;
    }
    
    /**
     * Get detailed connection info for debugging
     */
    public function get_connection_info() {
        $ping = $this->make_request( 'ping' );
        $list_info = $this->get_list_info();
        
        return array(
            'ping_success' => $ping !== false,
            'list_valid' => $list_info !== false,
            'api_key_set' => ! empty( $this->api_key ),
            'list_id_set' => ! empty( $this->list_id ),
            'server' => $this->server,
            'last_error' => $this->last_error,
            'list_info' => $list_info
        );
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
            $this->last_error = 'List ID is missing';
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
            if ( ! $this->last_error ) {
                $this->last_error = 'Failed to create campaign';
            }
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
        
        if ( ! $content_result ) {
            if ( ! $this->last_error ) {
                $this->last_error = 'Failed to set campaign content';
            }
            return false;
        }
        
        return $campaign['id'];
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
            if ( ! $this->last_error ) {
                $this->last_error = 'Failed to create campaign';
            }
            return false;
        }
        
        $send_result = $this->send_campaign( $campaign_id );
        
        if ( ! $send_result ) {
            if ( ! $this->last_error ) {
                $this->last_error = 'Campaign created but failed to send';
            }
        }
        
        return $send_result;
    }
    
    /**
     * Get campaign report
     */
    public function get_campaign_report( $campaign_id ) {
        return $this->make_request( 'reports/' . $campaign_id );
    }
    
    /**
     * Get specific list information
     */
    public function get_list_info( $list_id = null ) {
        $list_id = $list_id ?: $this->list_id;
        if ( empty( $list_id ) ) {
            return false;
        }
        
        return $this->make_request( 'lists/' . $list_id );
    }
    
    /**
     * Get list members
     */
    public function get_list_members( $list_id = null, $count = 100 ) {
        $list_id = $list_id ?: $this->list_id;
        if ( empty( $list_id ) ) {
            return false;
        }
        
        $result = $this->make_request( 'lists/' . $list_id . '/members?count=' . $count );
        return $result ? $result['members'] : array();
    }

    /**
     * Check if email is subscribed to the list
     */
    public function check_email_subscription( $email ) {
        if ( empty( $this->list_id ) || empty( $email ) ) {
            return false;
        }
        
        $subscriber_hash = md5( strtolower( $email ) );
        $result = $this->make_request( 'lists/' . $this->list_id . '/members/' . $subscriber_hash );
        
        if ( $result && isset( $result['status'] ) ) {
            return array(
                'subscribed' => true,
                'status' => $result['status'],
                'email' => $result['email_address'] ?? $email
            );
        }
        
        return array(
            'subscribed' => false,
            'status' => null,
            'email' => $email
        );
    }

    /**
     * Send test email to specific member
     */
    public function send_test_email( $campaign_id, $test_emails ) {
        if ( empty( $campaign_id ) || empty( $test_emails ) ) {
            $this->last_error = 'Campaign ID or test emails missing';
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Mailchimp API: send_test_email called with empty campaign_id or test_emails' );
            }
            return false;
        }

        // Ensure test_emails is array and validate email addresses
        $test_emails = (array) $test_emails;
        $valid_emails = array();
        
        foreach ( $test_emails as $email ) {
            if ( is_email( $email ) ) {
                $valid_emails[] = $email;
            }
        }
        
        if ( empty( $valid_emails ) ) {
            $this->last_error = 'No valid email addresses provided for test';
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Mailchimp API: No valid email addresses in test_emails array' );
            }
            return false;
        }

        // First check campaign status
        $campaign_info = $this->make_request( 'campaigns/' . $campaign_id );
        if ( $campaign_info && isset( $campaign_info['status'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Mailchimp API: Campaign status before test: ' . $campaign_info['status'] );
            }
        }

        $test_data = array(
            'test_emails' => $valid_emails,
            'send_type' => 'html'
        );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Mailchimp API: Sending test email for campaign ' . $campaign_id . ' to: ' . implode( ', ', $valid_emails ) );
            error_log( 'Mailchimp API: Test data: ' . json_encode( $test_data ) );
            error_log( 'Mailchimp API: Request URL: campaigns/' . $campaign_id . '/actions/test' );
        }

        $result = $this->make_request( 'campaigns/' . $campaign_id . '/actions/test', 'POST', $test_data );
        
        if ( ! $result ) {
            if ( ! $this->last_error ) {
                $this->last_error = 'Failed to send test email via Mailchimp API';
            }
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Mailchimp API: send_test_email failed. Last error: ' . $this->last_error );
            }
            
            // Try to get campaign status again to see if there's an issue
            $campaign_info_after = $this->make_request( 'campaigns/' . $campaign_id );
            if ( $campaign_info_after && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Mailchimp API: Campaign status after failed test: ' . json_encode( $campaign_info_after['status'] ?? 'unknown' ) );
                error_log( 'Mailchimp API: Campaign type: ' . json_encode( $campaign_info_after['type'] ?? 'unknown' ) );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Mailchimp API: send_test_email succeeded. Response: ' . json_encode( $result ) );
            }
        }
        
        return $result;
    }

    /**
     * Get campaign details
     */
    public function get_campaign_details( $campaign_id ) {
        if ( empty( $campaign_id ) ) {
            return false;
        }
        
        return $this->make_request( 'campaigns/' . $campaign_id );
    }

    /**
     * Create test campaign (without sending)
     */
    public function create_test_campaign( $subject, $content ) {
        if ( empty( $this->list_id ) ) {
            $this->last_error = 'List ID is missing';
            return false;
        }

        $campaign_data = array(
            'type' => 'regular',
            'recipients' => array(
                'list_id' => $this->list_id
            ),
            'settings' => array(
                'subject_line' => '[TEST] ' . $subject,
                'title' => '[TEST] ' . $subject . ' - ' . date( 'Y-m-d H:i:s' ),
                'from_name' => get_bloginfo( 'name' ),
                'reply_to' => get_option( 'admin_email' ),
                'auto_footer' => false,
                'inline_css' => false,
            )
        );

        $campaign = $this->make_request( 'campaigns', 'POST', $campaign_data );

        if ( ! $campaign ) {
            if ( ! $this->last_error ) {
                $this->last_error = 'Failed to create test campaign';
            }
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

        if ( ! $content_result ) {
            if ( ! $this->last_error ) {
                $this->last_error = 'Failed to set test campaign content';
            }
            return false;
        }

        return $campaign['id'];
    }
}
