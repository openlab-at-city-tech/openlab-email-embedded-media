<?php
/**
 * Plugin Name: OpenLab Email Embedded Media
 * Description: Handle embeded content in the email notifications sent by BPGES.
 * Author: OpenLab
 * Text Domain: openlab-email-embedded-media
 * Domain Path: /languages
 * Version: 1.0.2
*/

class OpenLab_Email_Embedded_Media {

    function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'bp_init', array( $this, 'setup' ) );
    }

	public function load_textdomain() {
		load_plugin_textdomain( 'openlab-email-embedded-media', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

    function setup() {
		// Don't allow BPGES to do its native filtering.
		remove_filter( 'ass_clean_content', 'strip_tags', 4 );
		remove_filter( 'ass_clean_content', 'ass_convert_links', 6 );

        add_filter( 'bp_ass_activity_notification_content', array( $this, 'remove_private_media_from_email' ), 300, 4 );
    }

    function remove_private_media_from_email( $content, $activity, $action, $group ) {
        if( $activity->type === 'new_blog_post' ) {
            // Get post url
            $post_url = esc_url( $activity->primary_link );

            // Remove images only for the posts of a non-public groups
            $content = $this->remove_private_images( $content, $post_url );

            // Remove audio multimedia embeds from the email
            $content = $this->remove_multimedia_by_type( $content, 'audio', $post_url );

            // Remove video multimedia embeds from the email
            $content = $this->remove_multimedia_by_type( $content, 'video', $post_url );
        }

        return $content;
    }

    /**
     * Remove private images from the content and change them
     * with a preview text and a link to the original blog post.
     *
     * @param   string  $content        Email HTML content
     * @param   string  $post_link      URL to the blog post
     * @return  string                  HTML with removed private images
     */
    function remove_private_images( $content, $post_link = '#' ) {
        // Skip if content is missing
        if( empty( $content ) ) {
            return;
        }

        // Create new DOM document and load the content
        $document = new DOMDocument();
        $document->loadHTML($content, LIBXML_NOERROR);

        // Get all images in the content
        $images = $document->getElementsByTagName('img');

        // Loop the DOMNodeList in reverse order
        for( $i = $images->length; --$i >= 0; ) {
            $image = $images->item($i);

            // If image is hosted on local WP network site
            if( $this->is_private_site( $image->getAttribute('src') ) ) {
                if( $image->parentNode->tagName === 'a' ) {
                    // Get <a> element
                    $link = $image->parentNode;

                    // Get <figure> element
                    $figure = $image->parentNode->parentNode;

                    // Make sure it's <figure>
                    if( $figure->tagName === 'figure' ) {
                        // Move image to <figure>
                        $figure->appendChild($image);

                        // Remove <a> from within <figure>
                        $figure->removeChild($link);

                        // Remove <figcaption> if it's present within <figure>
                        if( $figure->hasChildNodes() ) {
                            foreach( $figure->childNodes as $figure_child ) {
                                if( $figure_child->tagName == 'figcaption' ) {
                                    $figure->removeChild( $figure_child );
                                }
                            }
                        }
                    }
                }

                // Change <img> with a preview text
                $private_link = $document->createElement( 'a', __( 'View this image by visiting the original post.', 'openlab-email-embedded-media' ) );
                $private_link->setAttribute( 'href', $post_link );
                $image->parentNode->replaceChild( $private_link, $image );
            }
        }

        // Return modified HTML
        return $document->saveHTML();
    }

    /**
     * Remove multimedia embeds (audio/video) from the content and change
     * it with a preview text with a link to the original blog post.
     *
     * @param   string  $content        Email HTML content
     * @param   string  $media_type     Type of the HTML media element (audio/video)
     * @param   string  $post_link      URL to the blog post
     * @return  string                  HTML with removed audio/video multimedia
     */
    function remove_multimedia_by_type( $content, $media_type = '', $post_link = '#' ) {
        // Skip if media type and content is missing
        if( empty( $content ) || empty( $media_type ) ) {
            return;
        }

        // Create new DOM document and load the content
        $document = new DOMDocument();
        $document->loadHTML($content, LIBXML_NOERROR);

        // Find all elements with the specified tag name <media_type>
        $elements = $document->getElementsByTagName($media_type);

        // Loop the DOMNodeList in reverse order
        for( $i = $elements->length; --$i >= 0; ) {
            $element = $elements->item($i);

            // If has <figure> as a parent
            if( $element->parentNode->tagName === 'figure' ) {

                // Get <figure> element
                $figure = $element->parentNode;

                // Remove <figcaption> if it's present within <figure>
                if( $figure->hasChildNodes() ) {
                    foreach( $figure->childNodes as $figure_child ) {
                        if( $figure_child->tagName == 'figcaption' ) {
                            $figure->removeChild( $figure_child );
                        }
                    }
                }
            }

            // Change <audio> with a preview text
			switch ( $media_type ) {
				case 'audio' :
					$link_text = __( 'View this audio by visiting the original post.', 'openlab-email-embedded-media' );
				break;

				case 'video' :
					$link_text = __( 'View this video by visiting the original post.', 'openlab-email-embedded-media' );
				break;

				default :
					$link_text = __( 'View this media by visiting the original post.', 'openlab-email-embedded-media' );
				break;
			}

            $private_link = $document->createElement( 'a', $link_text );
            $private_link->setAttribute( 'href', $post_link );
            $element->parentNode->replaceChild( $private_link, $element );
        }

        // Return modified HTML
        return $document->saveHTML();
    }

    /**
     * Utility function to check if a URL belongs to a local private site.
     *
     * @param string $url
     * @return bool
     */
    function is_private_site( $url ) {
        // Parse provided url
        $url = parse_url( $url );

        // WP Site object
        $site_object = get_site_by_path( $url['host'], $url['path'] );

        if ( ! $site_object ) {
            return false;
        }

        return $site_object->public < 0;
    }
}

new OpenLab_Email_Embedded_Media();
