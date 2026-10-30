<?php

namespace App\Services\Payments;

/** What a mobile-money gateway told us about one payment attempt, in our own vocabulary. */
final class GatewayResult
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $state,
        public readonly ?string $clientReference = null,
        public readonly ?string $providerReference = null,
        public readonly ?float $amount = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public function isPaid(): bool
    {
        return $this->state === self::PAID;
    }

    public function isPending(): bool
    {
        return $this->state === self::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->state === self::FAILED;
    }
}
