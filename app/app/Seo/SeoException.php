<?php

namespace App\Seo;

use RuntimeException;

class SeoException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly bool $retryable = false,
        public readonly ?int $costMicros = null,
        public readonly ?int $providerStatus = null,
        public readonly ?string $taskId = null,
    ) {
        parent::__construct($reason);
    }
}
