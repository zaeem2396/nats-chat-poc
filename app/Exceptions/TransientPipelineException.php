<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Simulated or real transient failure: consumer should NAK so JetStream redelivers (until max_deliver).
 */
final class TransientPipelineException extends RuntimeException {}
