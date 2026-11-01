<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Tests\Unit;

use Kaching\Intelligence\DTOs\LLMProviderErrorDTO;
use Kaching\Intelligence\DTOs\LLMRequestDTO;
use Kaching\Intelligence\DTOs\LLMResponseDTO;
use Kaching\Intelligence\Providers\AbstractOpenAICompatibleProvider;
use Kaching\Intelligence\Providers\GroqProvider;
use Kaching\Intelligence\Providers\NineRouterProvider;
use Kaching\Intelligence\Providers\OpenAICompatibleProvider;
use Kaching\Intelligence\Services\LLMProviderOrchestrator;
use Kaching\Intelligence\Services\LLMProviderRegistry;
use PHPUnit\Framework\TestCase;

class LLMProviderContractsAndOrchestrationTest extends TestCase
{
    protected function tearDown(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(null);
        parent::tearDown();
    }

    public function test_llm_request_dto_serialization(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'Hello AI'],
        ];
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_sales_summary',
                    'description' => 'Get daily sales',
                ],
            ],
        ];

        $request = new LLMRequestDTO(
            messages: $messages,
            model: 'llama-3.3-70b-versatile',
            temperature: 0.2,
            maxTokens: 512,
            tools: $tools,
            toolChoice: 'auto'
        );

        $payload = $request->toPayload();

        $this->assertSame('llama-3.3-70b-versatile', $payload['model']);
        $this->assertSame(0.2, $payload['temperature']);
        $this->assertSame(512, $payload['max_tokens']);
        $this->assertFalse($payload['stream']);
        $this->assertCount(2, $payload['messages']);
        $this->assertCount(1, $payload['tools']);
        $this->assertSame('auto', $payload['tool_choice']);
    }

    public function test_provider_normalizes_successful_text_response(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function ($url, $headers, $payload) {
            $this->assertStringContainsString('Bearer test_groq_key', $headers['Authorization']);
            return [
                'status'  => 200,
                'body'    => json_encode([
                    'id'      => 'chatcmpl-123',
                    'model'   => 'llama-3.3-70b-versatile',
                    'choices' => [
                        [
                            'index'        => 0,
                            'message'      => ['role' => 'assistant', 'content' => 'Halo! Ada yang bisa saya bantu?'],
                            'finish_reason'=> 'stop',
                        ],
                    ],
                    'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 8, 'total_tokens' => 18],
                ]),
                'headers' => ['content-type' => 'application/json'],
            ];
        });

        $provider = new GroqProvider('test_groq_key');
        $request = new LLMRequestDTO(
            messages: [['role' => 'user', 'content' => 'Halo']],
            model: 'llama-3.3-70b-versatile'
        );

        $response = $provider->sendChatRequest($request);

        $this->assertTrue($response->success);
        $this->assertSame('groq', $response->providerIdentifier);
        $this->assertSame('Halo! Ada yang bisa saya bantu?', $response->content);
        $this->assertFalse($response->hasToolCalls());
        $this->assertSame('stop', $response->finishReason);
        $this->assertSame(18, $response->usage['total_tokens']);
        $this->assertNull($response->error);
    }

    public function test_provider_normalizes_tool_calls_response(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function () {
            return [
                'status'  => 200,
                'body'    => json_encode([
                    'id'      => 'chatcmpl-999',
                    'model'   => 'kachingcopilot',
                    'choices' => [
                        [
                            'index'        => 0,
                            'message'      => [
                                'role'       => 'assistant',
                                'content'    => null,
                                'tool_calls' => [
                                    [
                                        'id'       => 'call_abc123',
                                        'type'     => 'function',
                                        'function' => [
                                            'name'      => 'get_today_sales',
                                            'arguments' => '{"outlet_id": 1}',
                                        ],
                                    ],
                                ],
                            ],
                            'finish_reason'=> 'tool_calls',
                        ],
                    ],
                ]),
                'headers' => ['content-type' => 'application/json'],
            ];
        });

        $provider = new NineRouterProvider('test_9router_key');
        $request = new LLMRequestDTO(
            messages: [['role' => 'user', 'content' => 'Berapa omset hari ini?']],
            model: 'kachingcopilot'
        );

        $response = $provider->sendChatRequest($request);

        $this->assertTrue($response->success);
        $this->assertSame('9router', $response->providerIdentifier);
        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('call_abc123', $response->toolCalls[0]['id']);
        $this->assertSame('get_today_sales', $response->toolCalls[0]['function']['name']);
        $this->assertSame('tool_calls', $response->finishReason);
    }

    public function test_provider_normalizes_rate_limit_and_retry_after(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function () {
            return [
                'status'  => 429,
                'body'    => 'Rate limit reached. Please try again in 2.5s.',
                'headers' => ['retry-after' => '2.5'],
            ];
        });

        $provider = new GroqProvider('test_groq_key');
        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'Hi']], model: 'm');

        $response = $provider->sendChatRequest($request);

        $this->assertFalse($response->success);
        $this->assertNotNull($response->error);
        $this->assertSame(LLMProviderErrorDTO::ERROR_RATE_LIMIT, $response->error->errorType);
        $this->assertSame(429, $response->error->httpStatus);
        $this->assertEquals(2.5, $response->error->retryAfterSeconds);
        $this->assertTrue($response->error->isRetryable());
        $this->assertStringNotContainsString('test_groq_key', $response->error->safeMessage);
    }

    public function test_orchestrator_falls_back_from_groq_to_9router_on_server_error(): void
    {
        $calls = [];
        AbstractOpenAICompatibleProvider::setMockTransport(function ($url) use (&$calls) {
            $calls[] = $url;
            if (str_contains($url, 'groq.com')) {
                return [
                    'status' => 503,
                    'body'   => 'Service Unavailable',
                    'headers'=> [],
                ];
            }
            if (str_contains($url, '9router.com')) {
                return [
                    'status' => 200,
                    'body'   => json_encode([
                        'choices' => [
                            [
                                'message' => ['role' => 'assistant', 'content' => 'Jawaban dari 9Router'],
                            ],
                        ],
                    ]),
                    'headers'=> [],
                ];
            }
            return ['status' => 404, 'body' => 'not found', 'headers' => []];
        });

        $registry = new LLMProviderRegistry([
            new GroqProvider('groq_key', 'https://api.groq.com/openai/v1'),
            new NineRouterProvider('9router_key', 'https://api.9router.com/v1'),
        ]);

        $orchestrator = new LLMProviderOrchestrator(
            registry: $registry,
            primaryProviderIdentifier: 'groq',
            fallbackChain: ['9router']
        );

        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'test']], model: 'auto');
        $response = $orchestrator->execute($request);

        $this->assertTrue($response->success);
        $this->assertSame('9router', $response->providerIdentifier);
        $this->assertSame('Jawaban dari 9Router', $response->content);
        $this->assertCount(2, $calls);
    }

    public function test_orchestrator_aborts_without_fallback_on_auth_failure(): void
    {
        $calls = [];
        AbstractOpenAICompatibleProvider::setMockTransport(function ($url) use (&$calls) {
            $calls[] = $url;
            return [
                'status' => 401,
                'body'   => 'Unauthorized',
                'headers'=> [],
            ];
        });

        $registry = new LLMProviderRegistry([
            new GroqProvider('bad_groq_key', 'https://api.groq.com/openai/v1'),
            new NineRouterProvider('valid_9router_key', 'https://api.9router.com/v1'),
        ]);

        $orchestrator = new LLMProviderOrchestrator(
            registry: $registry,
            primaryProviderIdentifier: 'groq',
            fallbackChain: ['9router']
        );

        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'test']], model: 'auto');
        $response = $orchestrator->execute($request);

        $this->assertFalse($response->success);
        $this->assertSame('groq', $response->providerIdentifier);
        $this->assertSame(LLMProviderErrorDTO::ERROR_AUTH, $response->error->errorType);
        $this->assertCount(1, $calls); // Did NOT fall back silently on auth failure
    }

    public function test_provider_normalizes_timeout_and_connection_failure(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function () {
            throw new \RuntimeException('cURL error 28: Operation timed out after 5000 milliseconds with 0 bytes received');
        });

        $provider = new GroqProvider('test_groq_key');
        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'Hi']], model: 'm');

        $response = $provider->sendChatRequest($request);

        $this->assertFalse($response->success);
        $this->assertNotNull($response->error);
        $this->assertSame(LLMProviderErrorDTO::ERROR_TIMEOUT, $response->error->errorType);
        $this->assertTrue($response->error->isRetryable());
    }

    public function test_provider_normalizes_quota_exhaustion(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function () {
            return [
                'status'  => 403,
                'body'    => json_encode(['error' => ['message' => 'Quota exceeded / Insufficient balance']]),
                'headers' => [],
            ];
        });

        $provider = new OpenAICompatibleProvider('openai_key', 'https://api.openai.com/v1', 'gpt-4o');
        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'Hi']], model: 'gpt-4o');

        $response = $provider->sendChatRequest($request);

        $this->assertFalse($response->success);
        $this->assertSame(LLMProviderErrorDTO::ERROR_QUOTA, $response->error->errorType);
        $this->assertFalse($response->error->isRetryable());
    }

    public function test_provider_normalizes_malformed_response(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function () {
            return [
                'status'  => 200,
                'body'    => '<html><body>Bad Gateway Cloudflare Error</body></html>',
                'headers' => ['content-type' => 'text/html'],
            ];
        });

        $provider = new GroqProvider('test_groq_key');
        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'Hi']], model: 'm');

        $response = $provider->sendChatRequest($request);

        $this->assertFalse($response->success);
        $this->assertSame(LLMProviderErrorDTO::ERROR_MALFORMED_RESPONSE, $response->error->errorType);
    }

    public function test_orchestrator_handles_all_providers_failing(): void
    {
        AbstractOpenAICompatibleProvider::setMockTransport(function ($url) {
            return [
                'status' => 500,
                'body'   => 'Internal Server Error',
                'headers'=> [],
            ];
        });

        $registry = new LLMProviderRegistry([
            new GroqProvider('groq_key', 'https://api.groq.com/openai/v1'),
            new NineRouterProvider('9router_key', 'https://api.9router.com/v1'),
        ]);

        $orchestrator = new LLMProviderOrchestrator(
            registry: $registry,
            primaryProviderIdentifier: 'groq',
            fallbackChain: ['9router']
        );

        $request = new LLMRequestDTO(messages: [['role' => 'user', 'content' => 'test']], model: 'auto');
        $response = $orchestrator->execute($request);

        $this->assertFalse($response->success);
        $this->assertSame('orchestrator_exhausted', $response->providerIdentifier); // Exhausted orchestrator
        $this->assertSame(LLMProviderErrorDTO::ERROR_SERVER, $response->error->errorType);
    }
}
