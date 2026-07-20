<?php
/**
 * Social Sharing Component
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Social_Share {

	/**
	 * Render the social share buttons
	 *
	 * @param string $url The URL to share.
	 * @param string $title The title to share.
	 */
	public static function render_share_buttons( $url, $title ) {
		$opts            = get_option( 'o100_options', array() );
		$enabled_socials = isset( $opts['o100_enabled_socials'] ) ? $opts['o100_enabled_socials'] : array( 'facebook', 'twitter', 'whatsapp', 'email', 'linkedin' );

		if ( empty( $enabled_socials ) || ! is_array( $enabled_socials ) ) {
			return; // No channels enabled
		}

		// Enforce Max 5 Limit
		$enabled_socials = array_slice( $enabled_socials, 0, 5 );

		$encoded_url   = rawurlencode( $url );
		$encoded_title = rawurlencode( $title );

		// Base SVGs
		$svgs = array(
			'facebook'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3.81l.39-4h-4.2V7a1 1 0 0 1 1-1h3z"></path></svg>',
			'twitter'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>',
			'whatsapp'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>',
			'linkedin'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>',
			'email'     => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
			'instagram' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>',
			'tiktok'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5v3a3 3 0 0 1-3-3v10a7 7 0 1 1-7-7v3a4 4 0 0 0 4 4z"></path></svg>',
			'pinterest' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 0-2.67 19.63c-.1-.7-.18-1.78.04-2.54.2-.7 1.25-5.27 1.25-5.27s-.32-.63-.32-1.57c0-1.47.85-2.57 1.9-2.57.9 0 1.33.68 1.33 1.5 0 .9-.58 2.25-.88 3.5-.25 1.05.53 1.9 1.56 1.9 1.88 0 3.32-1.98 3.32-4.84 0-2.52-1.8-4.28-4.4-4.28-3 0-4.78 2.26-4.78 4.6 0 .9.35 1.87.78 2.4.1.1.1.25.07.35-.1.43-.32 1.3-.36 1.47-.05.2-.18.25-.38.16-1.43-.68-2.32-2.8-2.32-4.5 0-3.66 2.66-7.02 7.68-7.02 4.04 0 7.18 2.87 7.18 6.7 0 4.02-2.52 7.24-6.04 7.24-1.18 0-2.3-.6-2.68-1.34l-.73 2.76c-.26 1-1 2.25-1.5 3.03A10 10 0 0 0 12 22a10 10 0 0 0 0-20z"></path></svg>',
			'telegram'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>',
			'line'      => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 10.5C21.5 6.36 17.25 3 12 3S2.5 6.36 2.5 10.5c0 3.7 3.38 6.8 7.94 7.4.3.05.7.15.8.4.1.25.05.6.02.8l-.24 1.45c-.06.33-.28 1.4 1.25.75 1.52-.64 8.2-4.83 8.2-8.8z"></path></svg>',
			'viber'     => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>',
		);

		// Routing map
		$routes = array(
			'facebook'  => "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}",
			'twitter'   => "https://twitter.com/intent/tweet?url={$encoded_url}&text={$encoded_title}",
			'whatsapp'  => "https://api.whatsapp.com/send?text={$encoded_title}%20{$encoded_url}",
			'email'     => "mailto:?subject={$encoded_title}&body={$encoded_url}",
			'linkedin'  => "https://www.linkedin.com/sharing/share-offsite/?url={$encoded_url}",
			'pinterest' => "https://pinterest.com/pin/create/button/?url={$encoded_url}&description={$encoded_title}",
			'telegram'  => "https://t.me/share/url?url={$encoded_url}&text={$encoded_title}",
			'line'      => "https://line.me/R/msg/text/?{$encoded_title}%20{$encoded_url}",
			'viber'     => "viber://forward?text={$encoded_title}%20{$encoded_url}",
		);

		echo '<div class="o100-social-share-wrap" style="display: flex; gap: 10px; margin-top: 15px;">';
		
		foreach ( $enabled_socials as $channel ) {
			if ( ! isset( $svgs[ $channel ] ) ) continue;

			if ( in_array( $channel, array( 'instagram', 'tiktok' ) ) ) {
				// Copy Link Strategy for Instagram/TikTok
				$color = ( $channel === 'instagram' ) ? '#e1306c' : '#000000';
				echo '<button type="button" class="o100-copy-link-btn" data-url="' . esc_attr( $url ) . '" title="' . esc_attr( ucfirst( $channel ) ) . '" style="background:' . esc_attr( $color ) . '; color: #fff; border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: opacity 0.2s; position: relative;">';
				echo $svgs[ $channel ];
				echo '</button>';
			} else {
				// Standard URL Intent Strategy
				$colors = array(
					'facebook'  => '#1877F2',
					'twitter'   => '#000000',
					'whatsapp'  => '#25D366',
					'linkedin'  => '#0A66C2',
					'email'     => '#64748b',
					'pinterest' => '#E60023',
					'telegram'  => '#26A5E4',
					'line'      => '#00B900',
					'viber'     => '#7360F2',
				);
				$color = isset( $colors[ $channel ] ) ? $colors[ $channel ] : '#64748b';
				$link  = $routes[ $channel ];
				
				echo '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( ucfirst( $channel ) ) . '" style="background:' . esc_attr( $color ) . '; color: #fff; border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: opacity 0.2s; text-decoration: none;">';
				echo $svgs[ $channel ];
				echo '</a>';
			}
		}

		echo '</div>';
		
		// Inline JS for Copy Link fallback (Instagram/TikTok)
		// Only output once
		if ( ! defined( 'O100_SOCIAL_JS_LOADED' ) ) {
			define( 'O100_SOCIAL_JS_LOADED', true );
			?>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					document.body.addEventListener('click', function(e) {
						var btn = e.target.closest('.o100-copy-link-btn');
						if (btn) {
							e.preventDefault();
							var url = btn.getAttribute('data-url');
							navigator.clipboard.writeText(url).then(function() {
								var origHtml = btn.innerHTML;
								btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
								setTimeout(function() {
									btn.innerHTML = origHtml;
								}, 2000);
							});
						}
					});
				});
			</script>
			<?php
		}
	}
}
