# Kaching Intelligence Engine

`kaching/intelligence` is the autonomous intelligence, AI orchestration, financial copilot, forecasting, and advisory package for Kaching POS.

## Architectural Principles

1. **Proposer-Only Safety Invariant:**
   The Intelligence layer generates read-only insights, financial evaluations, and actionable proposals (`ActionProposalDTO`). It has **zero direct mutation privileges** over Core databases or financial records. All actions require Core approval and human-in-the-loop authorization.
2. **Tenant & Outlet Context Isolation:**
   Every request must carry an authenticated `ActorContextDTO`. Context cannot be altered or injected via LLM prompt or tool arguments.
3. **Decoupled & Portable:**
   Independent of Laravel HTTP kernel and Eloquent ORM. Operates in pure PHP 8.2+ environments.

## Directory Structure

```
src/
├── Contracts/       # Interfaces for integration adapters and providers
├── DTOs/            # Immutable Data Transfer Objects
├── Exceptions/      # Domain specific exceptions
└── Services/        # Prompt assembly and engine orchestration
tests/
├── Contract/        # Provider contract compliance tests
├── Fakes/           # In-memory test doubles
└── Unit/            # Isolated unit test suites
```

## Integration Flow

```
Authenticated Core User + Request
  │
  ▼
Core Boundary Adapter (ChatbotController / Integration Layer)
  │
  ▼
ActorContextDTO
  │
  ▼
KachingIntelligenceEngine ───► InventoryDataProviderInterface / FinancialDataProviderInterface
  │
  ▼
ActionProposalDTO
  │
  ▼
ProposalSinkInterface ───► Core ActionPolicyService ───► Human Approval / PIN Verification
```

## Running Tests

```bash
composer dump-autoload
./vendor/bin/phpunit
```

## License

Proprietary & Confidential. Copyright (c) 2026 Kaching POS.
