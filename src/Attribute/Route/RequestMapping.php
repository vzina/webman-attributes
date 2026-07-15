<?php
/**
 * RequestMapping.php
 * @version 8.1
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
class RequestMapping extends Mapping
{
    public function __construct(
        ?string $path = null,
        array|string $methods = ['GET', 'POST'],
        array|string $options = []
    ) {
        parent::__construct($path, $methods, $options);
    }
}