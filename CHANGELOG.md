# Changelog

All notable changes to `kaching/intelligence` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-31

### Added
- Extracted standalone `kaching/intelligence` library from monolithic Kaching Core.
- Core DTOs: `ActorContextDTO`, `ActionProposalDTO`, `FinancialMetricsDTO`, `IntelligenceResponseDTO`, `InventorySnapshotDTO`, `StockoutRiskDTO`, `RestockRecommendationDTO`.
- Contracts: `IntelligenceEngineInterface`, `InventoryDataProviderInterface`, `FinancialDataProviderInterface`, `ProposalSinkInterface`, `IntelligenceContextInterface`.
- Standalone `ChatbotPromptBuilder` decoupled from Laravel HTTP requests and Eloquent models.
- Core `KachingIntelligenceEngine` orchestration implementation.
- Architectural safety invariants: Proposer-Only pattern, strict tenant context isolation.
