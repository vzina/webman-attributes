<?php
/**
 * Constants.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;
use Vzina\Attributes\Collector\ConstantsCollector;
use Vzina\Attributes\Reflection\AttributeReader;
use Vzina\Attributes\Reflection\ReflectionManager;

#[Attribute(Attribute::TARGET_CLASS)]
class Constants extends AbstractAttribute
{
    public function collectClass(string $className): void
    {
        $data = AttributeReader::getConstants(ReflectionManager::reflectClass($className));

        ConstantsCollector::set($className, $data);
    }
}