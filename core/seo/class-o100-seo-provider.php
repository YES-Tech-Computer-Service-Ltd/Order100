<?php
/**
 * Smart SEO Data Provider
 *
 * Dictionary-based keyword extraction engine for Chinese restaurant menu items.
 * No AI required — relies on structural patterns of menu item naming.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_SEO_Provider {

	// ═══════════════════════════════════════════════════════════════
	// DICTIONARIES
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Compound terms that must NOT be split.
	 * Ordered longest-first to ensure greedy matching.
	 */
	private static $compound_terms = array(
		// Flavor compounds
		'sweet and sour',
		'salt and pepper',
		'black bean sauce',
		'black bean',
		'xo sauce',
		'oyster sauce',
		'hoisin sauce',
		'plum sauce',
		'garlic sauce',
		'brown sauce',
		'curry sauce',
		'satay sauce',
		'teriyaki sauce',
		'lemon sauce',
		'orange sauce',
		'honey garlic',
		'honey walnut',
		'kung pao',
		'kung po',
		'general tso',
		'general tao',
		'mapo tofu',
		'dan dan',
		'moo shu',
		'mu shu',
		// Dish type compounds
		'chow mein',
		'lo mein',
		'chow fun',
		'ho fun',
		'rice noodle',
		'rice noodles',
		'glass noodle',
		'glass noodles',
		'fried rice',
		'steamed rice',
		'sticky rice',
		'egg foo young',
		'egg fu yung',
		'wonton soup',
		'won ton soup',
		'hot pot',
		'hot and sour soup',
		'hot and sour',
		'egg drop soup',
		'spring roll',
		'spring rolls',
		'egg roll',
		'egg rolls',
		'rice paper roll',
		'green onion cake',
		'green onion pancake',
		'pot sticker',
		'pot stickers',
		'bubble tea',
		'dim sum',
		'peking duck',
		'bbq pork',
		'bbq duck',
		'char siu',
		'bao bun',
		// Variant spellings
		'low mein',
		'lo mien',
		'sweet sour',
		// Western combos found in Chinese-Canadian menus
		'french fries',
		'caesar salad',
		'onion rings',
		// Cooking method compounds
		'deep fried',
		'stir fried',
		'stir fry',
		'pan fried',
		'crispy fried',
	);

	/**
	 * Dish type nouns — MUST always be kept (highest priority).
	 */
	private static $dish_types = array(
		// Noodle dishes (after compound extraction)
		'chow mein', 'lo mein', 'chow fun', 'ho fun',
		'noodle', 'noodles', 'pasta', 'udon', 'ramen', 'pho', 'vermicelli',
		// Rice dishes
		'fried rice', 'rice', 'congee', 'porridge',
		// Soups
		'wonton soup', 'hot and sour soup', 'egg drop soup',
		'soup', 'broth',
		// Wraps / rolls
		'spring roll', 'egg roll', 'wrap', 'roll', 'rolls', 'burrito',
		// Baked / bun
		'bun', 'buns', 'dumpling', 'dumplings', 'potsticker', 'pot sticker',
		'baozi', 'bao', 'siu mai', 'shumai', 'har gow',
		// Main dishes
		'steak', 'chop', 'chops', 'ribs', 'wing', 'wings',
		'burger', 'sandwich', 'salad', 'platter', 'combo',
		'fries', 'strips', 'fingers', 'nuggets', 'tenders',
		// Desserts
		'cake', 'pie', 'pudding', 'tart', 'mousse', 'ice cream',
		// Drinks
		'bubble tea', 'tea', 'juice', 'smoothie',
		// Dim sum
		'dim sum',
		// Generic
		'curry', 'stew', 'casserole', 'skewer', 'satay',
	);

	/**
	 * Protein / main ingredient — MUST always be kept (highest priority).
	 */
	private static $proteins = array(
		'chicken', 'beef', 'pork', 'shrimp', 'prawn', 'prawns',
		'fish', 'salmon', 'cod', 'tilapia', 'halibut', 'tuna',
		'squid', 'calamari', 'lobster', 'crab', 'scallop', 'scallops',
		'lamb', 'duck', 'goose', 'turkey', 'quail',
		'tofu', 'bean curd',
		'egg', 'eggs',
		'vegetable', 'vegetables', 'veggie', 'mushroom', 'mushrooms',
		'broccoli', 'eggplant', 'bok choy', 'gai lan', 'choy sum',
		'corn', 'potato', 'taro',
		'seafood', 'mixed seafood',
	);

	/**
	 * Cooking methods — keep if space allows (medium priority).
	 * These are valuable for search differentiation.
	 */
	private static $cooking_methods = array(
		'deep fried', 'stir fried', 'stir fry', 'pan fried', 'crispy fried',
		'fried', 'grilled', 'roasted', 'baked', 'steamed',
		'braised', 'sauteed', 'sautéed', 'smoked', 'bbq', 'barbecue',
		'crispy', 'crunchy', 'stuffed', 'boiled', 'poached',
		'dried', 'marinated', 'glazed', 'tempura',
	);

	/**
	 * Flavor/style keywords — keep if differentiating (medium priority).
	 */
	private static $flavor_words = array(
		'sweet and sour', 'salt and pepper', 'kung pao', 'kung po',
		'general tso', 'general tao', 'mapo',
		'spicy', 'garlic', 'ginger', 'lemon', 'orange', 'honey',
		'sesame', 'teriyaki', 'curry', 'satay', 'szechuan', 'sichuan',
		'mongolian', 'hunan', 'black bean', 'xo',
		'hot', 'mild', 'tangy', 'savory', 'smoky',
		'sweet', 'sour', 'bbq',
	);

	/**
	 * Noise words — ALWAYS remove (lowest priority).
	 */
	private static $noise_words = array(
		'special', 'house', 'premium', 'deluxe', 'classic',
		'traditional', 'authentic', 'famous', 'popular',
		'assorted', 'chef',
		'homemade', 'fresh', 'tender', 'juicy',
		'swiss', 'buffalo', 'caesar', 'french', 'italian',
		'plain', 'golden', 'crisp',
		'large', 'small', 'medium', 'jumbo', 'mini', 'regular',
		'double', 'triple', 'extra', 'super', 'ultimate',
		'new', 'best', 'finest', 'original', 'real',
		'style', 'type', 'kind', 'sort',
		'served', 'topped', 'garnished', 'drizzled',
		'with', 'and', 'or', 'on', 'in', 'a', 'an', 'the', 'of', 'for', 'our',
		'includes', 'free', 'included', 'comes',
		'salt', 'pepper', 'sauce', 'oil', 'butter', 'cream', 'sugar', 'soy', 'vinegar',
		'cantonese', 'shanghai', 'beijing', 'peking',
		'taiwanese', 'hong kong', 'hakka', 'fujian',
		'thai', 'japanese', 'korean', 'vietnamese',
		'malaysian', 'singaporean', 'indonesian',
		'indian', 'chinese', 'asian', 'oriental',
		'western', 'american', 'canadian',
	);

	/** Synonym replacements for SEO-optimized keywords */
	private static $synonyms = array(
		'combination' => 'Combo',
		'bbq'         => 'BBQ',
		'szechuan'    => 'Szechuan',
		'sichuan'     => 'Szechuan',
	);

	// ═══════════════════════════════════════════════════════════════
	// CORE TAG PARSING
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Get all parsed tags for a given product ID
	 */
	public static function get_parsed_tags( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$title = $product->get_name();

		// [chinese_name]: Extract Chinese characters
		preg_match_all( '/[\x{4e00}-\x{9fa5}]+/u', $title, $chinese_matches );
		$chinese_name = ! empty( $chinese_matches[0] ) ? implode( ' ', $chinese_matches[0] ) : '';

		// [dish_name]: Extract English, strip Chinese, strip leading codes
		$clean_title = preg_replace( '/[\x{4e00}-\x{9fa5}]+/u', '', $title );
		$clean_title = preg_replace( '/^[A-Za-z0-9]+[-\.]\s*/', '', trim( $clean_title ) );
		$dish_name = trim( preg_replace( '/\s+/', ' ', $clean_title ) );

		// [smart_keyword]: Intelligent keyword extraction
		$smart_keyword = self::extract_smart_keyword( $dish_name );

		// For generic combo/family names, try to enrich from description
		$smart_keyword = self::enrich_generic_keyword( $smart_keyword, $dish_name, $product );

		// [city]
		$city = get_option( 'woocommerce_store_city', '' );

		// [location]
		$location_name = '';
		$terms = get_the_terms( $product_id, 'wafood_location' );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$location_name = $terms[0]->name;
		}
		if ( empty( $location_name ) ) {
			$location_name = $city;
		}

		// [site_name] — use html_entity_decode to avoid &#039; in meta fields
		$site_name = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );

		// [category]
		$category_name = '';
		$cat_terms = get_the_terms( $product_id, 'product_cat' );
		if ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) {
			$category_name = $cat_terms[0]->name;
		}

		// [price]
		$price_plain = $product->get_price() ? '$' . number_format( (float) $product->get_price(), 2 ) : '';

		// [sku]
		$sku = $product->get_sku();

		// [slug]
		$dish_slug = sanitize_title( $dish_name );

		// [focus_keyword]: Read from stored Rank Math focus keyword (set in Step 1)
		$focus_keyword = get_post_meta( $product_id, 'rank_math_focus_keyword', true );

		// [focus_keyword_slug]: Slug-safe version of focus keyword
		$focus_keyword_slug = ! empty( $focus_keyword ) ? sanitize_title( $focus_keyword ) : $dish_slug;

		return array(
			'[dish_name]'            => $dish_name,
			'[smart_keyword]'        => $smart_keyword,
			'[focus_keyword]'        => $focus_keyword,
			'[focus_keyword_slug]'   => $focus_keyword_slug,
			'[chinese_name]'         => $chinese_name,
			'[city]'                 => $city,
			'[location]'             => $location_name,
			'[site_name]'            => $site_name,
			'[category]'             => $category_name,
			'[price]'                => $price_plain,
			'[sku]'                  => $sku,
			'[slug]'                 => $dish_slug,
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// SMART KEYWORD EXTRACTION ENGINE
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Extract a concise, SEO-quality keyword from a dish name.
	 *
	 * Algorithm:
	 * 1. Cut at "or" / "/" (remove alternative names)
	 * 2. Remove parenthetical content
	 * 3. Protect compound terms (replace with placeholders)
	 * 4. Classify every word by priority
	 * 5. Assemble: dish_type + protein (always) + cooking_method + flavor (if space)
	 *
	 * @param string $dish_name  Clean English dish name (no Chinese, no number prefix)
	 * @return string  Optimized keyword, typically 2-4 words
	 */
	public static function extract_smart_keyword( $dish_name ) {
		if ( empty( $dish_name ) ) {
			return '';
		}

		$text = $dish_name;

		// ── Step 1: Cut at "or" / "/" (take first item of combo) ──
		// Note: "&" is NOT a splitter — "Hot & Sour" should stay together
		$text = preg_split( '/\s+or\s+|\s*\/\s*/i', $text )[0];
		// Convert "&" to natural word
		$text = str_replace( '&', 'and', $text );

		// ── Step 2: Clean special formatting ──
		// Remove parenthetical content (including unclosed parens like "Pork (Ginger")
		$text = preg_replace( '/\([^)]*\)?/', '', $text );
		// Strip quotes but KEEP the words inside: "Good Fortune" → Good Fortune
		$text = str_replace( array( '"', '"', '"', "'", "'", "'" ), '', $text );
		// Cut at dash subtitle separator
		$text = preg_split( '/\s+-\s+/', $text )[0];
		// Remove standalone numbers and quantities like "2", "3 pcs"
		$text = preg_replace( '/\b\d+\s*(pcs?|pieces?|oz|g|ml)?\b/i', '', $text );
		// Convert hyphens to spaces BEFORE removing special chars: Wok-Fried → Wok Fried
		$text = str_replace( '-', ' ', $text );
		// Remove any remaining special characters that hurt SEO keyword matching
		$text = preg_replace( '/[^\w\s]/u', '', $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );

		if ( empty( $text ) ) {
			return trim( $dish_name );
		}

		// ── Step 2.5: Apply synonym replacements ──
		$words = explode( ' ', $text );
		foreach ( $words as &$w ) {
			$wl = strtolower( $w );
			if ( isset( self::$synonyms[ $wl ] ) ) {
				$w = self::$synonyms[ $wl ];
			}
		}
		unset( $w );
		$text = implode( ' ', $words );

		$text_lower = strtolower( $text );

		// ── Step 3: Protect compound terms ──
		// Match compound terms including their plural forms (e.g. "spring rolls" matches "spring roll")
		$protected = array();       // placeholder => original compound
		$placeholder_idx = 0;
		foreach ( self::$compound_terms as $compound ) {
			// Try exact match first, then with optional trailing 's'
			$pattern = '/\b' . preg_quote( $compound, '/' ) . 's?\b/i';
			if ( preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
				$matched_text = $match[0][0];
				$pos = $match[0][1];
				$placeholder = '##COMPOUND' . $placeholder_idx . '##';
				// Store the compound term (without trailing s for consistency)
				$protected[ $placeholder ] = ucwords( $compound );
				$text = substr_replace( $text, ' ' . $placeholder . ' ', $pos, strlen( $matched_text ) );
				$text_lower = strtolower( $text );
				$placeholder_idx++;
			}
		}

		// ── Step 4: Classify every token ──
		$tokens = preg_split( '/\s+/', trim( $text ) );

		$found_dish_types   = array();
		$found_proteins     = array();
		$found_cooking      = array();
		$found_flavors      = array();
		$found_other        = array();
		$dropped_words      = array();

		foreach ( $tokens as $token ) {
			// Is it a protected compound placeholder?
			if ( isset( $protected[ $token ] ) ) {
				$compound_value = strtolower( $protected[ $token ] );
				if ( self::in_list( $compound_value, self::$dish_types ) ) {
					$found_dish_types[] = $protected[ $token ];
				} elseif ( self::in_list( $compound_value, self::$flavor_words ) ) {
					$found_flavors[] = $protected[ $token ];
				} elseif ( self::in_list( $compound_value, self::$cooking_methods ) ) {
					$found_cooking[] = $protected[ $token ];
				} elseif ( self::in_list( $compound_value, self::$proteins ) ) {
					$found_proteins[] = $protected[ $token ];
				} else {
					$found_flavors[] = $protected[ $token ];
				}
				continue;
			}

			$lower = strtolower( $token );

			// Skip single characters and numbers
			if ( strlen( $token ) <= 1 || is_numeric( $token ) ) {
				continue;
			}

			// Classify (noise first so it gets dropped)
			if ( self::in_list( $lower, self::$noise_words ) ) {
				$dropped_words[] = $token;
			} elseif ( self::in_list( $lower, self::$dish_types ) ) {
				$found_dish_types[] = $token;
			} elseif ( self::in_list( $lower, self::$proteins ) ) {
				$found_proteins[] = $token;
			} elseif ( self::in_list( $lower, self::$cooking_methods ) ) {
				$found_cooking[] = $token;
			} elseif ( self::in_list( $lower, self::$flavor_words ) ) {
				$found_flavors[] = $token;
			} else {
				$found_other[] = $token;
			}
		}

		// ── Step 5: Keep original word order, remove only noise ──
		// Instead of re-assembling by category, we preserve the original token order
		// and simply skip noise words. This keeps natural English phrasing intact.
		$kept_parts = array();

		foreach ( $tokens as $token ) {
			// Resolve compound placeholders
			if ( isset( $protected[ $token ] ) ) {
				$kept_parts[] = $protected[ $token ];
				continue;
			}

			$lower = strtolower( $token );

			// Skip single characters and numbers
			if ( strlen( $token ) <= 1 || is_numeric( $token ) ) {
				continue;
			}

			// Skip noise words
			if ( self::in_list( $lower, self::$noise_words ) ) {
				$dropped_words[] = $token;
				continue;
			}

			// Keep everything else (proteins, dish types, cooking, flavor, other)
			$kept_parts[] = $token;
		}

		// ── Step 6: Enforce max word count ──
		$final_words = array();
		$word_count = 0;
		$max_words = 4;

		foreach ( $kept_parts as $part ) {
			$part_word_count = str_word_count( $part );
			if ( $word_count + $part_word_count <= $max_words ) {
				$final_words[] = $part;
				$word_count += $part_word_count;
			} else if ( $word_count == 0 ) {
				$final_words[] = $part;
				$word_count += $part_word_count;
				break;
			} else {
				break;
			}
		}

		$keyword = implode( ' ', $final_words );

		// Safety: resolve any remaining placeholders
		foreach ( $protected as $placeholder => $value ) {
			$keyword = str_replace( $placeholder, $value, $keyword );
		}

		// If nothing was extracted, fallback to first 3 words of original
		if ( empty( trim( $keyword ) ) ) {
			$original_words = preg_split( '/\s+/', trim( $dish_name ) );
			$keyword = implode( ' ', array_slice( $original_words, 0, 3 ) );
		}

		return trim( $keyword );
	}

	/**
	 * Get the words that were dropped during smart keyword extraction.
	 * Used for deduplication: we can add these back to make a keyword unique.
	 *
	 * Returns words in recovery priority order:
	 * 1. Cooking methods (Crispy, Steamed — most differentiating)
	 * 2. Flavor words (Spicy, Garlic)
	 * 3. Noise/cuisine words (Cantonese, Special — last resort)
	 *
	 * @param string $dish_name       Original dish name
	 * @param string $smart_keyword   Generated smart keyword
	 * @return array  Recoverable words in priority order
	 */
	public static function get_recoverable_words( $dish_name, $smart_keyword ) {
		// Clean dish_name the same way extract_smart_keyword does:
		// strip parenthetical content and special characters to prevent
		// "(free", "soup)" etc from leaking into recovery words
		$clean_name = preg_replace( '/\([^)]*\)?/', '', $dish_name );
		$clean_name = preg_replace( '/[^\w\s]/u', '', $clean_name );
		$clean_name = trim( preg_replace( '/\s+/', ' ', $clean_name ) );

		// Get all words in original but not in keyword
		$original_words = preg_split( '/\s+/', strtolower( trim( $clean_name ) ) );
		$keyword_words  = preg_split( '/\s+/', strtolower( trim( $smart_keyword ) ) );

		$dropped = array_diff( $original_words, $keyword_words );

		// Filter out words that would make bad dedup additions
		// Include synonym source words (e.g., "combination" already mapped to "Combo")
		$bad_dedup_words = array(
			'for', 'with', 'and', 'or', 'the', 'a', 'an', 'of', 'on', 'in', 'our',
			'free', 'includes', 'included', 'comes',
			'x', 'xsmall', 'small', 'medium', 'large',
			'dinner', 'one', 'two', 'three', 'four', 'five', 'six',
		);

		// Also block all synonym source words (they're already represented in the keyword)
		foreach ( self::$synonyms as $from => $to ) {
			$bad_dedup_words[] = strtolower( $from );
		}

		// Sort by recovery priority
		$cooking_recovery = array();
		$flavor_recovery  = array();
		$other_recovery   = array();

		foreach ( $dropped as $word ) {
			if ( strlen( $word ) <= 2 ) continue;
			// Never use noise/preposition words for dedup
			if ( in_array( $word, $bad_dedup_words, true ) ) continue;

			if ( self::in_list( $word, self::$cooking_methods ) ) {
				$cooking_recovery[] = ucfirst( $word );
			} elseif ( self::in_list( $word, self::$flavor_words ) ) {
				$flavor_recovery[] = ucfirst( $word );
			} elseif ( self::in_list( $word, self::$proteins ) ) {
				$cooking_recovery[] = ucfirst( $word ); // proteins are high-value differentiators
			} else {
				$other_recovery[] = ucfirst( $word );
			}
		}

		return array_merge( $cooking_recovery, $flavor_recovery, $other_recovery );
	}

	/**
	 * Check if a word exists in a dictionary list.
	 * Handles both single words and multi-word entries.
	 */
	private static function in_list( $word, $list ) {
		return in_array( $word, $list, true );
	}

	/**
	 * Apply tags to a template string
	 */
	public static function apply_tags( $template, $product_id ) {
		if ( empty( $template ) ) {
			return '';
		}

		$tags = self::get_parsed_tags( $product_id );

		$result = str_replace( array_keys( $tags ), array_values( $tags ), $template );

		// Clean up multiple spaces that might result from empty tag replacements
		$result = trim( preg_replace( '/\s+/', ' ', $result ) );

		return $result;
	}

	/**
	 * Enrich generic combo/family dinner keywords using product description.
	 *
	 * When the extracted keyword is too generic (e.g., "Combo One", "Dinner Two"),
	 * scan the product description for distinguishing dish phrases and inject them.
	 *
	 * Strategy:
	 * 1. First look for meaningful compound dish phrases (e.g., "ginger beef", "fried rice")
	 * 2. Then look for protein+dish combinations in comma-separated segments
	 * 3. Fall back to isolated protein words only as last resort
	 *
	 * Example: "Combo One" + desc "spring roll, ginger beef, chicken fried rice"
	 *        → "Combo Ginger Beef Regina" (not "Combo Chicken Beef Regina")
	 *
	 * @param string      $keyword    Generated smart keyword
	 * @param string      $dish_name  Original English dish name
	 * @param WC_Product  $product    WooCommerce product object
	 * @return string  Enriched keyword
	 */
	private static function enrich_generic_keyword( $keyword, $dish_name, $product ) {
		// Only enrich generic combo/family/dinner keywords
		$lower = strtolower( $keyword );
		$is_generic = preg_match( '/^(combo|dinner|family dinner|special)\b/i', $lower )
			&& str_word_count( $keyword ) <= 3;

		if ( ! $is_generic ) {
			return $keyword;
		}

		// Get description text
		$desc = $product->get_short_description();
		if ( empty( $desc ) ) {
			$desc = $product->get_description();
		}
		if ( empty( $desc ) ) {
			return $keyword;
		}

		// Clean HTML and normalize
		$desc = wp_strip_all_tags( $desc );
		$desc_lower = strtolower( $desc );

		// ── Strategy 1: Find meaningful compound dish phrases ──
		// These are the most descriptive ("ginger beef" > "beef", "fried rice" > "rice")
		// Common restaurant dish compounds NOT already in self::$compound_terms
		$dish_phrases = array(
			// Protein + cooking style compounds common in Chinese-Canadian menus
			'ginger beef', 'ginger chicken', 'ginger pork',
			'lemon chicken', 'orange chicken', 'sesame chicken',
			'honey garlic chicken', 'honey garlic ribs',
			'chicken ball', 'chicken balls',
			'pork chop', 'pork chops',
			'beef tenderloin', 'beef brisket',
			'shrimp tempura', 'chicken tempura',
			'crispy chicken', 'crispy shrimp',
			'dry ribs', 'dry garlic ribs',
		);

		// Merge with existing compound_terms for a complete scan
		$all_phrases = array_merge( $dish_phrases, self::$compound_terms );

		// Skip generic/filler compounds that don't differentiate
		$skip_phrases = array( 'spring roll', 'spring rolls', 'egg roll', 'egg rolls',
			'wonton soup', 'won ton soup', 'steamed rice', 'sticky rice' );

		$found_phrases = array();
		foreach ( $all_phrases as $phrase ) {
			if ( in_array( $phrase, $skip_phrases, true ) ) {
				continue;
			}
			if ( strpos( $desc_lower, $phrase ) !== false ) {
				$found_phrases[] = ucwords( $phrase );
			}
		}

		// Use the first meaningful phrase found
		if ( ! empty( $found_phrases ) ) {
			$prefix_words = explode( ' ', $keyword );
			$prefix = $prefix_words[0]; // "Combo" or "Dinner" or "Special"
			$enriched = $prefix . ' ' . $found_phrases[0];

			// Enforce max 4 words
			$enriched_words = explode( ' ', $enriched );
			if ( count( $enriched_words ) > 4 ) {
				$enriched = implode( ' ', array_slice( $enriched_words, 0, 4 ) );
			}
			return $enriched;
		}

		// ── Strategy 2: Parse comma-separated segments for protein + context ──
		$segments = preg_split( '/[,;·•\n\r]+/', $desc_lower );
		foreach ( $segments as $segment ) {
			$segment = trim( $segment );
			if ( strlen( $segment ) < 3 ) continue;

			// Check if this segment contains a protein
			foreach ( self::$proteins as $protein ) {
				if ( strpos( $segment, $protein ) !== false ) {
					// Extract 2-3 meaningful words from this segment
					$seg_words = preg_split( '/\s+/', $segment );
					// Remove noise words from segment
					$seg_clean = array();
					foreach ( $seg_words as $sw ) {
						if ( strlen( $sw ) > 2 && ! self::in_list( $sw, self::$noise_words ) ) {
							$seg_clean[] = ucfirst( $sw );
						}
						if ( count( $seg_clean ) >= 3 ) break;
					}
					if ( ! empty( $seg_clean ) ) {
						$prefix_words = explode( ' ', $keyword );
						$prefix = $prefix_words[0];
						$enriched = $prefix . ' ' . implode( ' ', $seg_clean );
						$enriched_words = explode( ' ', $enriched );
						if ( count( $enriched_words ) > 4 ) {
							$enriched = implode( ' ', array_slice( $enriched_words, 0, 4 ) );
						}
						return $enriched;
					}
				}
			}
		}

		// ── Strategy 3: Last resort — isolated protein words ──
		$found_proteins = array();
		foreach ( self::$proteins as $protein ) {
			if ( strpos( $desc_lower, $protein ) !== false ) {
				$found_proteins[] = ucfirst( $protein );
				if ( count( $found_proteins ) >= 2 ) break;
			}
		}

		if ( ! empty( $found_proteins ) ) {
			$prefix_words = explode( ' ', $keyword );
			$prefix = $prefix_words[0];
			$enriched = $prefix . ' ' . implode( ' ', $found_proteins );
			$enriched_words = explode( ' ', $enriched );
			if ( count( $enriched_words ) > 4 ) {
				$enriched = implode( ' ', array_slice( $enriched_words, 0, 4 ) );
			}
			return $enriched;
		}

		return $keyword;
	}
}


// TS: 20260111112425

// TS: 20260313170737
