<?php

namespace Order100\Notification\Engine\PostTypes;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 *  Custom Post Type
 *
 * @method static TemplatePostType get_instance()
 */
class TemplatePostType {
    use SingletonTrait;

    public const POST_TYPE = 'o100_template';

    /**
     * Constructor
     */
    protected function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks when class init
     */
    protected function init_hooks() {
        // Register Custom Post Type for O100ne Template
        add_action( 'init', [ $this, 'register_template_post_type' ], 20 );
    }

    public function register_template_post_type() {
        $labels = [
            'name'               => __( 'Email Template', 'order100' ),
            'singular_name'      => __( 'Email Template', 'order100' ),
            'add_new'            => __( 'Add New Email Template', 'order100' ),
            'add_new_item'       => __( 'Add a new Email Template', 'order100' ),
            'edit_item'          => __( 'Edit Email Template', 'order100' ),
            'new_item'           => __( 'New Email Template', 'order100' ),
            'view_item'          => __( 'View Email Template', 'order100' ),
            'search_items'       => __( 'Search Email Template', 'order100' ),
            'not_found'          => __( 'No Email Template found', 'order100' ),
            'not_found_in_trash' => __( 'No Email Template currently trashed', 'order100' ),
            'parent_item_colon'  => '',
        ];
        $args   = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => false,
            'query_var'           => true,
            'rewrite'             => true,
            'capability_type'     => self::POST_TYPE,
            'capabilities'        => [],
            'hierarchical'        => false,
            'menu_position'       => null,
            'exclude_from_search' => true,
            'supports'            => [ 'title', 'author', 'thumbnail', 'revisions' ],
        ];
        register_post_type( self::POST_TYPE, $args );
    }
}




// TS: 20260105223132

// TS: 20260106144919
