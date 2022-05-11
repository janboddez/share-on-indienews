<?php
/**
 * Displays the "IndieNews" meta box, and stores its value.
 *
 * @package Share_On_IndieNews
 */

namespace Share_On_IndieNews;

/**
 * Post handler class.
 */
class Post_Handler {
	public const DEFAULT_URL = 'https://news.indieweb.org/en';

	public const DEFAULT_POST_TYPES = array( 'post', 'page' );

	/**
	 * Interacts with WordPress's Plugin API.
	 *
	 * @since 0.1.0
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'transition_post_status', array( $this, 'update_meta' ), 11, 3 );
		add_action( 'transition_post_status', array( $this, 'schedule_webmention' ), 999, 3 );
		add_action( 'share_on_indienews_send_webmention', array( $this, 'send_webmention' ) );
	}

	/**
	 * Registers a new meta box.
	 *
	 * @since 0.1.0
	 */
	public function add_meta_box() {
		$post_types = (array) apply_filters( 'share_on_indienews_post_types', self::DEFAULT_POST_TYPES );

		if ( empty( $post_types ) ) {
			// Sharing disabled for all post types.
			return;
		}

		// Add meta box, for those post types that are supported.
		add_meta_box(
			'share-on-indienews',
			__( 'Share on IndieNews', 'share-on-indienews' ),
			array( $this, 'render_meta_box' ),
			$post_types,
			'side',
			'default'
		);
	}

	/**
	 * Renders meta box.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'share_on_indienews_nonce' );

		$check = array( '', '1' );

		if ( apply_filters( 'share_on_indienews_optin', false ) ) {
			$check = array( '1' ); // Make sharing opt-in.
		}
		?>
		<label>
			<input type="checkbox" name="share_on_indienews" value="1" <?php checked( in_array( get_post_meta( $post->ID, '_share_on_indienews', true ), $check, true ) ); ?>>
			<?php esc_html_e( 'Share on IndieWeb News', 'share-on-indienews' ); ?>
		</label>
		<?php
		$url = get_post_meta( $post->ID, '_share_on_indienews_url', true );

		if ( '' !== $url && false !== wp_http_validate_url( $url ) ) :
			$url_parts = wp_parse_url( $url );

			$display_url  = '<span class="screen-reader-text">' . $url_parts['scheme'] . '://';
			$display_url .= ( ! empty( $url_parts['user'] ) ? $url_parts['user'] . ( ! empty( $url_parts['pass'] ) ? ':' . $url_parts['pass'] : '' ) . '@' : '' ) . '</span>';
			$display_url .= '<span class="ellipsis">' . mb_substr( $url_parts['host'] . $url_parts['path'], 0, 20 ) . '</span><span class="screen-reader-text">' . mb_substr( $url_parts['host'] . $url_parts['path'], 20 ) . '</span>';
			?>
			<p class="description">
				<?php /* translators: toot URL */ ?>
				<?php printf( esc_html__( 'Shared at %s', 'share-on-indienews' ), '<a class="url" href="' . esc_url( $url ) . '">' . $display_url . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>
			<?php
		endif;
	}

