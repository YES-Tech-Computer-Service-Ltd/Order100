<?php
/**
 * Google Reviews & Schema Enhancement
 *
 * Handles:
 * 1. AggregateRating injection (Google Reviews)
 * 2. FoodEstablishment Schema (Restaurant info)
 * 3. Product Brand injection
 * 4. FAQ Schema injection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Google_Reviews {

	public function __construct() {
		// Inject AggregateRating + Brand into Rank Math Product Schema
		add_filter( 'rank_math/snippet/rich_snippet_product_entity', array( $this, 'inject_product_schema' ) );
		add_filter( 'rank_math/json_ld', array( $this, 'inject_jsonld' ), 99, 2 );

		// Restaurant Schema on shop/home
		add_action( 'wp_head', array( $this, 'output_restaurant_schema' ) );

		// FAQ Schema on product pages
		add_action( 'wp_head', array( $this, 'output_faq_schema' ) );

		// Geo meta tags
		add_action( 'wp_head', array( $this, 'output_geo_meta' ) );

		// NAP footer
		add_action( 'wp_footer', array( $this, 'output_nap_footer' ) );
	}

	/**
	 * Save Google Reviews settings from POST.
	 */
	public static function save_settings() {
		$options = get_option( 'o100_google_reviews', array() );

		if ( isset( $_POST['o100_google_rating'] ) ) {
			$options['rating'] = floatval( $_POST['o100_google_rating'] );
		}
		if ( isset( $_POST['o100_google_review_count'] ) ) {
			$options['review_count'] = intval( $_POST['o100_google_review_count'] );
		}
		if ( isset( $_POST['o100_google_review_url'] ) ) {
			$options['review_url'] = esc_url_raw( $_POST['o100_google_review_url'] );
		}

		update_option( 'o100_google_reviews', $options );
	}

	// ═══════════════════════════════════════════════════════════════
	// PRODUCT SCHEMA: AggregateRating + Brand
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Inject into Rank Math Product entity.
	 */
	public function inject_product_schema( $entity ) {
		// AggregateRating
		$google = get_option( 'o100_google_reviews', array() );
		if ( ! empty( $google['rating'] ) && ! empty( $google['review_count'] ) ) {
			$entity['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $google['rating'],
				'reviewCount' => (string) $google['review_count'],
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}

		// Brand
		$schema = get_option( 'o100_schema_settings', array() );
		if ( ! empty( $schema['brand'] ) && ! isset( $entity['brand'] ) ) {
			$entity['brand'] = array(
				'@type' => 'Brand',
				'name'  => $schema['brand'],
			);
		}

		return $entity;
	}

	/**
	 * Fallback JSON-LD injection.
	 */
	public function inject_jsonld( $data, $jsonld ) {
		if ( ! is_singular( 'product' ) ) {
			return $data;
		}

		$google = get_option( 'o100_google_reviews', array() );
		$schema = get_option( 'o100_schema_settings', array() );

		foreach ( $data as $key => &$item ) {
			if ( ! isset( $item['@type'] ) || $item['@type'] !== 'Product' ) {
				continue;
			}

			// AggregateRating
			if ( ! empty( $google['rating'] ) && ! empty( $google['review_count'] ) && ! isset( $item['aggregateRating'] ) ) {
				$item['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => (string) $google['rating'],
					'reviewCount' => (string) $google['review_count'],
					'bestRating'  => '5',
					'worstRating' => '1',
				);
			}

			// Brand
			if ( ! empty( $schema['brand'] ) && ! isset( $item['brand'] ) ) {
				$item['brand'] = array(
					'@type' => 'Brand',
					'name'  => $schema['brand'],
				);
			}
		}

		return $data;
	}

	// ═══════════════════════════════════════════════════════════════
	// RESTAURANT / FOOD ESTABLISHMENT SCHEMA
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Output Restaurant Schema on shop page and homepage.
	 */
	public function output_restaurant_schema() {
		if ( ! is_shop() && ! is_front_page() ) {
			return;
		}

		$schema = get_option( 'o100_schema_settings', array() );
		$name = ! empty( $schema['restaurant_name'] ) ? $schema['restaurant_name'] : get_bloginfo( 'name' );

		if ( empty( $name ) ) {
			return;
		}

		$restaurant = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Restaurant',
			'name'     => $name,
			'url'      => home_url( '/' ),
		);

		if ( ! empty( $schema['cuisine'] ) ) {
			$restaurant['servesCuisine'] = array_map( 'trim', explode( ',', $schema['cuisine'] ) );
		}
		if ( ! empty( $schema['phone'] ) ) {
			$restaurant['telephone'] = $schema['phone'];
		}
		if ( ! empty( $schema['price_range'] ) ) {
			$restaurant['priceRange'] = $schema['price_range'];
		}
		// Structured PostalAddress — Google requires all sub-fields
		$street = ! empty( $schema['address'] ) ? $schema['address'] : get_option( 'woocommerce_store_address', '' );
		if ( ! empty( $street ) ) {
			$postal_address = array(
				'@type'         => 'PostalAddress',
				'streetAddress' => $street,
			);
			$city = ! empty( $schema['geo_city'] ) ? $schema['geo_city'] : get_option( 'woocommerce_store_city', '' );
			if ( $city ) {
				$postal_address['addressLocality'] = $city;
			}
			$country_state = get_option( 'woocommerce_default_country', '' );
			if ( $country_state && strpos( $country_state, ':' ) !== false ) {
				list( $country_code, $state_code ) = explode( ':', $country_state, 2 );
				if ( $state_code ) {
					$postal_address['addressRegion'] = $state_code;
				}
				$postal_address['addressCountry'] = $country_code;
			} elseif ( $country_state ) {
				$postal_address['addressCountry'] = $country_state;
			}
			$postcode = get_option( 'woocommerce_store_postcode', '' );
			if ( $postcode ) {
				$postal_address['postalCode'] = $postcode;
			}
			$restaurant['address'] = $postal_address;
		}

		// Add aggregate rating from Google Reviews
		$google = get_option( 'o100_google_reviews', array() );
		if ( ! empty( $google['rating'] ) && ! empty( $google['review_count'] ) ) {
			$restaurant['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $google['rating'],
				'reviewCount' => (string) $google['review_count'],
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}

		// Opening hours — read from ExFood settings, allow Schema override
		$opening_hours = self::get_opening_hours( $schema );
		if ( ! empty( $opening_hours ) ) {
			$restaurant['openingHoursSpecification'] = $opening_hours;
		}

		// Geo coordinates
		if ( ! empty( $schema['geo_lat'] ) && ! empty( $schema['geo_lng'] ) ) {
			$restaurant['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $schema['geo_lat'],
				'longitude' => (float) $schema['geo_lng'],
			);
		}

		// Menu link
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$restaurant['hasMenu'] = get_permalink( $shop_id );
			}
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $restaurant, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	// ═══════════════════════════════════════════════════════════════
	// FAQ SCHEMA
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Output FAQ Schema on product pages.
	 */
	public function output_faq_schema() {
		// FAQ schema on product pages, shop page, and homepage
		if ( ! is_singular( 'product' ) && ! is_shop() && ! is_front_page() ) {
			return;
		}

		$schema = get_option( 'o100_schema_settings', array() );
		$faqs   = $schema['faqs'] ?? array();

		if ( empty( $faqs ) ) {
			return;
		}

		$faq_entities = array();
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['q'] ) || empty( $faq['a'] ) ) {
				continue;
			}
			// Replace tags in FAQ text
			$answer = $this->replace_tags( $faq['a'] );
			$question = $this->replace_tags( $faq['q'] );

			$faq_entities[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $faq_entities ) ) {
			return;
		}

		$faq_schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $faq_entities,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Replace simple tags in FAQ text.
	 */
	private function replace_tags( $text ) {
		$city = get_option( 'woocommerce_store_city', '' );
		$schema = get_option( 'o100_schema_settings', array() );
		$restaurant_name = ! empty( $schema['restaurant_name'] ) ? $schema['restaurant_name'] : get_bloginfo( 'name' );

		$replacements = array(
			'[site_name]' => get_bloginfo( 'name' ),
			'[city]'      => ! empty( $schema['geo_city'] ) ? $schema['geo_city'] : $city,
			'[location]'  => ! empty( $schema['geo_city'] ) ? $schema['geo_city'] : $city,
			'[restaurant]'=> $restaurant_name,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
	}

	// ═══════════════════════════════════════════════════════════════
	// GEO META TAGS
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Output geo meta tags in <head>.
	 */
	public function output_geo_meta() {
		$schema = get_option( 'o100_schema_settings', array() );

		if ( ! empty( $schema['geo_region'] ) ) {
			echo '<meta name="geo.region" content="' . esc_attr( $schema['geo_region'] ) . '">' . "\n";
		}
		if ( ! empty( $schema['geo_city'] ) ) {
			echo '<meta name="geo.placename" content="' . esc_attr( $schema['geo_city'] ) . '">' . "\n";
		}
		if ( ! empty( $schema['geo_lat'] ) && ! empty( $schema['geo_lng'] ) ) {
			echo '<meta name="geo.position" content="' . esc_attr( $schema['geo_lat'] ) . ';' . esc_attr( $schema['geo_lng'] ) . '">' . "\n";
			echo '<meta name="ICBM" content="' . esc_attr( $schema['geo_lat'] ) . ', ' . esc_attr( $schema['geo_lng'] ) . '">' . "\n";
		}
	}

	// ═══════════════════════════════════════════════════════════════
	// NAP FOOTER
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Output NAP (Name, Address, Phone) in footer.
	 */
	public function output_nap_footer() {
		if ( is_admin() ) {
			return;
		}

		$schema = get_option( 'o100_schema_settings', array() );

		if ( empty( $schema['nap_footer'] ) ) {
			return;
		}

		$name    = ! empty( $schema['restaurant_name'] ) ? $schema['restaurant_name'] : get_bloginfo( 'name' );
		$address = ! empty( $schema['address'] ) ? $schema['address'] : self::get_wc_store_address();
		$phone   = ! empty( $schema['phone'] ) ? $schema['phone'] : '';

		if ( empty( $name ) ) {
			return;
		}

		echo '<div class="o100-nap-footer" style="text-align:center; padding:12px 20px; font-size:13px; color:#6b7280; border-top:1px solid #e5e7eb; background:#f9fafb;" itemscope itemtype="https://schema.org/LocalBusiness">';
		echo '<span itemprop="name" style="font-weight:600;">' . esc_html( $name ) . '</span>';
		if ( ! empty( $address ) ) {
			echo ' · <span itemprop="address">' . esc_html( $address ) . '</span>';
		}
		if ( ! empty( $phone ) ) {
			echo ' · <a href="tel:' . esc_attr( preg_replace( '/[^+0-9]/', '', $phone ) ) . '" itemprop="telephone" style="color:inherit; text-decoration:none;">' . esc_html( $phone ) . '</a>';
		}
		echo '</div>' . "\n";
	}

	/**
	 * Build address string from WooCommerce store settings
	 */
	private static function get_wc_store_address() {
		$parts = array_filter( array(
			get_option( 'woocommerce_store_address', '' ),
			get_option( 'woocommerce_store_address_2', '' ),
			get_option( 'woocommerce_store_city', '' ),
		) );

		$country_state = get_option( 'woocommerce_default_country', '' );
		if ( $country_state && strpos( $country_state, ':' ) !== false ) {
			list( $country, $state ) = explode( ':', $country_state, 2 );
			if ( $state ) {
				$parts[] = $state;
			}
		}

		$postcode = get_option( 'woocommerce_store_postcode', '' );
		if ( $postcode ) {
			$parts[] = $postcode;
		}

		return implode( ', ', $parts );
	}

	/**
	 * Get opening hours as OpeningHoursSpecification array.
	 *
	 * Priority: Schema manual override > ExFood store hours
	 * ExFood stores hours in exwoofood_advanced_options:
	 *   exwfood_{Day}_opcl_time => array( array( 'open-time' => '09:00', 'close-time' => '20:00' ) )
	 *
	 * @param array $schema  Our o100_schema_settings option
	 * @return array  OpeningHoursSpecification entries (empty if no data)
	 */
	private static function get_opening_hours( $schema ) {
		$day_map = array(
			'monday'    => array( 'schema' => 'Monday',    'day_abbr' => 'Mon' ),
			'tuesday'   => array( 'schema' => 'Tuesday',   'day_abbr' => 'Tue' ),
			'wednesday' => array( 'schema' => 'Wednesday', 'day_abbr' => 'Wed' ),
			'thursday'  => array( 'schema' => 'Thursday',  'day_abbr' => 'Thu' ),
			'friday'    => array( 'schema' => 'Friday',    'day_abbr' => 'Fri' ),
			'saturday'  => array( 'schema' => 'Saturday',  'day_abbr' => 'Sat' ),
			'sunday'    => array( 'schema' => 'Sunday',    'day_abbr' => 'Sun' ),
		);

		$hours   = $schema['hours'] ?? array();
		$opening = array();

		foreach ( $day_map as $key => $info ) {
			if ( ! empty( $hours[ $key . '_closed' ] ) ) {
				continue;
			}

			$open  = '';
			$close = '';

			// Priority 1: Schema manual override
			if ( ! empty( $hours[ $key . '_open' ] ) ) {
				$open  = $hours[ $key . '_open' ];
				$close = $hours[ $key . '_close' ] ?? '';
			}

			// Priority 2: O100 store hours
			if ( empty( $open ) && class_exists( 'O100_Store_Data' ) ) {
				$day_hours = O100_Store_Data::get_day_hours( $info['day_abbr'] );
				if ( is_array( $day_hours ) && ! empty( $day_hours ) ) {
					$earliest_open  = '23:59';
					$latest_close   = '00:00';
					foreach ( $day_hours as $slot ) {
						$slot_open  = $slot['open-time'] ?? '';
						$slot_close = $slot['close-time'] ?? '';
						if ( $slot_open && $slot_open < $earliest_open ) {
							$earliest_open = $slot_open;
						}
						if ( $slot_close && $slot_close > $latest_close ) {
							$latest_close = $slot_close;
						}
					}
					if ( $earliest_open !== '23:59' ) {
						$open  = $earliest_open;
						$close = $latest_close;
					}
				}
			}

			if ( ! empty( $open ) && ! empty( $close ) ) {
				$opening[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $info['schema'],
					'opens'     => $open,
					'closes'    => $close,
				);
			}
		}

		return $opening;
	}
}

// TS: 20260124225607
