<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Contracts;

use Gamma\SDK\Contracts\CreateGenerationRequest;
use Gamma\SDK\Enums\CardSplit;
use Gamma\SDK\Enums\Format;
use Gamma\SDK\Enums\ImageModel;
use Gamma\SDK\Enums\Language;
use Gamma\SDK\Enums\TextMode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateGenerationRequestTest extends TestCase
{
    public function testToPayloadNormalisesEnums(): void
    {
        $request = (new CreateGenerationRequest())
            ->withInputText('Hello world')
            ->withFormat(Format::Presentation)
            ->withTextMode(TextMode::Generate)
            ->withNumCards(10)
            ->withCardSplit(CardSplit::Auto)
            ->withTextOptions([
                'language' => Language::RU,
                'tone' => 'friendly',
            ])
            ->withImageOptions([
                'model' => ImageModel::IMAGEN_4_PRO,
                'style' => 'photorealistic',
            ])
            ->withExportAs('pdf');

        $payload = $request->toPayload();

        self::assertIsArray($payload);
        self::assertSame('Hello world', $payload['inputText']);
        self::assertSame('presentation', $payload['format']);
        self::assertSame('generate', $payload['textMode']);
        self::assertSame(10, $payload['numCards']);
        self::assertSame('auto', $payload['cardSplit']);
        self::assertIsArray($payload['textOptions']);
        self::assertSame('ru', $payload['textOptions']['language']);
        self::assertIsArray($payload['imageOptions']);
        self::assertSame('imagen-4-pro', $payload['imageOptions']['model']);
        self::assertSame('pdf', $payload['exportAs']);
    }

    public function testMissingMandatoryFieldsThrowException(): void
    {
        $request = new CreateGenerationRequest();

        $this->expectException(InvalidArgumentException::class);
        $request->toPayload();
    }
}
