<?php
/**
 * GetMapping.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class GetMapping extends Mapping
{
    public function __construct(?string $path = null, array $options = [])
    {
        parent::__construct($path, ['GET'], $options);
    }
}