<?php
/**
 * TestFixtures.php
 * PHP 8 attribute fixture classes for AttributeReader tests.
 * Must be loaded before tests run.
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Fixtures;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;
use Vzina\Attributes\Attribute\Message;

// A simple class-level attribute
#[Attribute(Attribute::TARGET_CLASS)]
class TestClassAttr extends AbstractAttribute
{
}

// A simple method-level attribute
#[Attribute(Attribute::TARGET_METHOD)]
class TestMethodAttr extends AbstractAttribute
{
}

// A simple property-level attribute
#[Attribute(Attribute::TARGET_PROPERTY)]
class TestPropertyAttr extends AbstractAttribute
{
}

// A simple constant-level attribute
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class TestConstAttr extends AbstractAttribute
{
}

// A test class with all attribute types
#[TestClassAttr]
class AttributedClass
{
    #[TestPropertyAttr]
    public string $testProp = 'default';

    #[TestMethodAttr]
    public function testMethod(): string
    {
        return 'hello';
    }

    #[TestConstAttr]
    public const STATUS_ACTIVE = 1;
}

// A class without attributes for negative testing
class PlainClass
{
    public string $name = 'plain';

    public function doSomething(): void
    {
    }

    public const EMPTY = 0;
}

// Enum for constant attribute testing
enum TestStatus: int
{
    #[TestConstAttr]
    case ACTIVE = 1;

    #[TestConstAttr]
    case INACTIVE = 0;

    case NO_ATTR = 2;
}

// Class with constants and attributes for getConstants testing
class ConstantsClass
{
    #[TestConstAttr]
    public const ACTIVE = 'active';

    #[TestConstAttr]
    public const INACTIVE = 'inactive';

    public const NO_ATTR = 'no_attr';
}
