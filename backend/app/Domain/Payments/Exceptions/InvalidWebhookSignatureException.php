<?php

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException {}
