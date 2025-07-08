<?php
/**
 * Admin Page Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Mailchimp_Builder_Admin_Page {
    
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }
    
    public function register_settings() {
        register_setting( 'mailchimp_builder_options', 'mailchimp_builder_options', array( $this, 'sanitize_options' ) );
    }
    
    public function sanitize_options( $input ) {
        $output = array();
        
        if ( isset( $input['mailchimp_api_key'] ) ) {
            $output['mailchimp_api_key'] = sanitize_text_field( $input['mailchimp_api_key'] );
        }
        
        if ( isset( $input['mailchimp_list_id'] ) ) {
            $output['mailchimp_list_id'] = sanitize_text_field( $input['mailchimp_list_id'] );
        }
        
        $output['include_posts'] = isset( $input['include_posts'] );
        $output['include_events'] = isset( $input['include_events'] );
        
        if ( isset( $input['posts_limit'] ) ) {
            $output['posts_limit'] = absint( $input['posts_limit'] );
        }
        
        if ( isset( $input['events_limit'] ) ) {
            $output['events_limit'] = absint( $input['events_limit'] );
        }
        
        if ( isset( $input['post_excerpt_length'] ) ) {
            $output['post_excerpt_length'] = absint( $input['post_excerpt_length'] );
        }
        
        if ( isset( $input['events_end_date'] ) ) {
            $output['events_end_date'] = sanitize_text_field( $input['events_end_date'] );
        }
        
        $output['include_featured_images'] = isset( $input['include_featured_images'] );
        
        // New options
        if ( isset( $input['separator_html'] ) ) {
            $output['separator_html'] = wp_kses_post( $input['separator_html'] );
        }
        
        if ( isset( $input['button_background_color'] ) ) {
            $output['button_background_color'] = sanitize_hex_color( $input['button_background_color'] );
        }
        
        if ( isset( $input['header_image'] ) ) {
            $output['header_image'] = absint( $input['header_image'] );
        }
        
        if ( isset( $input['facebook_url'] ) ) {
            $output['facebook_url'] = esc_url_raw( $input['facebook_url'] );
        }
        
        if ( isset( $input['instagram_url'] ) ) {
            $output['instagram_url'] = esc_url_raw( $input['instagram_url'] );
        }
        
        return $output;
    }
    
    public function render() {
        $options = get_option( 'mailchimp_builder_options', array() );
        
        // Handle form submission
        if ( isset( $_POST['submit'] ) && check_admin_referer( 'mailchimp_builder_settings' ) ) {
            $updated_options = $this->sanitize_options( $_POST['mailchimp_builder_options'] );
            update_option( 'mailchimp_builder_options', $updated_options );
            $options = $updated_options;
            echo '<div class="notice notice-success"><p>' . __( 'Indstillinger gemt!', 'mailchimp-builder' ) . '</p></div>';
        }
        
        // Test API connection if API key is provided
        $api_status = '';
        if ( ! empty( $options['mailchimp_api_key'] ) ) {
            $api = new Mailchimp_Builder_API();
            if ( $api->test_connection() ) {
                $api_status = '<span class="api-status success">✓ Forbindelse OK</span>';
            } else {
                $api_status = '<span class="api-status error">✗ Forbindelse fejlede</span>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Mailchimp Builder', 'mailchimp-builder' ); ?></h1>
            
            <div class="mailchimp-builder-admin">
                <div class="main-content">
                    <div class="settings-section">
                        <h2><?php _e( 'Indstillinger', 'mailchimp-builder' ); ?></h2>
                        
                        <form method="post" action="">
                            <?php wp_nonce_field( 'mailchimp_builder_settings' ); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="mailchimp_api_key"><?php _e( 'Mailchimp API Nøgle', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="mailchimp_api_key" 
                                               name="mailchimp_builder_options[mailchimp_api_key]" 
                                               value="<?php echo esc_attr( isset( $options['mailchimp_api_key'] ) ? $options['mailchimp_api_key'] : '' ); ?>" 
                                               class="regular-text" />
                                        <?php echo $api_status; ?>
                                        <p class="description">
                                            <?php _e( 'Find din API nøgle i Mailchimp under Profile icon > Profile > Extras dropdown > API keys', 'mailchimp-builder' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="mailchimp_list_id"><?php _e( 'Mailchimp Liste ID', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="mailchimp_list_id" 
                                               name="mailchimp_builder_options[mailchimp_list_id]" 
                                               value="<?php echo esc_attr( isset( $options['mailchimp_list_id'] ) ? $options['mailchimp_list_id'] : '' ); ?>" 
                                               class="regular-text" />
                                        <p class="description">
                                            <?php _e( 'ID på den liste du vil sende nyhedsbreve til', 'mailchimp-builder' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e( 'Inkluder Indhold', 'mailchimp-builder' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="mailchimp_builder_options[include_posts]" 
                                                   value="1" 
                                                   <?php checked( isset( $options['include_posts'] ) ? $options['include_posts'] : true ); ?> />
                                            <?php _e( 'Inkluder seneste indlæg', 'mailchimp-builder' ); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" 
                                                   name="mailchimp_builder_options[include_events]" 
                                                   value="1" 
                                                   <?php checked( isset( $options['include_events'] ) ? $options['include_events'] : true ); ?> />
                                            <?php _e( 'Inkluder kommende arrangementer', 'mailchimp-builder' ); ?>
                                            <?php if ( ! class_exists( 'Tribe__Events__Main' ) ) : ?>
                                                <em style="color: #d63638;"><?php _e( '(The Events Calendar plugin ikke installeret)', 'mailchimp-builder' ); ?></em>
                                            <?php endif; ?>
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="posts_limit"><?php _e( 'Antal Indlæg', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="posts_limit" 
                                               name="mailchimp_builder_options[posts_limit]" 
                                               value="<?php echo esc_attr( isset( $options['posts_limit'] ) ? $options['posts_limit'] : 5 ); ?>" 
                                               min="1" 
                                               max="20" 
                                               class="small-text" />
                                        <p class="description"><?php _e( 'Maksimalt antal indlæg at inkludere', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="events_limit"><?php _e( 'Antal Arrangementer', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="events_limit" 
                                               name="mailchimp_builder_options[events_limit]" 
                                               value="<?php echo esc_attr( isset( $options['events_limit'] ) ? $options['events_limit'] : 5 ); ?>" 
                                               min="1" 
                                               max="20" 
                                               class="small-text" />
                                        <p class="description"><?php _e( 'Maksimalt antal arrangementer at inkludere', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="post_excerpt_length"><?php _e( 'Uddrag Længde', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="post_excerpt_length" 
                                               name="mailchimp_builder_options[post_excerpt_length]" 
                                               value="<?php echo esc_attr( isset( $options['post_excerpt_length'] ) ? $options['post_excerpt_length'] : 150 ); ?>" 
                                               min="50" 
                                               max="500" 
                                               class="small-text" />
                                        <p class="description"><?php _e( 'Antal tegn for uddrag af indlæg/arrangementer', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="events_end_date"><?php _e( 'Arrangementer Til Dato', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="date" 
                                               id="events_end_date" 
                                               name="mailchimp_builder_options[events_end_date]" 
                                               value="<?php echo esc_attr( isset( $options['events_end_date'] ) ? $options['events_end_date'] : date( 'Y-m-d', strtotime( '+3 months' ) ) ); ?>" 
                                               class="regular-text" />
                                        <p class="description"><?php _e( 'Vis kun arrangementer frem til denne dato (lad være tom for alle kommende)', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e( 'Billede Indstillinger', 'mailchimp-builder' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="mailchimp_builder_options[include_featured_images]" 
                                                   value="1" 
                                                   <?php checked( isset( $options['include_featured_images'] ) ? $options['include_featured_images'] : true ); ?> />
                                            <?php _e( 'Inkluder udvalgte billeder som headers', 'mailchimp-builder' ); ?>
                                        </label>
                                        <p class="description"><?php _e( 'Vis det udvalgte billede øverst i hvert indlæg/arrangement', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="separator_html"><?php _e( 'HTML Separator', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="separator_html" 
                                                  name="mailchimp_builder_options[separator_html]" 
                                                  rows="5" 
                                                  cols="50" 
                                                  class="large-text code"><?php echo esc_textarea( isset( $options['separator_html'] ) ? $options['separator_html'] : '' ); ?></textarea>
                                        <p class="description"><?php _e( 'HTML kode der indsættes mellem nyheder og arrangementer', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="button_background_color"><?php _e( 'Knap Baggrundsfarve', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="color" 
                                               id="button_background_color" 
                                               name="mailchimp_builder_options[button_background_color]" 
                                               value="<?php echo esc_attr( isset( $options['button_background_color'] ) ? $options['button_background_color'] : '#007cba' ); ?>" 
                                               class="small-text" />
                                        <p class="description"><?php _e( 'Baggrundsfarve for knapper i nyhedsbrevet', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="header_image"><?php _e( 'Header Billede', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <?php 
                                        $header_image_id = isset( $options['header_image'] ) ? $options['header_image'] : '';
                                        $header_image_url = $header_image_id ? wp_get_attachment_image_url( $header_image_id, 'medium' ) : '';
                                        ?>
                                        <div class="header-image-container">
                                            <input type="hidden" 
                                                   id="header_image" 
                                                   name="mailchimp_builder_options[header_image]" 
                                                   value="<?php echo esc_attr( $header_image_id ); ?>" />
                                            <div class="header-image-preview" style="margin-bottom: 10px;">
                                                <?php if ( $header_image_url ) : ?>
                                                    <img src="<?php echo esc_url( $header_image_url ); ?>" 
                                                         alt="Header billede" 
                                                         style="max-width: 300px; height: auto; border: 1px solid #ddd;" />
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" 
                                                    id="upload-header-image" 
                                                    class="button">
                                                <?php _e( 'Vælg Header Billede', 'mailchimp-builder' ); ?>
                                            </button>
                                            <button type="button" 
                                                    id="remove-header-image" 
                                                    class="button" 
                                                    style="<?php echo ! $header_image_id ? 'display: none;' : ''; ?>">
                                                <?php _e( 'Fjern Billede', 'mailchimp-builder' ); ?>
                                            </button>
                                        </div>
                                        <p class="description"><?php _e( 'Billede der vises øverst i nyhedsbrevet i stedet for tekst', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="facebook_url"><?php _e( 'Facebook Side URL', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="url" 
                                               id="facebook_url" 
                                               name="mailchimp_builder_options[facebook_url]" 
                                               value="<?php echo esc_attr( isset( $options['facebook_url'] ) ? $options['facebook_url'] : '' ); ?>" 
                                               class="regular-text" 
                                               placeholder="https://www.facebook.com/dinside" />
                                        <p class="description"><?php _e( 'Link til din Facebook side', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="instagram_url"><?php _e( 'Instagram Side URL', 'mailchimp-builder' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="url" 
                                               id="instagram_url" 
                                               name="mailchimp_builder_options[instagram_url]" 
                                               value="<?php echo esc_attr( isset( $options['instagram_url'] ) ? $options['instagram_url'] : '' ); ?>" 
                                               class="regular-text" 
                                               placeholder="https://www.instagram.com/dinside" />
                                        <p class="description"><?php _e( 'Link til din Instagram side', 'mailchimp-builder' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button( __( 'Gem Indstillinger', 'mailchimp-builder' ) ); ?>
                        </form>
                    </div>
                    
                    <div class="newsletter-section">
                        <h2><?php _e( 'Generer og Send Nyhedsbrev', 'mailchimp-builder' ); ?></h2>
                        
                        <div class="newsletter-controls">
                            <button type="button" id="generate-newsletter" class="button button-primary">
                                <?php _e( 'Generer Nyhedsbrev', 'mailchimp-builder' ); ?>
                            </button>
                            
                            <div class="newsletter-subject" style="display: none; margin: 20px 0;">
                                <label for="newsletter-subject"><?php _e( 'Emne:', 'mailchimp-builder' ); ?></label>
                                <input type="text" 
                                       id="newsletter-subject" 
                                       value="<?php echo esc_attr( get_bloginfo( 'name' ) . ' - Nyhedsbrev ' . date( 'F Y' ) ); ?>" 
                                       class="regular-text" />
                            </div>
                            
                            <button type="button" id="send-newsletter" class="button button-secondary" style="display: none;">
                                <?php _e( 'Send Nyhedsbrev', 'mailchimp-builder' ); ?>
                            </button>
                        </div>
                        
                        <div id="newsletter-preview" class="newsletter-preview"></div>
                        <div id="newsletter-message" class="notice" style="display: none;"></div>
                    </div>
                </div>
                
                <div class="sidebar">
                    <div class="sidebar-box">
                        <h3><?php _e( 'Status', 'mailchimp-builder' ); ?></h3>
                        <ul>
                            <li>
                                <strong><?php _e( 'API Forbindelse:', 'mailchimp-builder' ); ?></strong>
                                <?php echo ! empty( $options['mailchimp_api_key'] ) ? $api_status : '<span class="api-status">' . __( 'Ikke konfigureret', 'mailchimp-builder' ) . '</span>'; ?>
                            </li>
                            <li>
                                <strong><?php _e( 'The Events Calendar:', 'mailchimp-builder' ); ?></strong>
                                <?php echo class_exists( 'Tribe__Events__Main' ) ? '<span class="api-status success">✓ Aktiv</span>' : '<span class="api-status error">✗ Ikke installeret</span>'; ?>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="sidebar-box">
                        <h3><?php _e( 'Hjælp', 'mailchimp-builder' ); ?></h3>
                        <p><?php _e( 'Dette plugin genererer automatisk nyhedsbreve baseret på dine seneste indlæg og kommende arrangementer.', 'mailchimp-builder' ); ?></p>
                        <ul>
                            <li><?php _e( '1. Konfigurer din Mailchimp API nøgle', 'mailchimp-builder' ); ?></li>
                            <li><?php _e( '2. Angiv din liste ID', 'mailchimp-builder' ); ?></li>
                            <li><?php _e( '3. Tilpas indstillinger efter behov', 'mailchimp-builder' ); ?></li>
                            <li><?php _e( '4. Generer og send nyhedsbrev', 'mailchimp-builder' ); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
