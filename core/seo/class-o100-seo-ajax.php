<?php
/**
 * Smart SEO AJAX Processor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_SEO_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_o100_seo_scan', array( $this, 'scan_products' ) );
		add_action( 'wp_ajax_o100_seo_fix_batch', array( $this, 'process_batch' ) );
		add_action( 'wp_ajax_o100_seo_revert', array( $this, 'revert_batch' ) );
	}

	/**
	 * Scan products and return IDs + preview
	 */
	public function scan_products() {
		check_ajax_referer( 'o100_seo_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$feature = isset( $_POST['feature'] ) ? sanitize_text_field( $_POST['feature'] ) : '';

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$product_ids = get_posts( $args );

		// For dry-run preview
		$preview = array();
		if ( ! empty( $feature ) ) {
			$rule = isset( $_POST['rule'] ) ? stripslashes( $_POST['rule'] ) : '';
			$force = isset( $_POST['force'] ) && $_POST['force'] === 'true';

			// Show ALL products in preview for full comparison
			$preview_ids = $product_ids;

			// For focus_keyword preview: simulate dedup across ALL products
			$simulated_keywords = array();

			foreach ( $preview_ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product ) continue;
				$tags = O100_SEO_Provider::get_parsed_tags( $pid );
				$generated = O100_SEO_Provider::apply_tags( $rule, $pid );

				$sample = array(
					'id'        => $pid,
					'title'     => $product->get_name(),
					'generated' => $generated,
				);

				switch ( $feature ) {
					case 'focus_keyword':
						$current = get_post_meta( $pid, 'rank_math_focus_keyword', true );
						$sample['current'] = $current;
						// Mark status: custom (user-set), empty, or auto
						if ( ! empty( $current ) && ! $force ) {
							$sample['status'] = 'keep'; // Has existing keyword, will not overwrite
						} else {
							$sample['status'] = 'update';
							// Simulate dedup
							$kw = strtolower( $generated );
							if ( isset( $simulated_keywords[ $kw ] ) ) {
								$deduped = $this->deduplicate_keyword(
									$generated,
									$tags['[dish_name]'],
									$tags['[chinese_name]'],
									$pid,
									array_keys( $simulated_keywords )
								);
								$sample['generated'] = $deduped;
								$sample['dedup'] = true;
								$kw = strtolower( $deduped );
							}
							$simulated_keywords[ $kw ] = $pid;
						}
						break;

					case 'alt_text':
						$thumb_id = get_post_thumbnail_id( $pid );
						if ( ! $thumb_id ) {
							$sample['current'] = '(no image)';
							$sample['generated'] = '(no image — skip)';
							$sample['status'] = 'skip';
						} else {
							$sample['current'] = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
						}
						break;

					case 'slug':
						$sample['current'] = get_post( $pid )->post_name;
						break;

					case 'image_rename':
						$thumb_id = get_post_thumbnail_id( $pid );
						if ( ! $thumb_id ) {
							$sample['current'] = '(no image)';
							$sample['generated'] = '(no image — skip)';
							$sample['status'] = 'skip';
							$sample['gallery_count'] = 0;
						} else {
							$current_file = basename( get_attached_file( $thumb_id ) );
							$sample['current'] = $current_file;
							$ext = pathinfo( $current_file, PATHINFO_EXTENSION );
							$sample['generated'] = $tags['[focus_keyword_slug]'] . '.' . ( $ext ?: 'jpg' );
							$gallery = $product->get_gallery_image_ids();
							$sample['gallery_count'] = count( $gallery );
						}
						break;

					case 'title_desc':
						$title_rule = isset( $_POST['title_rule'] ) ? stripslashes( $_POST['title_rule'] ) : '[focus_keyword] in [location] | [site_name]';
						$desc_rule = isset( $_POST['desc_rule'] ) ? stripslashes( $_POST['desc_rule'] ) : '';
						$current_title = get_post_meta( $pid, 'rank_math_title', true );
						$current_desc = get_post_meta( $pid, 'rank_math_description', true );
						$gen_title = O100_SEO_Provider::apply_tags( $title_rule, $pid );
						$gen_desc = O100_SEO_Provider::apply_tags( $desc_rule, $pid );
						$sample['current'] = ( $current_title ?: '(auto)' ) . ' | ' . ( $current_desc ? mb_substr( $current_desc, 0, 40 ) . '...' : '(auto)' );
						$sample['generated'] = $gen_title . ' | ' . mb_substr( $gen_desc, 0, 40 ) . '...';
						// Mark status
						if ( ( ! empty( $current_title ) || ! empty( $current_desc ) ) && ! $force ) {
							$sample['status'] = 'keep';
						} else {
							$sample['status'] = 'update';
						}
						break;

					case 'snippet':
						$post_obj = get_post( $pid );
						$content = $post_obj ? $post_obj->post_content : '';
						$snippet_generated = O100_SEO_Provider::apply_tags( $rule, $pid );
						// Check if keyword sentence already exists
						$has_snippet = ( strpos( $content, 'o100-seo-keyword' ) !== false );
						$sample['current'] = $has_snippet ? '(has keyword sentence)' : '(no keyword in content)';
						$sample['generated'] = mb_substr( $snippet_generated, 0, 80 ) . ( mb_strlen( $snippet_generated ) > 80 ? '...' : '' );
						if ( $has_snippet && ! $force ) {
							$sample['status'] = 'keep';
						} else {
							$sample['status'] = 'update';
						}
						break;
				}

				$preview[] = $sample;
			}
		}

		wp_send_json_success( array(
			'total'   => count( $product_ids ),
			'ids'     => $product_ids,
			'preview' => $preview,
		) );
	}

	/**
	 * Process a batch of product IDs
	 */
	public function process_batch() {
		check_ajax_referer( 'o100_seo_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', $_POST['product_ids'] ) : array();
		$feature     = isset( $_POST['feature'] ) ? sanitize_text_field( $_POST['feature'] ) : '';
		$rule        = isset( $_POST['rule'] ) ? stripslashes( $_POST['rule'] ) : '';
		$force       = isset( $_POST['force'] ) && $_POST['force'] === 'true';

		// Manual overrides from editable preview (pid => value)
		$overrides = array();
		if ( isset( $_POST['overrides'] ) && is_array( $_POST['overrides'] ) ) {
			foreach ( $_POST['overrides'] as $pid => $val ) {
				$overrides[ intval( $pid ) ] = sanitize_text_field( $val );
			}
		}

		if ( empty( $product_ids ) || empty( $feature ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		if ( ! in_array( $feature, array( 'image_rename', 'title_desc' ), true ) && empty( $rule ) && empty( $overrides ) ) {
			wp_send_json_error( 'Missing rule template' );
		}

		$processed = 0;
		$log = array();
		$changed_ids = array();

		foreach ( $product_ids as $product_id ) {
			// Save backup before processing
			$this->save_backup( $product_id, $feature );

			// If user manually edited this product's value, apply directly
			if ( isset( $overrides[ $product_id ] ) && ! empty( $overrides[ $product_id ] ) ) {
				$result = $this->apply_override( $product_id, $feature, $overrides[ $product_id ] );
			} else {
				$result = $this->process_single( $product_id, $feature, $rule, $force );
			}

			if ( $result['changed'] ) {
				$processed++;
				$changed_ids[] = $product_id;

				// Recalculate Rank Math SEO score after changes
				$this->update_rank_math_score( $product_id );
			}
			if ( ! empty( $result['message'] ) ) {
				$log[] = $result['message'];
			}
		}

		// Mark this feature as having revertable changes
		if ( ! empty( $changed_ids ) ) {
			update_option( 'o100_seo_revert_' . $feature, $changed_ids );
		}

		wp_send_json_success( array(
			'processed'   => $processed,
			'changed_ids' => $changed_ids,
			'log'         => $log,
		) );
	}

	// ═══════════════════════════════════════════════════════════════
	// BACKUP & REVERT
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Save original value before overwriting so we can revert.
	 */
	private function save_backup( $product_id, $feature ) {
		$meta_key_map = array(
			'focus_keyword' => 'rank_math_focus_keyword',
			'alt_text'      => '_o100_backup_alt',  // handled specially
			'slug'          => '_o100_backup_slug',
			'snippet'       => 'rank_math_snippet_description',
		);

		switch ( $feature ) {
			case 'focus_keyword':
				$old = get_post_meta( $product_id, 'rank_math_focus_keyword', true );
				update_post_meta( $product_id, '_o100_backup_focus_keyword', $old );
				break;

			case 'alt_text':
				$thumb_id = get_post_thumbnail_id( $product_id );
				if ( $thumb_id ) {
					$old = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
					update_post_meta( $product_id, '_o100_backup_alt_text', $old );
					update_post_meta( $product_id, '_o100_backup_alt_attach_id', $thumb_id );
				}
				break;

			case 'slug':
				$post = get_post( $product_id );
				update_post_meta( $product_id, '_o100_backup_slug', $post->post_name );
				break;

			case 'snippet':
				$old = get_post_meta( $product_id, 'rank_math_description', true );
				update_post_meta( $product_id, '_o100_backup_snippet', $old );
				break;

			case 'title_desc':
				$old_title = get_post_meta( $product_id, 'rank_math_title', true );
				$old_desc  = get_post_meta( $product_id, 'rank_math_description', true );
				update_post_meta( $product_id, '_o100_backup_title', $old_title );
				update_post_meta( $product_id, '_o100_backup_desc', $old_desc );
				break;
		}
	}

	/**
	 * Revert batch changes for a specific feature.
	 */
	public function revert_batch() {
		check_ajax_referer( 'o100_seo_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$feature = isset( $_POST['feature'] ) ? sanitize_text_field( $_POST['feature'] ) : '';
		if ( empty( $feature ) ) {
			wp_send_json_error( 'Missing feature' );
		}

		$revert_ids = get_option( 'o100_seo_revert_' . $feature, array() );
		if ( empty( $revert_ids ) ) {
			wp_send_json_error( 'No changes to revert' );
		}

		$reverted = 0;

		foreach ( $revert_ids as $product_id ) {
			switch ( $feature ) {
				case 'focus_keyword':
					$old = get_post_meta( $product_id, '_o100_backup_focus_keyword', true );
					if ( $old !== '' || metadata_exists( 'post', $product_id, '_o100_backup_focus_keyword' ) ) {
						update_post_meta( $product_id, 'rank_math_focus_keyword', $old );
						delete_post_meta( $product_id, '_o100_backup_focus_keyword' );
						$this->update_rank_math_score( $product_id );
						$reverted++;
					}
					break;

				case 'alt_text':
					$old = get_post_meta( $product_id, '_o100_backup_alt_text', true );
					$thumb_id = get_post_meta( $product_id, '_o100_backup_alt_attach_id', true );
					if ( $thumb_id ) {
						update_post_meta( $thumb_id, '_wp_attachment_image_alt', $old );
						delete_post_meta( $product_id, '_o100_backup_alt_text' );
						delete_post_meta( $product_id, '_o100_backup_alt_attach_id' );
						$reverted++;
					}
					break;

				case 'slug':
					$old_slug = get_post_meta( $product_id, '_o100_backup_slug', true );
					if ( $old_slug ) {
						wp_update_post( array(
							'ID'        => $product_id,
							'post_name' => $old_slug,
						) );
						delete_post_meta( $product_id, '_o100_backup_slug' );
						$reverted++;
					}
					break;

				case 'snippet':
					$old = get_post_meta( $product_id, '_o100_backup_snippet', true );
					if ( metadata_exists( 'post', $product_id, '_o100_backup_snippet' ) ) {
						update_post_meta( $product_id, 'rank_math_description', $old );
						delete_post_meta( $product_id, '_o100_backup_snippet' );
						$reverted++;
					}
					break;

				case 'title_desc':
					$old_title = get_post_meta( $product_id, '_o100_backup_title', true );
					$old_desc  = get_post_meta( $product_id, '_o100_backup_desc', true );
					if ( metadata_exists( 'post', $product_id, '_o100_backup_title' ) ) {
						update_post_meta( $product_id, 'rank_math_title', $old_title );
						update_post_meta( $product_id, 'rank_math_description', $old_desc );
						delete_post_meta( $product_id, '_o100_backup_title' );
						delete_post_meta( $product_id, '_o100_backup_desc' );
						$this->update_rank_math_score( $product_id );
						$reverted++;
					}
					break;
			}
		}

		// Clear revert marker
		delete_option( 'o100_seo_revert_' . $feature );

		wp_send_json_success( array(
			'reverted' => $reverted,
			'message'  => "{$reverted} products reverted to original values.",
		) );
	}

	// ═══════════════════════════════════════════════════════════════
	// RANK MATH SEO SCORE COMPUTATION
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Calculate and store a basic Rank Math SEO score.
	 *
	 * Rank Math normally computes this client-side in the editor JS.
	 * We compute a reasonable score based on which SEO fields are populated.
	 */
	private function update_rank_math_score( $product_id ) {
		$score = 0;

		$keyword = get_post_meta( $product_id, 'rank_math_focus_keyword', true );
		if ( ! empty( $keyword ) ) {
			$score += 20; // Has focus keyword

			$post = get_post( $product_id );
			$title = $post->post_title;
			$content = $post->post_content . ' ' . $post->post_excerpt;
			$slug = $post->post_name;

			// Keyword in title
			if ( stripos( $title, $keyword ) !== false ) {
				$score += 15;
			}

			// Keyword in slug
			$kw_slug = sanitize_title( $keyword );
			if ( stripos( $slug, $kw_slug ) !== false || stripos( $slug, str_replace( ' ', '-', $keyword ) ) !== false ) {
				$score += 10;
			}

			// Keyword in content
			if ( stripos( $content, $keyword ) !== false ) {
				$score += 10;
			}

			// SEO description exists and contains keyword
			$desc = get_post_meta( $product_id, 'rank_math_description', true );
			if ( ! empty( $desc ) ) {
				$score += 5;
				if ( stripos( $desc, $keyword ) !== false ) {
					$score += 5;
				}
			}

			// Image alt text contains keyword
			$thumb_id = get_post_thumbnail_id( $product_id );
			if ( $thumb_id ) {
				$score += 5;
				$alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
				if ( stripos( $alt, $keyword ) !== false ) {
					$score += 10;
				}
			}

			// URL length <= 75 chars
			$permalink = get_permalink( $product_id );
			if ( strlen( $permalink ) <= 75 ) {
				$score += 5;
			}

			// WooCommerce product schema (auto)
			$score += 5;

			// Has content
			if ( strlen( $content ) > 50 ) {
				$score += 5;
			}

			// Not duplicate keyword
			$score += 5;
		}

		$score = min( 100, $score );

		update_post_meta( $product_id, 'rank_math_seo_score', $score );
	}

	/**
	 * Process a single product
	 */
	private function process_single( $product_id, $feature, $rule, $force ) {
		$result = array( 'changed' => false, 'message' => '' );

		switch ( $feature ) {
			case 'focus_keyword':
				$result = $this->process_focus_keyword( $product_id, $rule, $force );
				break;

			case 'alt_text':
				$result = $this->process_alt_text( $product_id, $rule, $force );
				break;

			case 'slug':
				$result = $this->process_slug( $product_id, $rule, $force );
				break;

			case 'image_rename':
				$result = $this->process_image_rename( $product_id, $force );
				break;

			case 'title_desc':
				$result = $this->process_title_desc( $product_id, $force );
				break;

			case 'snippet':
				$result = $this->process_snippet( $product_id, $rule, $force );
				break;
		}

		return $result;
	}

	/**
	 * Apply a manual override value directly (user edited in preview table).
	 *
	 * @param int    $product_id  Product ID
	 * @param string $feature     Feature type
	 * @param string $value       User-provided override value
	 * @return array  Result with 'changed' and 'message'
	 */
	private function apply_override( $product_id, $feature, $value ) {
		$result = array( 'changed' => false, 'message' => '' );

		switch ( $feature ) {
			case 'focus_keyword':
				$value = strtolower( $value );
				update_post_meta( $product_id, 'rank_math_focus_keyword', $value );
				$this->update_rank_math_score( $product_id );
				$result['changed'] = true;
				$result['message'] = "#{$product_id}: \"{$value}\" (manual)";
				break;

			case 'alt_text':
				$thumb_id = get_post_thumbnail_id( $product_id );
				if ( $thumb_id ) {
					update_post_meta( $thumb_id, '_wp_attachment_image_alt', $value );
					$result['changed'] = true;
				}
				$product = wc_get_product( $product_id );
				if ( $product ) {
					foreach ( $product->get_gallery_image_ids() as $gid ) {
						update_post_meta( $gid, '_wp_attachment_image_alt', $value );
						$result['changed'] = true;
					}
				}
				break;

			case 'slug':
				$post = get_post( $product_id );
				$old_slug = $post->post_name;
				$new_slug = sanitize_title( $value );
				if ( ! empty( $new_slug ) && $new_slug !== $old_slug ) {
					wp_update_post( array( 'ID' => $product_id, 'post_name' => $new_slug ) );
					// 301 redirect via Rank Math
					if ( class_exists( '\\RankMath\\Redirections\\Redirection' ) ) {
						$redir = \RankMath\Redirections\Redirection::from( array(
							'sources'     => array( array( 'pattern' => 'product/' . $old_slug, 'comparison' => 'exact' ) ),
							'url_to'      => get_permalink( $product_id ),
							'header_code' => 301,
							'status'      => 'active',
						) );
						if ( $redir ) $redir->save();
					}
					$result['changed'] = true;
					$result['message'] = "#{$product_id}: {$old_slug} → {$new_slug} (manual)";
				}
				break;

			case 'title_desc':
				// Override not supported for split fields — use normal flow
				$result = $this->process_title_desc( $product_id, true );
				break;

			case 'snippet':
				// Direct override for snippet content
				$post = get_post( $product_id );
				$content = $post->post_content;
				// Remove old snippet
				$content = preg_replace( '/<p[^>]*class\s*=\s*["\']o100-seo-keyword["\'][^>]*>.*?<\/p>\s*/is', '', $content );
				$content = trim( $content );
				$snippet_html = '<p class="o100-seo-keyword">' . esc_html( $value ) . '</p>';
				wp_update_post( array( 'ID' => $product_id, 'post_content' => $content . "\n" . $snippet_html ) );
				$result['changed'] = true;
				break;
		}

		return $result;
	}

	// ═══════════════════════════════════════════════════════════════
	// FOCUS KEYWORD — with global deduplication
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Generate and save focus keyword with DB-level deduplication.
	 *
	 * 1. Generate keyword via [smart_keyword] or rule template
	 * 2. Check if any other product already has this keyword
	 * 3. If duplicate: progressively add back dropped words
	 * 4. Last resort: append Chinese name
	 */
	private function process_focus_keyword( $product_id, $rule, $force ) {
		$result = array( 'changed' => false, 'message' => '' );

		$tags = O100_SEO_Provider::get_parsed_tags( $product_id );
		$generated = O100_SEO_Provider::apply_tags( $rule, $product_id );

		if ( empty( trim( $generated ) ) ) {
			return $result;
		}

		$existing = get_post_meta( $product_id, 'rank_math_focus_keyword', true );
		if ( ! empty( $existing ) && ! $force ) {
			return $result;
		}

		// Get all existing keywords in the DB (excluding this product)
		$existing_keywords = $this->get_all_focus_keywords( $product_id );

		// Deduplicate
		$final_keyword = $this->deduplicate_keyword(
			$generated,
			$tags['[dish_name]'],
			$tags['[chinese_name]'],
			$product_id,
			$existing_keywords
		);

		$final_keyword = strtolower( $final_keyword );

		update_post_meta( $product_id, 'rank_math_focus_keyword', $final_keyword );
		$result['changed'] = true;
		$result['message'] = "#{$product_id}: \"{$final_keyword}\"";

		return $result;
	}

	/**
	 * Get all existing focus keywords from the database, excluding a specific product.
	 *
	 * @param int $exclude_product_id  Product ID to exclude
	 * @return array  Array of lowercase keyword strings
	 */
	private function get_all_focus_keywords( $exclude_product_id ) {
		global $wpdb;

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT LOWER(pm.meta_value)
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = 'rank_math_focus_keyword'
			   AND pm.meta_value != ''
			   AND p.post_type = 'product'
			   AND p.post_status = 'publish'
			   AND pm.post_id != %d",
			$exclude_product_id
		) );

		return $results ?: array();
	}

	/**
	 * Make a keyword unique by progressively adding back dropped words.
	 *
	 * Recovery order:
	 * 1. Cooking methods (Crispy, Steamed, BBQ) — most differentiating
	 * 2. Flavor words (Spicy, Garlic, Honey)
	 * 3. Cuisine/noise words (Cantonese, Special) — last resort from original
	 * 4. Chinese name — absolute fallback
	 *
	 * @param string $keyword           Generated keyword
	 * @param string $dish_name         Original full dish name
	 * @param string $chinese_name      Chinese characters from title
	 * @param int    $product_id        Current product ID
	 * @param array  $existing_keywords All existing keywords in DB (lowercase)
	 * @return string  Unique keyword
	 */
	private function deduplicate_keyword( $keyword, $dish_name, $chinese_name, $product_id, $existing_keywords ) {
		$lower_keyword = strtolower( $keyword );

		// Not a duplicate? Return as-is.
		if ( ! in_array( $lower_keyword, $existing_keywords, true ) ) {
			return $keyword;
		}

		// Get recoverable words from the original dish name
		$recoverable = O100_SEO_Provider::get_recoverable_words( $dish_name, $keyword );

		// Try adding words one by one
		$modified = $keyword;
		foreach ( $recoverable as $word ) {
			$modified = $word . ' ' . $modified;
			$lower_mod = strtolower( $modified );
			if ( ! in_array( $lower_mod, $existing_keywords, true ) ) {
				return $modified;
			}
		}

		// Still duplicate? Append Chinese name
		if ( ! empty( $chinese_name ) ) {
			$with_chinese = $keyword . ' ' . $chinese_name;
			$lower_chinese = strtolower( $with_chinese );
			if ( ! in_array( $lower_chinese, $existing_keywords, true ) ) {
				return $with_chinese;
			}
		}

		// Absolute last resort: append product ID
		return $keyword . ' ' . $product_id;
	}

	// ═══════════════════════════════════════════════════════════════
	// ALT TEXT
	// ═══════════════════════════════════════════════════════════════

	private function process_alt_text( $product_id, $rule, $force ) {
		$result = array( 'changed' => false, 'message' => '' );

		$generated_value = O100_SEO_Provider::apply_tags( $rule, $product_id );
		if ( empty( trim( $generated_value ) ) ) {
			return $result;
		}

		$updated_any = false;
		$thumbnail_id = get_post_thumbnail_id( $product_id );
		if ( $thumbnail_id ) {
			$existing_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
			if ( empty( $existing_alt ) || $force ) {
				update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', $generated_value );
				$updated_any = true;
			}
		}

		$product = wc_get_product( $product_id );
		if ( $product ) {
			$gallery_ids = $product->get_gallery_image_ids();
			foreach ( $gallery_ids as $gallery_id ) {
				$existing_alt = get_post_meta( $gallery_id, '_wp_attachment_image_alt', true );
				if ( empty( $existing_alt ) || $force ) {
					update_post_meta( $gallery_id, '_wp_attachment_image_alt', $generated_value );
					$updated_any = true;
				}
			}
		}

		if ( $updated_any ) {
			$result['changed'] = true;
		}

		return $result;
	}

	// ═══════════════════════════════════════════════════════════════
	// SLUG
	// ═══════════════════════════════════════════════════════════════

	private function process_slug( $product_id, $rule, $force ) {
		$result = array( 'changed' => false, 'message' => '' );

		$generated_value = O100_SEO_Provider::apply_tags( $rule, $product_id );
		if ( empty( trim( $generated_value ) ) ) {
			return $result;
		}

		$post = get_post( $product_id );
		$old_slug = $post->post_name;

		$use_filter = isset( $_POST['slug_filter'] ) && $_POST['slug_filter'] === 'true';
		if ( $use_filter && ! $force ) {
			if ( preg_match( '/[a-z]+/', $old_slug ) && ! preg_match( '/[\x{4e00}-\x{9fa5}]+/u', $old_slug ) && ! is_numeric( str_replace( '-', '', $old_slug ) ) ) {
				return $result;
			}
		}

		$new_slug = sanitize_title( $generated_value );

		if ( ! empty( $new_slug ) && $new_slug !== $old_slug ) {
			wp_update_post( array(
				'ID'        => $product_id,
				'post_name' => $new_slug,
			) );

			// Rank Math 301 Redirect
			if ( class_exists( '\RankMath\Redirections\Redirection' ) ) {
				$redirection = \RankMath\Redirections\Redirection::from( array(
					'sources'     => array(
						array(
							'pattern'    => 'product/' . $old_slug . '/?$',
							'comparison' => 'regex',
						),
						array(
							'pattern'    => 'product/' . $old_slug,
							'comparison' => 'exact',
						),
					),
					'url_to'      => get_permalink( $product_id ),
					'header_code' => 301,
					'status'      => 'active',
				) );

				if ( $redirection ) {
					$redirection->save();
				}
			}

			$result['changed'] = true;
			$result['message'] = "#{$product_id}: {$old_slug} → {$new_slug}";
		}

		return $result;
	}

	// ═══════════════════════════════════════════════════════════════
	// IMAGE RENAME
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Rename product images to clean English filenames.
	 *
	 * Featured image: dish-name-slug.ext
	 * Gallery images: dish-name-slug-1.ext, dish-name-slug-2.ext, ...
	 * Thumbnails: dish-name-slug-WxH.ext
	 */
	private function process_image_rename( $product_id, $force = false ) {
		$result = array( 'changed' => false, 'message' => '' );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $result;
		}

		$tags = O100_SEO_Provider::get_parsed_tags( $product_id );
		// Use focus keyword slug as base (consistent with keyword-first workflow)
		$base_slug = $tags['[focus_keyword_slug]'];

		if ( empty( $base_slug ) || $base_slug === '-' ) {
			return $result;
		}

		$renamed_count = 0;

		// 1. Featured image
		$thumbnail_id = get_post_thumbnail_id( $product_id );
		if ( $thumbnail_id ) {
			if ( $this->rename_attachment( $thumbnail_id, $base_slug, '', $force ) ) {
				$renamed_count++;
			}
		}

		// 2. Gallery images
		$gallery_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_ids as $index => $gallery_id ) {
			if ( $this->rename_attachment( $gallery_id, $base_slug, '-' . ( $index + 1 ), $force ) ) {
				$renamed_count++;
			}
		}

		if ( $renamed_count > 0 ) {
			$result['changed'] = true;
			$result['message'] = "#{$product_id}: Renamed {$renamed_count} image(s) → {$base_slug}.*";
		}

		return $result;
	}

	/**
	 * Rename a single WordPress attachment file + all thumbnails.
	 *
	 * @param int    $attachment_id  WP attachment ID
	 * @param string $new_base_name  New filename without extension
	 * @param string $suffix         Optional suffix (e.g. "-1" for gallery)
	 * @param bool   $force          Overwrite even if filename looks valid
	 * @return bool  True if file was renamed
	 */
	private function rename_attachment( $attachment_id, $new_base_name, $suffix = '', $force = false ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		$dir      = dirname( $file_path );
		$ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$old_base = pathinfo( $file_path, PATHINFO_FILENAME );
		$new_name = $new_base_name . $suffix;

		// Skip if already correctly named
		if ( $old_base === $new_name ) {
			return false;
		}

		// Skip clean filenames unless force
		if ( ! $force ) {
			$has_chinese      = preg_match( '/[\x{4e00}-\x{9fa5}]/u', $old_base );
			$is_numeric_only  = preg_match( '/^\d+$/', $old_base );
			$is_hash          = preg_match( '/^[a-f0-9]{8,}$/i', $old_base );
			$has_encoded      = preg_match( '/%[0-9A-Fa-f]{2}/', $old_base );
			$has_non_ascii    = preg_match( '/[^\x20-\x7E]/', $old_base );

			if ( ! $has_chinese && ! $is_numeric_only && ! $is_hash && ! $has_encoded && ! $has_non_ascii ) {
				return false; // Already clean English filename
			}
		}

		// Avoid filename collision
		$target_file = $dir . '/' . $new_name . '.' . $ext;
		$counter = 0;
		while ( file_exists( $target_file ) && $target_file !== $file_path ) {
			$counter++;
			$target_file = $dir . '/' . $new_name . '-v' . $counter . '.' . $ext;
		}
		if ( $counter > 0 ) {
			$new_name = $new_name . '-v' . $counter;
		}

		// ── 1. Rename main file ──
		$new_file_path = $dir . '/' . $new_name . '.' . $ext;
		if ( ! @rename( $file_path, $new_file_path ) ) {
			return false;
		}

		// ── 2. Delete old thumbnails ──
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_data ) {
				$old_thumb_path = $dir . '/' . $size_data['file'];
				if ( file_exists( $old_thumb_path ) ) {
					@unlink( $old_thumb_path );
				}
			}
		}

		// ── 3. Update WordPress records ──
		$upload_dir    = wp_upload_dir();
		$relative_path = ltrim( str_replace( $upload_dir['basedir'], '', $new_file_path ), '/' );

		update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );

		$new_url = $upload_dir['baseurl'] . '/' . $relative_path;
		wp_update_post( array(
			'ID'         => $attachment_id,
			'guid'       => $new_url,
			'post_title' => ucwords( str_replace( '-', ' ', $new_name ) ),
			'post_name'  => $new_name,
		) );

		// ── 4. Regenerate all thumbnails from renamed main file ──
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$new_metadata = wp_generate_attachment_metadata( $attachment_id, $new_file_path );
		if ( ! empty( $new_metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $new_metadata );
		}

		clean_attachment_cache( $attachment_id );

		// ── 5. Global URL replacement in database ──
		// Fix hardcoded URLs in post_content, Elementor data, page builders, etc.
		$old_url = $upload_dir['baseurl'] . '/' . ltrim( str_replace( $upload_dir['basedir'], '', $file_path ), '/' );

		if ( $old_url !== $new_url ) {
			global $wpdb;

			// Replace main file URL in post_content
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				$old_url,
				$new_url,
				'%' . $wpdb->esc_like( $old_url ) . '%'
			) );

			// Replace in postmeta
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s",
				$old_url,
				$new_url,
				'%' . $wpdb->esc_like( $old_url ) . '%'
			) );

			// Also replace base name pattern for thumbnail URLs (old-name-300x300.jpg → new-name-300x300.jpg)
			$old_base_url = $upload_dir['baseurl'] . '/' . dirname( ltrim( str_replace( $upload_dir['basedir'], '', $file_path ), '/' ) ) . '/' . $old_base;
			$new_base_url = $upload_dir['baseurl'] . '/' . dirname( $relative_path ) . '/' . $new_name;

			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				$old_base_url,
				$new_base_url,
				'%' . $wpdb->esc_like( $old_base_url ) . '%'
			) );

			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s",
				$old_base_url,
				$new_base_url,
				'%' . $wpdb->esc_like( $old_base_url ) . '%'
			) );
		}

		return true;
	}

	// ═══════════════════════════════════════════════════════════════
	// SEO TITLE & DESCRIPTION — Batch write to Rank Math meta
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Generate and save SEO title + description to Rank Math meta fields.
	 */
	private function process_title_desc( $product_id, $force ) {
		$result = array( 'changed' => false, 'message' => '' );

		$options = get_option( 'o100_options', array() );
		$title_rule = $options['o100_seo_title_rule'] ?? '[focus_keyword] in [location] | [site_name]';
		$desc_rule = $options['o100_seo_desc_rule'] ?? '';

		if ( empty( $title_rule ) && empty( $desc_rule ) ) {
			return $result;
		}

		$current_title = get_post_meta( $product_id, 'rank_math_title', true );
		$current_desc = get_post_meta( $product_id, 'rank_math_description', true );

		// Skip if existing data and not forcing
		if ( ! $force && ( ! empty( $current_title ) || ! empty( $current_desc ) ) ) {
			$result['message'] = "#{$product_id}: Skipped (existing title/desc)";
			return $result;
		}

		$changed = false;

		if ( ! empty( $title_rule ) ) {
			$new_title = O100_SEO_Provider::apply_tags( $title_rule, $product_id );
			if ( ! empty( $new_title ) && $new_title !== $current_title ) {
				update_post_meta( $product_id, 'rank_math_title', $new_title );
				$changed = true;
			}
		}

		if ( ! empty( $desc_rule ) ) {
			$new_desc = O100_SEO_Provider::apply_tags( $desc_rule, $product_id );
			if ( ! empty( $new_desc ) && $new_desc !== $current_desc ) {
				update_post_meta( $product_id, 'rank_math_description', $new_desc );
				$changed = true;
			}
		}

		if ( $changed ) {
			$result['changed'] = true;
			$result['message'] = "#{$product_id}: Title/Description updated";
		}

		return $result;
	}

	// ═══════════════════════════════════════════════════════════════
	// CONTENT SNIPPET — Append hidden SEO paragraph
	// ═══════════════════════════════════════════════════════════════

	/**
	 * Append a hidden SEO snippet to the product's post_content.
	 */
	private function process_snippet( $product_id, $rule, $force ) {
		$result = array( 'changed' => false, 'message' => '' );

		if ( empty( $rule ) ) {
			return $result;
		}

		$post = get_post( $product_id );
		if ( ! $post ) {
			return $result;
		}

		$content = $post->post_content;

		// Check if keyword snippet already exists (by our marker class)
		$has_snippet = ( strpos( $content, 'o100-seo-keyword' ) !== false );

		if ( $has_snippet && ! $force ) {
			$result['message'] = "#{$product_id}: Skipped (keyword sentence exists)";
			return $result;
		}

		// If force: remove old snippet first
		if ( $has_snippet && $force ) {
			$content = preg_replace( '/<p[^>]*class\s*=\s*["\']o100-seo-keyword["\'][^>]*>.*?<\/p>\s*/is', '', $content );
			// Also remove legacy hidden snippets
			$content = preg_replace( '/<p[^>]*style\s*=\s*["\']display:\s*none["\'][^>]*>.*?<\/p>\s*/is', '', $content );
			$content = trim( $content );
		}

		$snippet_text = O100_SEO_Provider::apply_tags( $rule, $product_id );

		if ( empty( $snippet_text ) ) {
			return $result;
		}

		// Wrap in a <p> with marker class, APPEND to content end
		$snippet_html = '<p class="o100-seo-keyword">' . esc_html( $snippet_text ) . '</p>';
		$new_content = $content . "\n" . $snippet_html;

		wp_update_post( array(
			'ID'           => $product_id,
			'post_content' => $new_content,
		) );

		$result['changed'] = true;
		$result['message'] = "#{$product_id}: Keyword sentence appended";

		return $result;
	}
}

