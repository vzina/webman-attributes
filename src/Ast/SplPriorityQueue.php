<?php
/**
 * SplPriorityQueue.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use ReturnTypeWillChange;

class SplPriorityQueue extends \SplPriorityQueue
{
    protected $serial = PHP_INT_MAX;

    #[ReturnTypeWillChange]
    public function insert($value, $priority)
    {
        if (! is_array($priority)) {
            $priority = [$priority, $this->serial > 0 ? $this->serial-- : $this->serial = PHP_INT_MAX];
        }
        parent::insert($value, $priority);
    }
}