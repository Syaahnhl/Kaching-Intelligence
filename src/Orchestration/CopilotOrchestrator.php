<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Orchestration;

use InvalidArgumentException;
use Kaching\Intelligence\Contracts\DomainRoutingInterface;
use Kaching\Intelligence\Contracts\IntelligenceContextAssemblerInterface;
use Kaching\Intelligence\Contracts\IntelligenceResultAggregatorInterface;
use Kaching\Intelligence\Contracts\IntentClassificationInterface;
use Kaching\Intelligence\Contracts\MultiDomainExecutionCoordinatorInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceRequestDTO;
use Kaching\Intelligence\DTOs\UnifiedIntelligenceResponseDTO;
use Kaching\Intelligence\Exceptions\TenantContextMismatchException;

class CopilotOrchestrator
{
    public function __construct(
        protected IntentClassificationInterface $classifier,
        protected DomainRoutingInterface $router,
        protected IntelligenceContextAssemblerInterface $contextAssembler,
        protected MultiDomainExecutionCoordinatorInterface $executionCoordinator,
        protected IntelligenceResultAggregatorInterface $aggregator
    ) {
    }

    /**
     * Process an incoming Copilot inquiry through the unified multi-domain pipeline.
     *
     * @param UnifiedIntelligenceRequestDTO $request
     * @return UnifiedIntelligenceResponseDTO
     * @throws TenantContextMismatchException
     */
    public function process(UnifiedIntelligenceRequestDTO $request): UnifiedIntelligenceResponseDTO
    {
        $startTime = microtime(true);

        // 1. Boundary & Actor Security Validation
        $this->validateActorContext($request->actor);

        // 2. Intent & Entity Classification
        $classification = $this->classifier->classify($request->message, $request->actor);

        // 3. Selective Domain Routing & Role Permission Filter
        $requestedDomains = !empty($request->requestedDomains)
            ? $request->requestedDomains
            : $classification['domains'];

        $activeDomains = $this->router->resolveRoutes($requestedDomains, $request->actor);

        // 4. Assemble POPO Contexts from Core adapters / input parameters
        $assembledContexts = $this->contextAssembler->assemble(
            $request,
            $activeDomains,
            $classification['entities']
        );

        // 5. Coordinated Multi-Domain Execution (with partial failure protection)
        $domainResults = $this->executionCoordinator->coordinate(
            $activeDomains,
            $assembledContexts,
            $request
        );

        $durationMs = (microtime(true) - $startTime) * 1000.0;

        // 6. Aggregate Results, Propose Actions & Compose Final Response
        return $this->aggregator->aggregate(
            $domainResults,
            $request,
            $classification,
            $durationMs
        );
    }

    protected function validateActorContext(ActorContextDTO $actor): void
    {
        if ($actor->userId <= 0) {
            throw new TenantContextMismatchException("Invalid tenant identifier: {$actor->userId}");
        }

        // Outlet identifier must not be negative
        if ($actor->outletId !== null && $actor->outletId < 0) {
            throw new TenantContextMismatchException("Invalid outlet identifier: {$actor->outletId}");
        }
    }
}
