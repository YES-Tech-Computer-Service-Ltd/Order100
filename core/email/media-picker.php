<?php
/**
 * Standalone WordPress Media Library Picker
 * Opens in a popup window, auto-opens media library, sends selected image back via postMessage
 */
if ( ! defined( 'ABSPATH' ) ) {
    $wp_load_paths = [
        dirname( __FILE__, 5 ) . '/wp-load.php',
        dirname( __FILE__, 6 ) . '/wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    ];
    foreach ( $wp_load_paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            break;
        }
    }
}

if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
    wp_die( 'Access denied.' );
}

wp_enqueue_media();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <title>Select Image - Media Library</title>
    <?php wp_head(); ?>
    <style>
        /* Hide everything — only the WP media modal should be visible */
        body { margin: 0; padding: 0; background: #f0f0f1; }
        #picker-loading {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #646970; font-size: 16px;
        }
    </style>
</head>
<body>
    <div id="picker-loading">Loading Media Library...</div>

    <script>
    (function() {
        function openMediaLibrary() {
            if (!wp || !wp.media) {
                document.getElementById('picker-loading').innerHTML = 
                    '<div style="text-align:center"><p style="color:red">Failed to load WordPress Media Library.</p>' +
                    '<button onclick="window.close()" style="padding:10px 20px;cursor:pointer">Close</button></div>';
                return;
            }

            // Hide loading text once media opens
            document.getElementById('picker-loading').style.display = 'none';

            var frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use This Image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.url || '';
                var alt = attachment.alt || attachment.title || '';

                // Send to parent window (the email editor)
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'wp-media-selected',
                        url: url,
                        alt: alt
                    }, '*');
                }
                // Close this popup
                window.close();
            });

            frame.on('close', function() {
                // User closed without selecting — just close popup
                window.close();
            });

            frame.open();
        }

        // Auto-open after WP scripts are ready
        if (document.readyState === 'complete') {
            setTimeout(openMediaLibrary, 100);
        } else {
            window.addEventListener('load', function() {
                setTimeout(openMediaLibrary, 100);
            });
        }
    })();
    </script>

    <?php wp_footer(); ?>
</body>
</html>


// TS: 20260428035710
