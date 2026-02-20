<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\Attribute;

use ReflectionClass;
use Attribute;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\Attribute\ProvideMarkdownResponse;

class ProvideMarkdownResponseTest extends TestCase
{
    public function testDefaultEnabledIsTrue(): void
    {
        $attribute = new ProvideMarkdownResponse();

        self::assertTrue($attribute->enabled);
    }

    public function testEnabledCanBeSetToFalse(): void
    {
        $attribute = new ProvideMarkdownResponse(enabled: false);

        self::assertFalse($attribute->enabled);
    }

    public function testEnabledCanBeSetToTrue(): void
    {
        $attribute = new ProvideMarkdownResponse(enabled: true);

        self::assertTrue($attribute->enabled);
    }

    public function testAttributeTargetsClassAndMethod(): void
    {
        $reflection = new ReflectionClass(ProvideMarkdownResponse::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        self::assertNotEmpty($attributes);

        $attributeInstance = $attributes[0]->newInstance();

        self::assertSame(
            Attribute::TARGET_CLASS | Attribute::TARGET_METHOD,
            $attributeInstance->flags,
        );
    }

    public function testAttributeCanBeAppliedToClass(): void
    {
        $reflection = new ReflectionClass(AnnotatedClass::class);
        $attrs      = $reflection->getAttributes(ProvideMarkdownResponse::class);

        self::assertCount(1, $attrs);
        self::assertTrue($attrs[0]->newInstance()->enabled);
    }

    public function testAttributeCanBeAppliedToMethod(): void
    {
        $reflection = new ReflectionClass(AnnotatedClass::class);
        $attrs      = $reflection->getMethod('disabledAction')->getAttributes(ProvideMarkdownResponse::class);

        self::assertCount(1, $attrs);
        self::assertFalse($attrs[0]->newInstance()->enabled);
    }
}

#[ProvideMarkdownResponse]
class AnnotatedClass
{
    #[ProvideMarkdownResponse(enabled: false)]
    public function disabledAction(): void {}
}
