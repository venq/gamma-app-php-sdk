<?php

declare(strict_types=1);

namespace Gamma\SDK\Tests\Enums;

use Gamma\SDK\Enums\ImageModel;
use Gamma\SDK\Enums\Language;
use PHPUnit\Framework\TestCase;

final class EnumPropertyTest extends TestCase
{
    public function testLanguageValuesAreUniqueAndIsoLike(): void
    {
        $cases = Language::cases();
        $values = array_map(static fn (Language $lang) => $lang->value, $cases);

        self::assertSameSize(array_flip($values), $values, 'Language enum contains duplicate values.');

        for ($i = 0; $i < 50; $i++) {
            $random = $cases[random_int(0, count($cases) - 1)];
            self::assertMatchesRegularExpression('/^[a-z]{2}(?:-[A-Z]{2})?$/', $random->value);
        }
    }

    public function testImageModelValuesAreSlugFormatted(): void
    {
        $cases = ImageModel::cases();
        $values = array_map(static fn (ImageModel $model) => $model->value, $cases);
        self::assertSameSize(array_flip($values), $values, 'ImageModel enum contains duplicate values.');

        foreach ($cases as $model) {
            self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $model->value);
        }
    }
}
