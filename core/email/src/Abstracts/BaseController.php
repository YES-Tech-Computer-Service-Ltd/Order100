<?php
namespace Order100\Notification\Engine\Abstracts;

/**
 * Base Rest API Controller
 */
abstract class BaseController {

    /**
     * Check if a user has permission to access a router
     *
     * @return bool
     */
    public function permission_callback() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Returns response when verify nonce failed
     *
     * @return \WP_REST_Response
     */
    public function nonce_failure_response() {
        return new \WP_REST_Response(
            [
                'success' => false,
                'code'    => 'nonce_failure',
                'message' => __( 'Verify nonce failed', 'order100' ),
            ]
        );
    }

    /**
     * Verify nonce
     *
     * @return int|false
     */
    public function verify_nonce( $request ) {
        return true;
    }

    /**
     * Function API exec
     *
     * @param  callable $callable
     * @param  \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function exec( $callable, \WP_REST_Request $request ) {

        if ( ! $this->verify_nonce( $request ) ) {
            return rest_ensure_request( $this->nonce_failure_response() );
        }

        try {
            if ( is_callable( $callable ) ) {
                $response = $callable( $request );
            }
        } catch ( \Throwable $ex ) {
            return rest_ensure_response(
                [
                    'isError' => true,
                    'code'    => $ex->getCode(),
                    'message' => $ex->getMessage(),
                ]
            );
        }

        return rest_ensure_response( $response );
    }

}



// TS: 20260105164831
