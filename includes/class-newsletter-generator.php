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
    public function generate_newsletter( $mark_as_sent = false ) {
        $posts = array();
        $events = array();
        
        // Get selected posts if enabled
        if ( isset( $this->options['include_posts'] ) && $this->options['include_posts'] ) {
            $posts = $this->get_selected_posts();
        }
        
        // Get upcoming events if enabled and plugin is active
        if ( isset( $this->options['include_events'] ) && $this->options['include_events'] ) {
            $events = $this->get_upcoming_events();
        }
        
        // Generate HTML content
        $content = $this->build_newsletter_html( $posts, $events, $mark_as_sent );
        
        return array(
            'content' => $content,
            'post_ids' => array_map( function( $post ) { return $post->ID; }, $posts )
        );
    }
    
    /**
     * Generate newsletter content and return only HTML (for backward compatibility)
     */
    public function generate_newsletter_html( $mark_as_sent = false ) {
        $result = $this->generate_newsletter( $mark_as_sent );
        return $result['content'];
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
        
        $events_end_date = isset( $this->options['events_end_date'] ) ? $this->options['events_end_date'] : '';
        $group_recurring_events = isset( $this->options['group_recurring_events'] ) ? $this->options['group_recurring_events'] : false;
        $recurring_event_category = isset( $this->options['recurring_event_category'] ) ? $this->options['recurring_event_category'] : '';
        
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
            'posts_per_page' => -1, // Get all events, no limit
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => '_EventStartDate',
            'meta_query' => $meta_query
        );
        
        $events = get_posts( $args );
        
        // Group recurring events if enabled and category is specified
        if ( $group_recurring_events && ! empty( $recurring_event_category ) ) {
            return $this->group_recurring_events( $events, $recurring_event_category );
        }
        
        return $events;
    }
    
    /**
     * Group recurring events by category
     */
    private function group_recurring_events( $events, $recurring_category_slug ) {
        $grouped_events = array();
        $recurring_events_by_title = array();
        $regular_events = array();
        
        foreach ( $events as $event ) {
            // Check if event belongs to the recurring category
            $event_categories = wp_get_post_terms( $event->ID, 'tribe_events_cat', array( 'fields' => 'slugs' ) );
            
            if ( in_array( $recurring_category_slug, $event_categories ) ) {
                // This is a recurring event - group by title
                $title = $event->post_title;
                
                if ( ! isset( $recurring_events_by_title[$title] ) ) {
                    $recurring_events_by_title[$title] = array(
                        'main_event' => $event,
                        'dates' => array()
                    );
                }
                
                // Add this date to the list
                $start_date = get_post_meta( $event->ID, '_EventStartDate', true );
                $recurring_events_by_title[$title]['dates'][] = array(
                    'event_id' => $event->ID,
                    'start_date' => $start_date,
                    'venue_id' => get_post_meta( $event->ID, '_EventVenueID', true )
                );
            } else {
                // Regular event - add as-is
                $regular_events[] = $event;
            }
        }
        
        // Convert grouped recurring events back to event objects with additional data
        foreach ( $recurring_events_by_title as $title => $group_data ) {
            $main_event = $group_data['main_event'];
            
            // Sort dates chronologically
            usort( $group_data['dates'], function( $a, $b ) {
                return strtotime( $a['start_date'] ) - strtotime( $b['start_date'] );
            });
            
            // Add custom property to track grouped dates
            $main_event->grouped_dates = $group_data['dates'];
            $main_event->is_grouped_recurring = true;
            
            $grouped_events[] = $main_event;
        }
        
        // Combine regular events and grouped recurring events
        $all_events = array_merge( $regular_events, $grouped_events );
        
        // Sort all events by the earliest date
        usort( $all_events, function( $a, $b ) {
            $date_a = isset( $a->is_grouped_recurring ) && $a->is_grouped_recurring 
                ? $a->grouped_dates[0]['start_date'] 
                : get_post_meta( $a->ID, '_EventStartDate', true );
                
            $date_b = isset( $b->is_grouped_recurring ) && $b->is_grouped_recurring 
                ? $b->grouped_dates[0]['start_date'] 
                : get_post_meta( $b->ID, '_EventStartDate', true );
                
            return strtotime( $date_a ) - strtotime( $date_b );
        });
        
        return $all_events;
    }

    /**
     * Build newsletter HTML
     */
    private function build_newsletter_html( $posts, $events, $mark_as_sent = false ) {
        $excerpt_length = isset( $this->options['post_excerpt_length'] ) ? intval( $this->options['post_excerpt_length'] ) : 150;
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - Nyhedsbrev</title>
            <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400,600&family=Oswald:wght@700&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    line-height: 1.6;
                    color: #000000;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f4f4f4;
                    text-align: center;
                }
                .newsletter-container {
                    background-color: #ffffff;
                    padding: 0;
                    margin: 0;
                    width: 100%;
                }
                .header {
                    text-align: center;
                    margin: 0;
                    padding: 0;
                }
                .header h1 {
                    font-family: 'Oswald', Arial, sans-serif;
                    font-weight: 700;
                    color: #000000;
                    margin: 0;
                    font-size: 36px;
                    text-align: center;
                }
                .header p {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    color: #000000;
                    text-align: center;
                }
                .section {
                    margin-bottom: 40px;
                    text-align: center;
                }
                .section-title {
                    font-family: 'Oswald', Arial, sans-serif;
                    font-weight: 700;
                    color: #000000;
                    font-size: 36px;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .item-content {
                    padding: 10px 40px;
                }
                .item-title {
                    font-family: 'Oswald', Arial, sans-serif;
                    font-weight: 700;
                    font-size: 36px;
                    line-height: 1.2;
                    margin-bottom: 15px;
                    text-align: center;
                    color: #000000;
                }
                .item-title a {
                    color: #000000;
                    text-decoration: none;
                }
                .item-title a:hover {
                    text-decoration: underline;
                }
                .item-meta {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    color: #000000;
                    font-size: 14px;
                    margin-bottom: 10px;
                    text-align: center;
                }
                .item-excerpt {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    margin-bottom: 20px;
                    line-height: 1.5;
                    color: #000000;
                    text-align: center;
                }
                .item-image {
                    margin-bottom: 15px;
                    text-align: center;
                    width: 100%;
                }
                .item-image img {
                    max-width: 100%;
                    height: auto;
                    width: 100%;
                }
                /* Responsive image containers */
                figure {
                    margin: 0 auto 15px auto !important;
                    width: 100% !important;
                    max-width: 600px !important;
                    overflow: hidden !important;
                    display: block !important;
                }
                figure img {
                    width: 100% !important;
                    height: auto !important;
                    aspect-ratio: 882/463 !important;
                    object-fit: cover !important;
                    object-position: center !important;
                    display: block !important;
                    margin: 0 auto !important;
                }
                @media only screen and (max-width: 600px) {
                    body {
                        padding: 10px;
                        max-width: 100%;
                    }
                    .item-content {
                        padding: 10px 20px;
                    }
                    .item-title {
                        font-size: 28px;
                    }
                    .section-title {
                        font-size: 28px;
                    }
                    .header h1 {
                        font-size: 28px;
                    }
                    figure {
                        max-width: 100% !important;
                    }
                    .sponsors-container {
                        flex-direction: column;
                        gap: 20px;
                    }
                    .sponsor-item {
                        min-width: auto;
                        max-width: none;
                    }
                }
                .event-date {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 600;
                    color: black;
                    font-size: 18px;
                    display: inline-block;
                    margin-bottom: 10px;
                    text-align: center;
                }
                .event-dates-list {
                    text-align: center;
                    margin-bottom: 15px;
                }
                .event-dates-list .event-date {
                    display: block;
                    margin-bottom: 5px;
                    font-size: 16px;
                    padding: 3px 0;
                }
                .event-dates-list .event-date:first-child {
                    font-weight: 700;
                    font-size: 18px;
                    margin-bottom: 8px;
                    color: #000000;
                }
                .event-dates-list .event-date:not(:first-child) {
                    color: #666666;
                    font-weight: 500;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    background-color: <?php echo esc_attr( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa' ); ?>;
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    color: #ffffff;
                    font-size: 14px;
                }
                .footer p {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    color: #ffffff;
                    text-align: center;
                }
                .footer a {
                    color: #ffffff;
                    text-decoration: none;
                }
                .footer a:hover {
                    text-decoration: underline;
                }
                .read-more {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 600;
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 25px;
                    background-color: <?php echo esc_attr( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa' ); ?>;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 14px;
                    text-align: center;
                }
                .read-more:hover {
                    background-color: <?php echo esc_attr( $this->adjust_color_brightness( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa', -0.2 ) ); ?>;
                }
                .header-image {
                    margin-bottom: -7px;
                    text-align: center;
                }
                .header-image img {
                    max-width: 100%;
                    height: auto;
                }
                .separator-section {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    margin: 40px 0;
                    text-align: center;
                    padding: 20px;
                    background-color: #f9f9f9;
                    border-radius: 8px;
                    color: #000000;
                }
                .separator-section p {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    color: #000000;
                    text-align: center;
                }
                .separator-section h1, .separator-section h2, .separator-section h3, .separator-section h4, .separator-section h5, .separator-section h6 {
                    font-family: 'Oswald', Arial, sans-serif;
                    font-weight: 700;
                    color: #000000;
                    text-align: center;
                }
                .social-links {
                    margin-top: 20px;
                    text-align: center;
                }
                .social-links a {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 400;
                    display: inline-block;
                    margin: 0 10px;
                    padding: 10px 15px;
                    background-color: <?php echo esc_attr( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa' ); ?>;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 14px;
                    text-align: center;
                }
                .social-links a:hover {
                    background-color: <?php echo esc_attr( $this->adjust_color_brightness( isset( $this->options['button_background_color'] ) ? $this->options['button_background_color'] : '#0073aa', -0.2 ) ); ?>;
                }
                .sponsors-section {
                    margin: 40px 0;
                    text-align: center;
                    padding-bottom: 40px;
                    border-bottom: 2px solid #f9f9f9;
                }
                .sponsors-container {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 40px;
                    flex-wrap: wrap;
                    margin-top: 20px;
                }
                .sponsor-item {
                    flex: 1;
                    min-width: 200px;
                    max-width: 250px;
                    text-align: center;
                }
                .sponsor-logo {
                    margin-bottom: 10px;
                }
                .sponsor-logo img {
                    max-width: 200px;
                    max-height: 200px;
                    width: auto;
                    height: auto;
                    object-fit: contain;
                }
                .sponsor-name {
                    font-family: 'Raleway', Arial, sans-serif;
                    font-weight: 600;
                    font-size: 14px;
                    color: #000000;
                }
                .sponsor-name a {
                    color: #000000;
                    text-decoration: none;
                }
                .sponsor-name a:hover {
                    text-decoration: underline;
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
                </div>
                
                <?php if ( ! empty( $posts ) ) : ?>
                <div class="section">
                    <?php foreach ( $posts as $post ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php echo $this->get_featured_image_html( $post->ID ); ?></a>
                        <div class="item-content">
                            <div class="item-title">
                                <?php echo esc_html( $post->post_title ); ?>
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
                    echo '<div class="separator-section">' . $this->sanitize_separator_html( $this->options['separator_html'] ) . '</div>';
                }
                ?>
                
                <?php 
                // Sponsors section
                $sponsors = isset( $this->options['sponsors'] ) ? $this->options['sponsors'] : array();
                
                if ( ! empty( $sponsors ) ) :
                ?>
                <div class="sponsors-section">
                    <h2 class="section-title">Nye sponsorer</h2>
                    <div class="sponsors-container">
                        <?php foreach ( $sponsors as $sponsor ) : ?>
                            <?php 
                            $sponsor_post = get_post( $sponsor['id'] );
                            if ( $sponsor_post ) :
                                $sponsor_url = get_permalink( $sponsor_post->ID );
                                $sponsor_image_id = get_post_meta( $sponsor_post->ID, 'allround-cpt_logo_id', true );
                                $sponsor_logo_url = wp_get_attachment_url( $sponsor_image_id );
                            ?>
                            <div class="sponsor-item">
                                <?php if ( ! empty( $sponsor_logo_url ) ) : ?>
                                    <div class="sponsor-logo">
                                        <a href="<?php echo esc_url( $sponsor_url ); ?>" target="_blank">
                                            <img src="<?php echo esc_url( $sponsor_logo_url ); ?>" alt="<?php echo esc_attr( $sponsor_post->post_title ); ?> logo" />
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="sponsor-name">
                                    <a href="<?php echo esc_url( $sponsor_url ); ?>" target="_blank">
                                        <?php echo esc_html( $sponsor_post->post_title ); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $events ) ) : ?>
                <div class="section">
                    <h2 class="section-title">Arrangementer</h2>
                    <?php foreach ( $events as $event ) : ?>
                        <?php
                        // Check if this is a grouped recurring event
                        $is_grouped = isset( $event->is_grouped_recurring ) && $event->is_grouped_recurring;
                        
                        if ( $is_grouped ) {
                            // This is a grouped recurring event - show all dates
                            $grouped_dates = $event->grouped_dates;
                            $first_date = $grouped_dates[0];
                            $venue_id = $first_date['venue_id'];
                            $venue_name = $venue_id ? get_the_title( $venue_id ) : '';
                        } else {
                            // Regular single event
                            $start_date = get_post_meta( $event->ID, '_EventStartDate', true );
                            $end_date = get_post_meta( $event->ID, '_EventEndDate', true );
                            $venue_id = get_post_meta( $event->ID, '_EventVenueID', true );
                            $venue_name = $venue_id ? get_the_title( $venue_id ) : '';
                        }
                        ?>
                        <a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>"><?php echo $this->get_featured_image_html( $event->ID ); ?></a>
                        <div class="item-content">
                            <div class="item-title">
                                <?php echo esc_html( $event->post_title ); ?>
                            </div>
                            
                            <?php if ( $is_grouped ) : ?>
                                <!-- Grouped recurring event dates -->
                                <div class="event-dates-list">
                                    <?php foreach ( $grouped_dates as $date_info ) : ?>
                                        <div class="event-date">
                                            <?php echo date_i18n( 'j. F Y \k\l. H:i', strtotime( $date_info['start_date'] ) ); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <!-- Single event date -->
                                <div class="event-date">
                                    <?php echo date_i18n( 'j. F Y \k\l. H:i', strtotime( $start_date ) ); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( $venue_name ) : ?>
                                <?php $venue_object = tribe_get_venue_object( $venue_id ); ?>
                                <div class="item-meta">
                                    <?php echo esc_html( $venue_name ); ?>
                                    <?php if ( $venue_object ) : ?>
                                        <br><?php echo esc_html( $venue_object->address ); ?>
                                        <br><?php echo $venue_object->zip . ' ' . $venue_object->city; ?>
                                        <br><br>[<a href="<?php echo esc_url( $venue_object->directions_link ); ?>" target="_blank">📍 Find vej</a>]
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="item-excerpt">
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
                            <a href="<?php echo esc_url( $this->options['facebook_url'] ); ?>" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/Facebook-ikon.webp'; ?>" width="20" height="20" alt="Facebook ikon"></a>
                        <?php endif; ?>
                        <?php if ( isset( $this->options['instagram_url'] ) && ! empty( $this->options['instagram_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $this->options['instagram_url'] ); ?>" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/Instagram-ikon.webp'; ?>" width="20" height="20" alt="Instagram ikon"></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        $content = ob_get_clean();
        
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
        
        // Use responsive approach instead of fixed dimensions
        return sprintf(
            '<figure style="margin: 0 auto 15px auto; width: 100%%; max-width: %spx; overflow: hidden; display: block;">
                <img src="%s" alt="%s" style="width: 100%%; height: auto; aspect-ratio: 882/463; object-fit: cover; object-position: center; display: block; margin: 0 auto;" />
            </figure>',
            $max_width,
            esc_url( $image_url ),
            esc_attr( $image_alt )
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
    
    /**
     * Sanitize separator HTML with extended CSS properties allowed
     *
     * @param string $html The HTML to sanitize
     * @return string Sanitized HTML
     */
    private function sanitize_separator_html( $html ) {
        // Allow specific safe HTML tags with style attributes
        $allowed_tags = array(
            'div'    => array( 'class' => true, 'id' => true, 'style' => true ),
            'span'   => array( 'class' => true, 'id' => true, 'style' => true ),
            'p'      => array( 'class' => true, 'id' => true, 'style' => true ),
            'h1'     => array( 'class' => true, 'id' => true, 'style' => true ),
            'h2'     => array( 'class' => true, 'id' => true, 'style' => true ),
            'h3'     => array( 'class' => true, 'id' => true, 'style' => true ),
            'h4'     => array( 'class' => true, 'id' => true, 'style' => true ),
            'h5'     => array( 'class' => true, 'id' => true, 'style' => true ),
            'h6'     => array( 'class' => true, 'id' => true, 'style' => true ),
            'strong' => array( 'class' => true, 'style' => true ),
            'em'     => array( 'class' => true, 'style' => true ),
            'b'      => array( 'class' => true, 'style' => true ),
            'i'      => array( 'class' => true, 'style' => true ),
            'a'      => array( 'href' => true, 'class' => true, 'id' => true, 'style' => true, 'target' => true ),
            'img'    => array( 'src' => true, 'alt' => true, 'class' => true, 'id' => true, 'style' => true, 'width' => true, 'height' => true ),
            'br'     => array(),
            'hr'     => array( 'class' => true, 'style' => true ),
            'ul'     => array( 'class' => true, 'style' => true ),
            'ol'     => array( 'class' => true, 'style' => true ),
            'li'     => array( 'class' => true, 'style' => true )
        );

        // Add filter to allow more CSS properties
        add_filter( 'safe_style_css', array( $this, 'add_safe_css_properties' ) );
        
        $sanitized = wp_kses( $html, $allowed_tags );
        
        // Remove the filter
        remove_filter( 'safe_style_css', array( $this, 'add_safe_css_properties' ) );

        return $sanitized;
    }

    /**
     * Add additional safe CSS properties
     */
    public function add_safe_css_properties( $styles ) {
        $additional_styles = array(
            'display', 'position', 'top', 'right', 'bottom', 'left', 'z-index',
            'flex', 'flex-direction', 'flex-wrap', 'flex-basis', 'flex-grow', 'flex-shrink',
            'align-items', 'align-content', 'align-self', 'justify-content', 'justify-items', 'justify-self',
            'gap', 'row-gap', 'column-gap',
            'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows', 'grid-template-areas',
            'grid-area', 'grid-column', 'grid-row'
        );
        
        return array_merge( $styles, $additional_styles );
    }
    
    /**
     * Mark specific posts as sent
     */
    public function mark_posts_as_sent( $post_ids ) {
        if ( ! is_array( $post_ids ) ) {
            $post_ids = array( $post_ids );
        }
        
        foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, '_mailchimp_newsletter_sent', time() );
        }
    }
    
    /**
     * Get IDs of posts that would be included in newsletter
     */
    public function get_newsletter_post_ids() {
        $post_ids = array();
        
        // Get selected posts if enabled
        if ( isset( $this->options['include_posts'] ) && $this->options['include_posts'] ) {
            $posts = $this->get_selected_posts();
            foreach ( $posts as $post ) {
                $post_ids[] = $post->ID;
            }
        }
        
        return $post_ids;
    }
    
    /**
     * Get selected posts based on saved post IDs and their order
     */
    private function get_selected_posts() {
        $selected_posts = isset( $this->options['selected_posts'] ) ? $this->options['selected_posts'] : array();
        
        if ( empty( $selected_posts ) ) {
            // Fallback to latest posts if no posts are selected
            return $this->get_latest_posts();
        }
        
        $posts = array();
        
        // Get posts in the specified order
        foreach ( $selected_posts as $post_data ) {
            $post_id = intval( $post_data['id'] );
            $post = get_post( $post_id );
            
            if ( $post && $post->post_status === 'publish' ) {
                $posts[] = $post;
            }
        }
        
        return $posts;
    }
}
