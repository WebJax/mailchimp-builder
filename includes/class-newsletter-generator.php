<?php
/**
 * Newsletter Generator Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Mailchimp_Builder_Newsletter_Generator {
    
    private $options;
    
    public function __construct() {
        $this->options = get_option( 'mailchimp_builder_options', array() );
    }
    
    /**
     * Generate newsletter content
     */
    public function generate_newsletter() {
        $posts = array();
        $events = array();
        
        // Get latest posts if enabled
        if ( isset( $this->options['include_posts'] ) && $this->options['include_posts'] ) {
            $posts = $this->get_latest_posts();
        }
        
        // Get upcoming events if enabled and plugin is active
        if ( isset( $this->options['include_events'] ) && $this->options['include_events'] ) {
            $events = $this->get_upcoming_events();
        }
        
        // Generate HTML content
        return $this->build_newsletter_html( $posts, $events );
    }
    
    /**
     * Get latest posts
     */
    private function get_latest_posts() {
        $posts_limit = isset( $this->options['posts_limit'] ) ? intval( $this->options['posts_limit'] ) : 5;
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $posts_limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_mailchimp_newsletter_sent',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        return get_posts( $args );
    }
    
    /**
     * Get upcoming events from The Events Calendar
     */
    private function get_upcoming_events() {
        // Check if The Events Calendar is active
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            return array();
        }
        
        $events_limit = isset( $this->options['events_limit'] ) ? intval( $this->options['events_limit'] ) : 5;
        $events_end_date = isset( $this->options['events_end_date'] ) ? $this->options['events_end_date'] : '';
        
        $meta_query = array(
            array(
                'key' => '_EventStartDate',
                'value' => date( 'Y-m-d H:i:s' ),
                'compare' => '>=',
                'type' => 'DATETIME'
            )
        );
        
        // Add end date filter if specified
        if ( ! empty( $events_end_date ) ) {
            $meta_query[] = array(
                'key' => '_EventStartDate',
                'value' => $events_end_date . ' 23:59:59',
                'compare' => '<=',
                'type' => 'DATETIME'
            );
        }
        
        $args = array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'posts_per_page' => $events_limit,
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => '_EventStartDate',
            'meta_query' => $meta_query
        );
        
        return get_posts( $args );
    }
    
    /**
     * Build newsletter HTML
     */
    private function build_newsletter_html( $posts, $events ) {
        $excerpt_length = isset( $this->options['post_excerpt_length'] ) ? intval( $this->options['post_excerpt_length'] ) : 150;
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - Nyhedsbrev</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f4f4f4;
                }
                .newsletter-container {
                    background-color: #ffffff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header {
                    text-align: center;
                    border-bottom: 3px solid #0073aa;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .header h1 {
                    color: #0073aa;
                    margin: 0;
                    font-size: 28px;
                }
                .section {
                    margin-bottom: 40px;
                }
                .section-title {
                    color: #0073aa;
                    font-size: 22px;
                    border-bottom: 2px solid #e1e1e1;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                }
                .item {
                    margin-bottom: 25px;
                    padding: 20px;
                    border: 1px solid #e1e1e1;
                    border-radius: 5px;
                    background-color: #fafafa;
                }
                .item-title {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .item-title a {
                    color: #0073aa;
                    text-decoration: none;
                }
                .item-title a:hover {
                    text-decoration: underline;
                }
                .item-meta {
                    color: #666;
                    font-size: 14px;
                    margin-bottom: 10px;
                }
                .item-excerpt {
                    line-height: 1.5;
                }
                .item-image {
                    margin-bottom: 15px;
                    text-align: center;
                }
                .item-image img {
                    max-width: 100%;
                    height: auto;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .event-date {
                    background-color: #0073aa;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                    display: inline-block;
                    margin-bottom: 10px;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 2px solid #e1e1e1;
                    color: #666;
                    font-size: 14px;
                }
                .read-more {
                    display: inline-block;
                    margin-top: 10px;
                    padding: 8px 15px;
                    background-color: <?php echo esc_attr( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa' ); ?>;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .read-more:hover {
                    background-color: <?php echo esc_attr( $this->adjust_color_brightness( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa', -0.2 ) ); ?>;
                }
                .header-image {
                    margin-bottom: 20px;
                }
                .header-image img {
                    max-width: 100%;
                    height: auto;
                    border-radius: 8px;
                }
                .separator-section {
                    margin: 40px 0;
                    text-align: center;
                    padding: 20px;
                    background-color: #f9f9f9;
                    border-radius: 8px;
                }
                .social-links {
                    margin-top: 20px;
                }
                .social-links a {
                    display: inline-block;
                    margin: 0 10px;
                    padding: 10px 15px;
                    background-color: <?php echo esc_attr( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa' ); ?>;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .social-links a:hover {
                    background-color: <?php echo esc_attr( $this->adjust_color_brightness( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa', -0.2 ) ); ?>;
                }
            </style>
        </head>
        <body>
            <div class="newsletter-container">
                <div class="header">
                    <?php 
                    $header_image_id = isset( $this->options['header_image'] ) ? $this->options['header_image'] : '';
                    if ( $header_image_id ) {
                        $header_image_url = wp_get_attachment_image_url( $header_image_id, 'full' );
                        if ( $header_image_url ) {
                            echo '<div class="header-image"><img src="' . esc_url( $header_image_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" /></div>';
                        }
                    } else {
                        echo '<h1>' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
                    }
                    ?>
                    <p>Nyhedsbrev - <?php echo date_i18n( 'F Y' ); ?></p>
                </div>
                
                <?php if ( ! empty( $posts ) ) : ?>
                <div class="section">
                    <h2 class="section-title">📝 Seneste Indlæg</h2>
                    <?php foreach ( $posts as $post ) : ?>
                        <div class="item">
                            <?php echo $this->get_featured_image_html( $post->ID ); ?>
                            <div class="item-title">
                                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
                            </div>
                            <div class="item-meta">
                                Udgivet: <?php echo date_i18n( 'j. F Y', strtotime( $post->post_date ) ); ?>
                            </div>
                            <div class="item-excerpt">
                                <?php echo esc_html( $this->get_excerpt( $post->post_content, $excerpt_length ) ); ?>
                                <br><a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="read-more">Læs mere</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php 
                // Insert separator HTML between posts and events
                if ( ! empty( $posts ) && ! empty( $events ) && isset( $this->options['separator_html'] ) && ! empty( $this->options['separator_html'] ) ) {
                    echo '<div class="separator-section">' . wp_kses_post( $this->options['separator_html'] ) . '</div>';
                }
                ?>
                
                <?php if ( ! empty( $events ) ) : ?>
                <div class="section">
                    <h2 class="section-title">📅 Kommende Arrangementer</h2>
                    <?php foreach ( $events as $event ) : ?>
                        <?php
                        $start_date = get_post_meta( $event->ID, '_EventStartDate', true );
                        $end_date = get_post_meta( $event->ID, '_EventEndDate', true );
                        $venue_id = get_post_meta( $event->ID, '_EventVenueID', true );
                        $venue_name = $venue_id ? get_the_title( $venue_id ) : '';
                        ?>
                        <div class="item">
                            <?php echo $this->get_featured_image_html( $event->ID ); ?>
                            <div class="event-date">
                                <?php echo date_i18n( 'j. F Y \k\l. H:i', strtotime( $start_date ) ); ?>
                            </div>
                            <div class="item-title">
                                <a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>"><?php echo esc_html( $event->post_title ); ?></a>
                            </div>
                            <?php if ( $venue_name ) : ?>
                            <div class="item-meta">
                                📍 <?php echo esc_html( $venue_name ); ?>
                            </div>
                            <?php endif; ?>
                            <div class="item-excerpt">
                                <?php echo esc_html( $this->get_excerpt( $event->post_content, $excerpt_length ) ); ?>
                                <br><a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>" class="read-more">Læs mere</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="footer">
                    <p>Dette nyhedsbrev er sendt fra <strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong></p>
                    <p>Besøg vores hjemmeside: <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_url( home_url() ); ?></a></p>
                    
                    <?php if ( ( isset( $this->options['facebook_url'] ) && ! empty( $this->options['facebook_url'] ) ) || ( isset( $this->options['instagram_url'] ) && ! empty( $this->options['instagram_url'] ) ) ) : ?>
                    <div class="social-links">
                        <p>Følg os på sociale medier:</p>
                        <?php if ( isset( $this->options['facebook_url'] ) && ! empty( $this->options['facebook_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $this->options['facebook_url'] ); ?>" target="_blank">📘 Facebook</a>
                        <?php endif; ?>
                        <?php if ( isset( $this->options['instagram_url'] ) && ! empty( $this->options['instagram_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $this->options['instagram_url'] ); ?>" target="_blank">📷 Instagram</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        $content = ob_get_clean();
        
        // Mark posts as sent
        foreach ( $posts as $post ) {
            update_post_meta( $post->ID, '_mailchimp_newsletter_sent', time() );
        }
        
        return $content;
    }
    
    /**
     * Get excerpt from content
     */
    private function get_excerpt( $content, $length = 150 ) {
        $content = strip_tags( $content );
        $content = str_replace( array( "\r", "\n", "\t" ), ' ', $content );
        $content = trim( preg_replace( '/\s+/', ' ', $content ) );
        
        if ( strlen( $content ) <= $length ) {
            return $content;
        }
        
        $excerpt = substr( $content, 0, $length );
        $last_space = strrpos( $excerpt, ' ' );
        
        if ( $last_space !== false ) {
            $excerpt = substr( $excerpt, 0, $last_space );
        }
        
        return $excerpt . '...';
    }
    
    /**
     * Get featured image HTML for email
     */
    private function get_featured_image_html( $post_id, $max_width = 600 ) {
        if ( ! isset( $this->options['include_featured_images'] ) || ! $this->options['include_featured_images'] ) {
            return '';
        }
        
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumbnail_id ) {
            return '';
        }
        
        $image_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
        $image_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
        
        if ( ! $image_url ) {
            return '';
        }
        
        // Get image dimensions
        $image_meta = wp_get_attachment_metadata( $thumbnail_id );
        $width = isset( $image_meta['width'] ) ? $image_meta['width'] : $max_width;
        $height = isset( $image_meta['height'] ) ? $image_meta['height'] : 'auto';
        
        // Calculate responsive width
        if ( $width > $max_width ) {
            $ratio = $max_width / $width;
            $width = $max_width;
            if ( is_numeric( $height ) ) {
                $height = round( $height * $ratio );
            }
        }
        
        return sprintf(
            '<img src="%s" alt="%s" style="width: %spx; height: %s; max-width: 100%%; border-radius: 4px; margin-bottom: 15px; display: block;" />',
            esc_url( $image_url ),
            esc_attr( $image_alt ),
            $width,
            is_numeric( $height ) ? $height . 'px' : $height
        );
    }
    
    /**
     * Adjust color brightness
     */
    private function adjust_color_brightness( $hex, $percent ) {
        // Remove # if present
        $hex = ltrim( $hex, '#' );
        
        // Convert hex to RGB
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        // Adjust brightness
        $r = max( 0, min( 255, $r + ( $r * $percent ) ) );
        $g = max( 0, min( 255, $g + ( $g * $percent ) ) );
        $b = max( 0, min( 255, $b + ( $b * $percent ) ) );
        
        // Convert back to hex
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}
