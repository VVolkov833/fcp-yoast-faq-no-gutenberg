<?php

/*
Plugin Name: FCP Yoast FAQ Post Type
Description: Use the "Yoast SEO" FAQ Gutenberg block outside the Gutenberg editor via <code>[yoast-faq id=FAQpageID]</code>
Version: 1.0.0
Requires at least: 5.0.0
Requires PHP: 7.0.0
Author: Firmcatalyst, Vadim Volkov
Author URI: https://firmcatalyst.com
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// the requirements above are conditional - just mentioned, because the plugin was tested on those and higher

class FCPYoastFAQPostType {

    public $inits;

    public function __construct() {

        add_action( 'plugins_loaded', [ $this, 'start' ] );

	}
	
	public function start() {

        $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

        if (
            !in_array( 'wordpress-seo/wp-seo.php', $active_plugins ) &&
            !in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins )
        ) {
            return;
        }

        $this->inits = (object) [
            'name' => 'FAQ',
            'slug' => 'faq',
            'shortcode' => 'yoast-faq',
            'gutenberg_allow' => [
                'core/heading',
                'core/paragraph',
                'yoast/faq-block'
            ]
        ];

		add_action( 'init', [ $this, 'add_post_type' ] );
		add_shortcode( $this->inits->shortcode, [ $this, 'add_short_code' ] );

		// print the shortcode column in the list of faq posts
        add_filter( 'manage_'.$this->inits->slug.'_posts_columns', [ $this, 'add_column_name' ] );
        add_action( 'manage_'.$this->inits->slug.'_posts_custom_column' , [ $this, 'print_row_shortcode' ], 10, 2 );	

        add_filter( 'allowed_block_types', [ $this, 'limit_gutenberg' ], 10, 2 );
	}
	
	public function add_post_type() {

        $labels = [
            'name'                => $this->inits->name . ' Sections',
            'singular_name'       => $this->inits->name . ' Section',
            'menu_name'           => $this->inits->name . ' Sections',
            'all_items'           => 'All ' . $this->inits->name,
            'view_item'           => 'View ' . $this->inits->name,
            'add_new_item'        => 'Add New ' . $this->inits->name,
            'add_new'             => 'Add New',
            'edit_item'           => 'Edit ' . $this->inits->name,
            'update_item'         => 'Update ' . $this->inits->name,
            'search_items'        => 'Search ' . $this->inits->name,
            'not_found'           => $this->inits->name . ' Not Found',
            'not_found_in_trash'  => $this->inits->name . ' Not found in Trash',
        ];
            
        $args = [
            'label'               => $this->inits->slug,
            'description'         => $this->inits->name . ' sections for further on-page implemention',
            'labels'              => $labels,
            'supports'            => [
                                        'title',
                                        'editor'
                                    ],
            'hierarchical'        => false,
            'public'              => false,
            'show_in_rest'        => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => true,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-portfolio',
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'page',
        ];

        register_post_type( $this->inits->slug, $args );

    }

    public function limit_gutenberg( $allowed_blocks, $post ) {

        if ( $post->post_type !== $this->inits->slug || !$this->inits->gutenberg_allow ) {
            return $allowed_blocks;
        }

        return $this->inits->gutenberg_allow;
    }

    public function add_short_code( $atts ) {

        if ( !$atts['id'] || !is_numeric( $atts['id'] ) )
            return;

        $query = new WP_Query( [
            'post_type' => $this->inits->slug,
            'p' => $atts['id']
        ]);

        $result = '';
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();

                $result = get_the_content();
                
                if ( !preg_match( '/\<\!--\s+wp\:yoast\/faq-block\s+(.*?)\s+--\>/si', $result, $matches ) ) {
                    continue;
                }

                $data = json_decode( $matches[1] );
 
                $structured = [];
                foreach ( $data->questions as $k => $v ) {
                    $structured[] = [
                        '@type' => 'Question',
                        'name' => strip_tags( $v->jsonQuestion ),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $v->jsonAnswer
                        ]
                    ];
                }

                $structured = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $structured
                ];

                $result = preg_replace( '/\<\!--\s*(.*?)\s*--\>/si', '', $result );

                $result .= '<script type="application/ld+json">'.json_encode( $structured, JSON_PRETTY_PRINT ).'</script>';
   
            }
        }

        wp_reset_postdata();

        return $result;
    }

    public function add_column_name($columns) {

        $result = [];

        foreach ( $columns as $k => $v ) {
            if ( $k == 'date' ) { // add before date
                $result['shortcode'] = 'ShortCode';
            }
            $result[$k] = $v;
        }

        return $result;

    }

    public function print_row_shortcode($column, $post_id) {

        if ( $column == 'shortcode' ) {
            echo '<code>['.$this->inits->shortcode.' id='.$post_id.']</code>';
        }

    }

}

new FCPYoastFAQPostType();
