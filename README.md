# Attributes 简易使用文档

## 一、工具简介

Attributes 是适配 Webman 框架的轻量级 PHP 注解工具，通过简单的注解（Attribute）语法，快速实现依赖注入、配置注入、定时任务、事件监听等功能，无需手动编写重复代码，方便hyperf用户过渡使用。

## 二、环境要求

- PHP 8.0+（需支持原生 Attribute 注解）
- Webman 框架 >= 2.1

## 三、安装插件

```shell
composer require -W vzina/attributes
```

## 四、基础使用

### 1. 依赖注入（@Inject）

自动注入类属性，支持懒加载（使用时才实例化）。

```php
namespace app\controller;

use Vzina\Attributes\Attribute\Inject;
use app\service\UserService;

class UserController
{
    // 懒加载注入UserService
    #[Inject(lazy: true)]
    private ?UserService $userService = null;

    public function index()
    {
        // 调用注入的服务方法
        return $this->userService->getUser(1);
    }
}
```

### 2. 配置注入（@Value）

从配置文件中读取值并注入到属性。

```php
namespace app\service;

use Vzina\Attributes\Attribute\Value;

class OrderService
{
    // 读取配置 app.name，默认值为 "webman"
    #[Value(key: 'app.name', default: 'webman')]
    private string $appName;

    public function getAppName()
    {
        return $this->appName; // 直接使用注入的配置值
    }
}
```

### 3. 定时任务（@Crontab）

通过注解定义定时任务。
```php
namespace app\crontab;

use Vzina\Attributes\Attribute\Crontab;

// 每1分钟执行一次
#[Crontab(rule: '* * * * *')]
class OrderStats
{
    // 固定执行方法：handle
    public function handle()
    {
        echo "订单统计任务执行：" . date('Y-m-d H:i:s') . PHP_EOL;
    }
}
```

### 4. 事件监听（@Listener）

监听指定事件，触发时自动执行处理逻辑。

```php
namespace app\listener;

use Vzina\Attributes\Attribute\Listener;
use Webman\Event\Event;

// 监听 "order.created" 事件
#[Listener(event: 'order.created')]
class OrderCreated
{
    // 事件处理方法：handle
    public function handle($params, $event)
    {
        $orderId = $params['order_id'];
        echo "订单 {$orderId} 已创建" . PHP_EOL;
    }
}

// 触发事件（任意位置）
// Event::dispatch('order.created', ['order_id' => 1001]);
```

### 5. 注册路由（@Controller）

通过注解定义路由规则。

```php
namespace app\controller;

use app\routes\RequestMapping;
use Vzina\Attributes\Attribute\Route\AutoController;
use Vzina\Attributes\Attribute\Route\Controller;
use Vzina\Attributes\Attribute\Route\Resource;

// 注册路由
#[Controller(prefix: 'order', options: ['middleware' => []])]
class OrderController
{
    #[RequestMapping(path: 'index', options: ['middleware' => [], 'name' => '路由名称，默认：类名.方法名'])]
    public function index()
    {
        return json([]);
    }
}

// 自动路由
#[AutoController(prefix: 'bar', options: ['middleware' => []])]
class BarController
{
    public function index()
    {
        return json([]);
    }
}

// 资源型路由
#[Resource(prefix: 'bar2')]
class Bar2Controller
{
    public function index()
    {
        return json([]);
    }
}

```

### 6. 缓存功能（@Cacheable）

通过注解定义缓存逻辑

```php
namespace app\services;

use Vzina\Attributes\Attribute\Cacheable;

class OrderService
{
    #[Cacheable(
        prefix: "cache", // 缓存前缀
        value: "#{params.order_id}", // 参数模板，格式：#{方法参数名}，数组及对象：#{方法参数名.元素key/属性名}
        ttl: 60, // 缓存时间
        group: "redis", // 缓存策略，默认：config('cache.default')
        collect: false, // 是否收集缓存key，可用于统一管理，仅支持redis缓存驱动
        evict: false, // true，仅删除缓存
        put: false, // true，仅写入缓存
        aheadSeconds: 0, // 缓存提前更新时间
        lockSeconds: 10, // 更新缓存锁时间，仅支持redis缓存驱动
        offset: 0, // 缓存偏移量
    )]
    public function handle(array $params)
    {
        return time();
    }
}
```

### 7. 枚举（@Constants）

```php
namespace app\constants;

use Vzina\Attributes\Attribute\Constants;
use Vzina\Attributes\Attribute\ConstantsTrait;

#[Constants]
class OrderStats
{
    use ConstantsTrait
    
    /**
     * @Message("完成")
     */
    const SUCCESS = 1;
}

// 使用方法
//OrderStats::getMessage(OrderStats::SUCCESS)

#[Constants]
enum OrderStatsEnum
{
    use ConstantsTrait
    
    /**
     * @Message("完成")
     */
    case SUCCESS = 1;
}

// 使用方法
//OrderStatsEnum::getMessage(OrderStatsEnum::SUCCESS)

```
### 8. 切面（@Aspect）

> 注意使用切面前需要生成项目的自动加载（autoload）文件 `composer dump-autoload -o`

```php
namespace app\aspects;

use GuzzleHttp\Client;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Attribute\Aspect;
use Vzina\Attributes\Attribute\AspectInterface;

#[Aspect]
class ClientAspect implements AspectInterface
{
    public array $classes = [
        Client::class . '::request'
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        var_dump(__METHOD__);
        return $proceedingJoinPoint->process();
    }
}

// 使用方法
$client = new Client();
$r = $client->get('https://www.baidu.com');
var_dump($r->getStatusCode());

// 输出结果：
//string(33) "app\aspects\ClientAspect::process"
//int(200)

```
