<?php

namespace Kaching\Intelligence\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Kaching\Intelligence\Services\ChatbotPromptBuilder;
use Kaching\Intelligence\DTOs\ActorContextDTO;

class PromptBuilderBehaviorTest extends TestCase
{
    public function test_prompt_builder_generates_system_prompt_without_laravel(): void
    {
        $context = new ActorContextDTO(
            userId: 42,
            outletId: 7,
            role: 'owner',
            planTier: 'ultimate'
        );

        $prompt = ChatbotPromptBuilder::buildPrompt(ChatbotPromptBuilder::CONTEXT_COMMAND_CENTER, 'ultimate', $context);

        $this->assertIsString($prompt);
        $this->assertStringContainsString('Kaching', $prompt);
    }

    public function test_prompt_builder_bilingual_localization(): void
    {
        $contextId = new ActorContextDTO(userId: 1, outletId: 1, locale: 'id');
        $contextEn = new ActorContextDTO(userId: 1, outletId: 1, locale: 'en');

        $promptId = ChatbotPromptBuilder::buildPrompt(ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT, 'pro', $contextId, ['locale' => 'id']);
        $promptEn = ChatbotPromptBuilder::buildPrompt(ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT, 'pro', $contextEn, ['locale' => 'en']);

        $this->assertIsString($promptId);
        $this->assertIsString($promptEn);
        $this->assertStringContainsString('Bahasa Indonesia', $promptId);
        $this->assertStringContainsString('English', $promptEn);
    }

    public function test_prompt_builder_filters_tools_for_context(): void
    {
        $context = new ActorContextDTO(userId: 1, outletId: 1, planTier: 'basic');

        $toolClusters = ChatbotPromptBuilder::getToolClusters();
        $this->assertIsArray($toolClusters);

        $isAllowed = ChatbotPromptBuilder::isToolAllowedForContext(ChatbotPromptBuilder::CONTEXT_COMMAND_CENTER, 'basic', $context);
        $this->assertFalse($isAllowed);

        $isAllowedPro = ChatbotPromptBuilder::isToolAllowedForContext(ChatbotPromptBuilder::CONTEXT_COMMAND_CENTER, 'pro', $context);
        $this->assertTrue($isAllowedPro);
    }
}
