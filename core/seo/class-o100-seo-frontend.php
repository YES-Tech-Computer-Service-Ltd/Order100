<?php
/**
 * Smart SEO Frontend Dynamic Hooks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_SEO_Frontend {

	public function __construct() {
		// Rank Math Filters
		add_filter( 'rank_math/frontend/title', array( $this, 'dynamic_title' ), 99 );
		add_filter( 'rank_math/frontend/description', array( $this, 'dynamic_description' ), 99 );

		// Content Snippet Filter
		add_filter( 'the_content', array( $this, 'inject_content_snippet' ), 99 );
	}

	/**
	 * Dynamic Title
	 */
	public function dynamic_title( $title ) {
		if ( ! is_product() ) {
			return $title;
		}

		$options = get_option( 'o100_options', array() );
		$enabled = $options['o100_seo_title_desc_enabled'] ?? '';
		if ( $enabled === 'off' ) {
			return $title;
		}

		global $post;
		if ( ! $post ) {
			return $title;
		}
		
		// If user manually set a rank_math_title, respect it!
		$custom_title = get_post_meta( $post->ID, 'rank_math_title', true );
		if ( ! empty( $custom_title ) ) {
			return $title; // Rank Math already handled it
		}

		// Use dedicated title rule
		$rule = $options['o100_seo_title_rule'] ?? '';
		if ( empty( $rule ) ) {
			$rule = '[focus_keyword] in [location] | [site_name]';
		}

		$generated = O100_SEO_Provider::apply_tags( $rule, $post->ID );
		
		if ( ! empty( $generated ) ) {
			return $generated;
		}

		return $title;
	}

	/**
	 * Dynamic Description
	 */
	public function dynamic_description( $description ) {
		if ( ! is_product() ) {
			return $description;
		}

		$options = get_option( 'o100_options', array() );
		$enabled = $options['o100_seo_title_desc_enabled'] ?? '';
		if ( $enabled === 'off' ) {
			return $description;
		}

		global $post;
		if ( ! $post ) {
			return $description;
		}
		
		// If user manually set a rank_math_description, respect it
		$custom_desc = get_post_meta( $post->ID, 'rank_math_description', true );
		if ( ! empty( $custom_desc ) ) {
			return $description;
		}

		// Use dedicated description rule
		$rule = $options['o100_seo_desc_rule'] ?? '';
		if ( empty( $rule ) ) {
			$rule = 'Order [focus_keyword] [chinese_name] in [location]. Best Chinese food delivery & takeout in [city].';
		}

		$generated = O100_SEO_Provider::apply_tags( $rule, $post->ID );
		
		if ( ! empty( $generated ) ) {
			return $generated;
		}

		return $description;
	}

	/**
	 * Inject Content Snippet
	 */
	public function inject_content_snippet( $content ) {
		// Only run on main query and single product pages
		if ( ! is_product() || ! is_main_query() ) {
			return $content;
		}

		$options = get_option( 'o100_options', array() );
		$enabled = $options['o100_seo_snippet_enabled'] ?? '';
		if ( $enabled === 'off' ) {
			return $content;
		}

		global $post;
		if ( ! $post ) {
			return $content;
		}

		// Skip if already batch-processed (has our marker class in post_content)
		if ( strpos( $post->post_content, 'o100-seo-keyword' ) !== false ) {
			return $content;
		}

		$rule = ! empty( $options['o100_seo_snippet_rule'] ) ? $options['o100_seo_snippet_rule'] : 'Order [focus_keyword] online from [site_name]. Fresh, fast delivery & pickup!';

		$snippet_text = O100_SEO_Provider::apply_tags( $rule, $post->ID );

		if ( ! empty( $snippet_text ) ) {
			$snippet_html = '<p class="o100-seo-keyword">' . esc_html( $snippet_text ) . '</p>';
			$content = $content . "\n" . $snippet_html;
		}

		return $content;
	}
}




// TS: 20260127175320

// TS: 20260316165730
