<?php

namespace App\Application\Sealing\Exceptions;

/**
 * US-021: the Stellar network didn't answer — no connection to its RPC, a
 * timeout or a server error. Nothing was decided about the seal: it's
 * retried with the same policy as any other failure.
 */
class NetworkUnavailable extends SealingNetworkError {}