	/**
	 * Adds admin scripts and styles.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix Current WP-Admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'post-new.php' !== $hook_suffix && 'post.php' !== $hook_suffix ) {
			// Not an "Edit Post" screen.
			return;
		}

		global $post;

		if ( empty( $post ) ) {
			// Can't do much without a `$post` object.
			return;
		}

		if ( ! in_array( $post->post_type, (array) apply_filters( 'share_on_indienews_post_types', self::DEFAULT_POST_TYPES ), true ) ) {
			// Unsupported post type.
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style( 'share-on-indienews', plugins_url( '/assets/share-on-indienews.css', dirname( __FILE__ ) ), array(), '0.1.0' );
	}

	/**
	 * Handles metadata. Runs immediately after a post is saved.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $new_status Old post status.
	 * @param string  $old_status New post status.
	 * @param WP_Post $post       Post object.
	 */
	public function update_meta( $new_status, $old_status, $post ) {
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			// Prevent double posting.
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		if ( ! isset( $_POST['share_on_indienews_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['share_on_indienews_nonce'] ), basename( __FILE__ ) ) ) {
			// Nonce missing or invalid.
			return;
		}

		if ( ! in_array( $post->post_type, (array) apply_filters( 'share_on_indienews_post_types', self::DEFAULT_POST_TYPES ), true ) ) {
			// Unsupported post type.
			return;
		}

		if ( 'publish' !== $new_status ) {
			// Status is something other than `publish`.
			return;
		}

		if ( post_password_required( $post ) ) {
			// Post is password-protected.
			return;
		}

		$default_url = apply_filters( 'share_on_indienews_default_url', self::DEFAULT_URL );

		if ( isset( $_POST['share_on_indienews'] ) && ! post_password_required( $post ) ) {
			// If sharing enabled and post not password-protected.
			if ( '' === get_post_meta( $post->ID, '_share_on_indienews_url', true ) ) {
				update_post_meta( $post->ID, '_share_on_indienews_url', $default_url );
			}
		} else {
			if ( get_post_meta( $post->ID, '_share_on_indienews_url', true ) === $default_url ) {
				// Disable sharing (for new posts).
				delete_post_meta( $post->ID, '_share_on_indienews_url' );
			}
		}
	}

	/**
	 * Schedules sending the webmention.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $new_status Old post status.
	 * @param string  $old_status New post status.
	 * @param WP_Post $post       Post object.
	 */
	public function schedule_webmention( $new_status, $old_status, $post ) {
		wp_schedule_single_event( time() + 300, 'share_on_indienews_send_webmention', array( $post->ID ) );
	}

	/**
	 * Pushes a post to IndieWeb News.
	 *
	 * We're doing this without relying on other plugins, as those typically
	 * won't detect our syndication link's endpoint, which isn't in
	 * `$post->post_content`. Runs in the background.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function send_webmention( $post_id ) {
		$post = get_post( $post_id );

		if ( ! in_array( $post->post_type, (array) apply_filters( 'share_on_indienews_post_types', self::DEFAULT_POST_TYPES ), true ) ) {
			// Post type no longer supported.
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			// Status has changed to something other than `publish`.
			return;
		}

		if ( post_password_required( $post ) ) {
			// Post is now password-protected.
			return;
		}

		$default_url = apply_filters( 'share_on_indienews_default_url', self::DEFAULT_URL );
		$url         = get_post_meta( $post->ID, '_share_on_indienews_url', true );

		if ( empty( $url ) ) {
			// Nothing to do.
			return;
		}

		if ( false === wp_http_validate_url( $url ) ) {
			// Invalid URL.
			return;
		}

		if ( $url !== $default_url ) {
			// Prevent posts from being shared more than once. We should
			// probably take this out afterward, so that post updates are
			// properly synced.
			return;
		}

		$endpoint = $this->discover_endpoint( $url );

		if ( empty( $endpoint ) ) {
			return;
		}

		// Send the webmention.
		$response = wp_safe_remote_post(
			esc_url_raw( $endpoint ),
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => 1048576,
				'body'                => array(
					'source' => get_permalink( $post->ID ),
					'target' => $url,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Something went wrong.
			error_log( '[Share on IndieWeb News] Could not send webmention.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		$location = wp_remote_retrieve_header( $response, 'location' );

		// Update syndication URL.
		if ( ! empty( $location ) && false !== wp_http_validate_url( $location ) ) {
			// This is the URL we want to show on the front end.
			update_post_meta( $post->ID, '_share_on_indienews_url', $location );
		} else {
			error_log( '[Share on IndieWeb News] Could not detect a "Location" header.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Finds a Webmention enpoint for the given URL.
	 *
	 * @link https://github.com/pfefferle/wordpress-webmention/blob/master/includes/functions.php#L174
	 *
	 * @param  string $url URL to ping.
	 * @return string|null Endpoint URL, or nothing on failure.
	 */
	private function discover_endpoint( $url ) {
		if ( false === wp_http_validate_url( $url ) ) {
			// Not a (valid) URL. This should never happen.
			return;
		}

		$args = array(
			'timeout'             => 15,
			'redirection'         => 20,
			'limit_response_size' => 1048576,
		);

		$response = wp_safe_remote_head(
			esc_url_raw( $url ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		// Check link header.
		$links = wp_remote_retrieve_header( $response, 'link' );

		if ( ! empty( $links ) ) {
			foreach ( (array) $links as $link ) {
				if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(\.org)?\/?[\"\']?/i', $link, $result ) ) {
					return \WP_Http::make_absolute_url( $result[1], $url );
				}
			}
		}

		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			// Not an (X)HTML, SGML, or XML document. No use going further.
			return;
		}

		// Now do a GET since we're going to look in the HTML headers (and we're
		// sure its not a binary file).
		$response = wp_safe_remote_get(
			esc_url_raw( $url ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$contents = wp_remote_retrieve_body( $response );
		$contents = mb_convert_encoding( $contents, 'HTML-ENTITIES', mb_detect_encoding( $contents ) );

		libxml_use_internal_errors( true );

		$doc = new \DOMDocument();
		$doc->loadHTML( $contents );

		$xpath = new \DOMXPath( $doc );

		foreach ( $xpath->query( '(//link|//a)[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			return \WP_Http::make_absolute_url( $result->value, $url );
		}
	}
}
