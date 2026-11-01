<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\PromptContextDTO;
use Kaching\Intelligence\DTOs\PromptResultDTO;

interface PromptEngineInterface
{
    /**
     * Menyusun prompt sistem dan metadata secara deterministik dari PromptContextDTO.
     */
    public function render(PromptContextDTO $context): PromptResultDTO;

    /**
     * Resolusi locale aktif ('id' atau 'en').
     *
     * @param array<string, mixed> $options
     */
    public static function resolveLocale(array $options = []): string;

    /**
     * Deteksi kluster intent dari pesan pengguna.
     *
     * @return array<int, string>
     */
    public static function detectIntents(string $message): array;

    /**
     * Menyaring daftar tools berdasarkan konteks aktif, tier, dan deteksi intent pesan pengguna.
     *
     * @param array<int, array<string, mixed>> $allTools
     * @return array<int, array<string, mixed>>
     */
    public function filterTools(
        array $allTools,
        string $context,
        string $tier,
        ?PromptContextDTO $promptContext = null
    ): array;
}
