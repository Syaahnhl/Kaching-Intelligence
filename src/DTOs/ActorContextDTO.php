<?php

namespace Kaching\Intelligence\DTOs;

readonly class ActorContextDTO
{
    public function __construct(
        public int $userId,
        public ?int $outletId,
        public string $role = 'staff',
        public string $planTier = 'basic',
        public string $locale = 'id'
    ) {}

    public function toArray(): array
    {
        return [
            'user_id'   => $this->userId,
            'outlet_id' => $this->outletId,
            'role'      => $this->role,
            'plan_tier' => $this->planTier,
            'locale'    => $this->locale,
        ];
    }
}
