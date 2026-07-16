<?php
/**
 * Transactional — 数据库事务注解。
 *
 * 标记方法自动包裹数据库事务。方法正常返回则提交，抛出异常则回滚。
 *
 * @param string  $connection  数据库连接名，默认 'default'
 * @param int     $attempts    死锁重试次数，默认 1
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Transactional extends AbstractAttribute
{
    public function __construct(
        public string $connection = 'default',
        public int $attempts = 1,
        public $transactionHandler = null
    ) {
    }
}
