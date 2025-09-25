<?php

declare(strict_types=1);

namespace Gamma\SDK\Contracts;

use Gamma\SDK\Enums\CardSplit;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\ImageModel;
use Gamma\SDK\Enums\Language;
use Gamma\SDK\Enums\TextMode;
use InvalidArgumentException;

final class CreateGenerationRequest
{
    private ?string $inputText = null;
    private ?TextMode $textMode = null;
    private ?Format $format = null;
    private ?string $themeName = null;
    private ?int $numCards = null;
    private ?CardSplit $cardSplit = null;
    private ?string $additionalInstructions = null;
    private ?string $exportAs = null;
    /** @var array<string, mixed> */
    private array $textOptions = [];
    /** @var array<string, mixed> */
    private array $imageOptions = [];
    /** @var array<string, mixed> */
    private array $cardOptions = [];
    /** @var array<string, mixed> */
    private array $sharingOptions = [];
    /** @var array<string, mixed> */
    private array $metadata = [];
    /** @var array<int, string> */
    private array $tags = [];

    public function withInputText(string $inputText): self
    {
        $inputText = trim($inputText);
        if ($inputText === '') {
            throw new InvalidArgumentException('Input text must not be empty.');
        }

        $this->inputText = $inputText;

        return $this;
    }

    public function withTextMode(TextMode $textMode): self
    {
        $this->textMode = $textMode;

        return $this;
    }

    public function withFormat(Format $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function withThemeName(?string $themeName): self
    {
        $this->themeName = $themeName !== null ? trim($themeName) : null;

        return $this;
    }

    public function withNumCards(?int $numCards): self
    {
        if ($numCards !== null && $numCards <= 0) {
            throw new InvalidArgumentException('Number of cards must be positive.');
        }

        $this->numCards = $numCards;

        return $this;
    }

    public function withCardSplit(?CardSplit $split): self
    {
        $this->cardSplit = $split;

        return $this;
    }

    public function withAdditionalInstructions(?string $instructions): self
    {
        $this->additionalInstructions = $instructions !== null ? trim($instructions) : null;

        return $this;
    }

    public function withExportAs(?string $exportAs): self
    {
        if ($exportAs === null) {
            $this->exportAs = null;

            return $this;
        }

        $exportAs = strtolower(trim($exportAs));
        if (!in_array($exportAs, ['pdf', 'pptx'], true)) {
            throw new InvalidArgumentException('Export type must be pdf or pptx.');
        }

        $this->exportAs = $exportAs;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withTextOptions(array $options): self
    {
        $normalized = $options;
        if (isset($normalized['language'])) {
            $language = $normalized['language'];
            if ($language instanceof Language) {
                $normalized['language'] = $language->value;
            } elseif (!is_string($language)) {
                throw new InvalidArgumentException('Text options language must be a string or Language enum.');
            }
        }

        $this->textOptions = $normalized;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withImageOptions(array $options): self
    {
        $normalized = $options;
        if (isset($normalized['model'])) {
            $model = $normalized['model'];
            if ($model instanceof ImageModel) {
                $normalized['model'] = $model->value;
            } elseif (!is_string($model)) {
                throw new InvalidArgumentException('Image model must be a string or ImageModel enum.');
            }
        }

        $this->imageOptions = $normalized;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withCardOptions(array $options): self
    {
        if (isset($options['dimensions'])) {
            $dimensions = $options['dimensions'];
            if (!in_array($dimensions, ['16x9', '4x3', 'fluid'], true)) {
                throw new InvalidArgumentException('Card dimensions must be one of 16x9, 4x3, fluid.');
            }
        }

        $this->cardOptions = $options;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withSharingOptions(array $options): self
    {
        $this->sharingOptions = $options;

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @param array<int, string> $tags
     */
    public function withTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $this->assertReady();

        $payload = [
            'inputText' => $this->inputText,
            'textMode' => $this->textMode?->value,
            'format' => $this->format?->value,
            'themeName' => $this->themeName,
            'numCards' => $this->numCards,
            'cardSplit' => $this->cardSplit?->value,
            'additionalInstructions' => $this->additionalInstructions,
            'exportAs' => $this->exportAs,
        ];

        if ($this->textOptions !== []) {
            $payload['textOptions'] = $this->textOptions;
        }

        if ($this->imageOptions !== []) {
            $payload['imageOptions'] = $this->imageOptions;
        }

        if ($this->cardOptions !== []) {
            $payload['cardOptions'] = $this->cardOptions;
        }

        if ($this->sharingOptions !== []) {
            $payload['sharingOptions'] = $this->sharingOptions;
        }

        if ($this->metadata !== []) {
            $payload['metadata'] = $this->metadata;
        }

        if ($this->tags !== []) {
            $payload['tags'] = $this->tags;
        }

        return array_filter($payload, static fn ($value) => $value !== null && $value !== []);
    }

    private function assertReady(): void
    {
        if ($this->inputText === null) {
            throw new InvalidArgumentException('Input text is required.');
        }

        if ($this->textMode === null) {
            throw new InvalidArgumentException('Text mode is required.');
        }

        if ($this->format === null) {
            throw new InvalidArgumentException('Format is required.');
        }
    }
}
