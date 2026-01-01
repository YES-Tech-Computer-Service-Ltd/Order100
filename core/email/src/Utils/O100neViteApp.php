<?php

namespace Order100\Notification\Engine\Utils;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * O100ne Vite App enqueue logic
 */
class O100neViteApp {
    use SingletonTrait;

    public const BASE_PATH = O100NE_PLUGIN_URL . 'assets/dist/builder/';

    private $entries              = [];
    private $manifest             = [];
    private $preload_module_files = [];
    private $style_files          = [];

    protected function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 99 );
        add_action( 'admin_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 99 );
    }

    public function enqueue_entry( string $key = 'src/main.tsx', array $deps = [] ) {
        if ( isset( $this->entries[ $key ] ) ) {
            return;
        }
        $this->entries[ $key ] = [
            'key'  => $key,
            'deps' => $deps,
        ];
    }

    public function wp_enqueue_scripts() {
        if ( empty( $this->entries ) ) {
            return;
        }

        if ( ! O100NE_IS_DEVELOPMENT ) {
            $this->load_manifest();
            foreach ( $this->entries as $entry_opts ) {
                $this->load_entry( $entry_opts['key'] );
            }
        }

        $this->register_entry_modules();

        add_action( 'admin_head', [ $this, 'register_preload_modules' ] );
        add_action( 'wp_head', [ $this, 'register_preload_modules' ] );

        $this->register_styles();
    }

    private function load_entry( $entry_key ) {
        $entry_options = $this->get_module_opts( $entry_key );
        if ( null === $entry_options ) {
            return;
        }

        if ( isset( $entry_options['css'] ) ) {
            $this->load_styles( $entry_options['css'] );
        }

        if ( isset( $entry_options['imports'] ) ) {
            foreach ( $entry_options['imports'] as $import_key ) {
                $import_module_opt = $this->get_module_opts( $import_key );

                if ( ! in_array( $import_module_opt['file'], $this->preload_module_files, true ) ) {
                    $this->preload_module_files[] = $import_module_opt['file'];
                }

                if ( isset( $import_module_opt['css'] ) ) {
                    $this->load_styles( $import_module_opt['css'] );
                }
            }
        }
    }

    private function register_entry_modules() {
        add_filter(
            'script_loader_tag',
            function ( $tag, $handle, $src ) {
                if ( strpos( $handle, 'module/o100ne/' ) !== false ) {
                    $str  = "type='module'";
                    $str .= O100NE_IS_DEVELOPMENT ? ' crossorigin' : '';
                    $tag  = '<script ' . $str . ' src="' . $src . '" id="' . $handle . '-js"></script>';
                }
                return $tag;
            },
            10,
            3
        );

        foreach ( $this->entries as $entry_key => $entry_opts ) {
            if ( O100NE_IS_DEVELOPMENT ) {
                wp_register_script( "module/o100ne/$entry_key", "http://localhost:3000/$entry_key", $entry_opts['deps'], null, true );// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

            } else {
                $url = self::BASE_PATH . $this->get_module_opts( $entry_key )['file'];
                wp_register_script( "module/o100ne/$entry_key", $url, $entry_opts['deps'], O100NE_VERSION, true );
            }

            wp_enqueue_script( "module/o100ne/$entry_key" );
        }

        do_action( 'o100ne_after_enqueue_scripts', $this->entries );
    }

    public function register_preload_modules() {
        if ( O100NE_IS_DEVELOPMENT ) {
            echo '<script type="module">
            import RefreshRuntime from "http://localhost:3000/@react-refresh"
            RefreshRuntime.injectIntoGlobalHook(window)
            window.$RefreshReg$ = () => {}
            window.$RefreshSig$ = () => (type) => type
            window.__vite_plugin_react_preamble_installed__ = true
            </script>';

        } else {
            foreach ( $this->preload_module_files as $file ) {
                echo ( '<link rel="modulepreload" href="' . esc_attr( self::BASE_PATH . $file ) . '">' );
            }
        }
    }

    private function register_styles() {
        if ( O100NE_IS_DEVELOPMENT ) {
            return;
        }

        foreach ( $this->style_files as $file ) {
            wp_register_style( 'o100ne/' . $file, self::BASE_PATH . $file, [], O100NE_VERSION );
            wp_enqueue_style( 'o100ne/' . $file );
        }
    }

    private function load_manifest() {
        global $wp_filesystem;
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();

        $content = $wp_filesystem->get_contents( O100NE_PLUGIN_PATH . 'assets/dist/builder/.vite/manifest.json' );

        // when $wp_filesystem is not available, use file_get_contents
        if ( ! $content ) {
            $content = file_get_contents( O100NE_PLUGIN_PATH . 'assets/dist/builder/.vite/manifest.json' );
        }

        $this->manifest = json_decode( $content, true );
    }

    private function get_module_opts( $key ) {
        return $this->manifest[ $key ] ?? null;
    }

    private function load_styles( array $style_files ) {
        if ( empty( $style_files ) ) {
            return;
        }
        foreach ( $style_files as $file ) {
            if ( ! in_array( $file, $this->style_files, true ) ) {
                $this->style_files[] = $file;
            }
        }
    }
}



