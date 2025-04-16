<?php

namespace WPMVC\Exceptions;

use WP_Error;
use Exception;
/**
 * Exception based on a WP_Error.
 * Helper exception class.
 *
 * @author Ale Mostajo
 * @license MIT
 * @package wpmvc-core
 * @version 3.1.14
 */
class WPException extends Exception
{
    /**
     * Related WP_Error.
     * @since 3.1.14
     *
     * @var \WP_Error
     */
    protected $wp_error;
    /**
     * Initializes exception, overrides constructor.
     * @since 3.1.14
     *
     * @param \WP_Error|string $error    WP Error or an error message.
     * @param int              $code     Error code.
     * @param \Throwable       $previous Previous exception.
     */
    public function __construct( $error, $code = 0, $previous = null )
    {
        if ( $error instanceof WP_Error ) {
            $this->wp_error = $error;
        } else {
            $this->wp_error = new WP_Error( $code, $error );
        }
        parent::__construct( $this->wp_error->get_error_message(), intval( $this->wp_error->get_error_code() ), $previous );
    }
    /**
     * Returns WordPress error associated with the exception.
     * @since 3.1.14
     *
     * @return \WP_Error
     */
    public function get_wp_error()
    {
        return $this->wp_error;
    }
}