<?php

namespace App\Services\Payments;

/**
 * The gateway could not be reached or answered with a server error, so we do not know whether
 * the request was processed. Callers must treat the outcome as unknown (never as "failed") and
 * confirm it later with a status check instead of charging again.
 */
class GatewayUnavailable extends \RuntimeException
{
}
