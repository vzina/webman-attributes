# Attributes 简易使用文档

## 一、工具简介

Attributes 是适配 Webman 框架的轻量级 PHP 注解工具，通过简单的注解（Attribute）语法，快速实现依赖注入、配置注入、定时任务、事件监听等功能，无需手动编写重复代码。

## 二、环境要求

- PHP 8.0+（需支持原生 Attribute 注解）
- Webman 框架 >= 2.1

## 三、快速配置

修改配置文件 `config/plugin/vzina/attributes/attribute.php`，保留核心配置即可：

```php
return [
    'autoload' => true, // 默认自动加载组件
    'scan_path' => [ // 扫描目录
        app_path(),
        // 组件目录
        // base_path('vendor/vzina/attributes/src'),
        // base_path('plugin/xxx'),
    ],
    'excludes' => [ // 排除部分扫描目录/文件
        'config',
    ],
    'class_map' => [], // 类映射
    'ignores' => [
        // 忽略注解
        // Vzina\Attributes\Attribute\Inject::class,
    ],
    'collectors' => [ // 注册收集器
        Vzina\Attributes\Collector\AttributeCollector::class,
        Vzina\Attributes\Collector\AspectCollector::class,
    ],
    'aspects' => [ // 注册切面
        Vzina\Attributes\Attribute\InjectAspect::class,
    ],
];
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

### 3. 自定义实现定时任务（@Crontab）

通过注解定义定时任务，配置任务进程：`config/process.php`。
```php
namespace app\crontab;

use app\attributes\Crontab;

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

### 4. 自定义实现事件监听（@Listener）

监听指定事件，触发时自动执行处理逻辑，加载监听器：`config/bootstrap.php`。

```php
namespace app\listener;

use app\attributes\Listener;
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