<?php

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\Contracts\PromptEngineInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\PromptContextDTO;
use Kaching\Intelligence\DTOs\PromptResultDTO;
use Kaching\Intelligence\Services\ChatbotPromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptEngineInterfaceTest extends TestCase
{
    private PromptEngineInterface $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ChatbotPromptBuilder();
    }

    public function test_engine_implements_prompt_engine_interface(): void
    {
        $this->assertInstanceOf(PromptEngineInterface::class, $this->engine);
    }

    public function test_render_returns_valid_prompt_result_dto(): void
    {
        $actor = new ActorContextDTO(
            userId: 1,
            outletId: 2,
            role: 'owner',
            planTier: 'pro',
            locale: 'id'
        );

        $context = new PromptContextDTO(
            actor: $actor,
            locale: 'id',
            context: ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT,
            tier: 'pro',
            options: [
                'user_message' => 'Berapa omset hari ini?'
            ]
        );

        $result = $this->engine->render($context);

        $this->assertInstanceOf(PromptResultDTO::class, $result);
        $this->assertNotEmpty($result->systemPrompt);
        $this->assertStringContainsString('Data Copilot', $result->systemPrompt);
        $this->assertEquals('id', $result->locale);
        $this->assertEquals(ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT, $result->personaContext);
        $this->assertTrue($result->hasToolAccess);
        $this->assertGreaterThan(0, $result->estimatedTokenCount);
        $this->assertEquals(1, $result->metadata['actor_id']);
        $this->assertEquals(2, $result->metadata['outlet_id']);
    }

    public function test_render_supports_english_locale(): void
    {
        $context = new PromptContextDTO(
            locale: 'en',
            context: ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT,
            tier: 'pro',
            options: ['locale' => 'en']
        );

        $result = $this->engine->render($context);

        $this->assertEquals('en', $result->locale);
        $this->assertStringContainsString('Data Copilot', $result->systemPrompt);
        $this->assertStringContainsString('PRIMARY RESPONSE LANGUAGE: ENGLISH', $result->systemPrompt);
    }

    public function test_intent_detection(): void
    {
        $intents = $this->engine->detectIntents('Tolong carikan menu kopi susu dan hitung profit');
        $this->assertContains('menu', $intents);
    }

    public function test_filter_tools_for_context(): void
    {
        $allTools = [
            ['function' => ['name' => 'query_financial_metrics']],
            ['function' => ['name' => 'execute_raw_mutation']],
        ];

        $context = new PromptContextDTO(
            context: ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT,
            tier: 'pro'
        );

        $filtered = $this->engine->filterTools($allTools, ChatbotPromptBuilder::CONTEXT_FINANCE_COPILOT, 'pro', $context);
        $this->assertIsArray($filtered);
    }
}
