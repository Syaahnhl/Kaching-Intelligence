<?php

namespace Kaching\Intelligence\DTOs;

readonly class IntelligenceResponseDTO
{
    public function __construct(
        public string $reply,
        public string $status = 'success',
        public ?string $code = null,
        public bool $isFallback = false,
        public ?ActionProposalDTO $proposal = null,
        public ?array $financialCard = null,
        public array $actions = [],
        public array $data = []
    ) {}

    public function toArray(): array
    {
        return [
            'status'         => $this->status,
            'code'           => $this->code,
            'reply'          => $this->reply,
            'is_fallback'    => $this->isFallback,
            'proposal'       => $this->proposal?->toArray(),
            'financial_card' => $this->financialCard,
            'actions'        => $this->actions,
            'data'           => $this->data,
        ];
    }
}
