<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Razorpay rejected the configured Key ID / Key Secret (HTTP 401).
 */
class RazorpayAuthenticationException extends RuntimeException
{
}
