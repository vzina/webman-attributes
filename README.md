# vzina/attributes

Lightweight PHP 8.1+ attribute-driven AOP & DI toolkit for the [Webman](https://github.com/walkor/webman) framework.

- **Zero boilerplate** — `#[Inject]`, `#[Cacheable]`, `#[Listener]`, `#[Crontab]` and more with a single attribute
- **AOP via AST** — method-level proxy generation using nikic/php-parser, transparent to business code
- **Lazy injection** — auto-generated proxy classes defer service resolution until first access
- **OPcache-friendly caching** — PHP-native `var_export`/`include` cache format, no `unserialize` overhead
- **Multi-process safe** — per-worker scanning with `pcntl_fork` isolation and file locks
- **18 built-in annotations** — DI, AOP, cache, cron, events, routes, validation, transactions, logging, retry, tracing, middleware, CLI commands

---

## Installation

```bash
composer require vzina/attributes
```

Requires PHP 8.1+ and the `pcntl` extension.

| Dependency | Purpose |
|---|---|
| `nikic/php-parser ^5.0` | AST parsing & proxy code generation |
| `illuminate/pipeline >=9.0` | Aspect pipeline execution |
| `php-di/phpdoc-reader ^2.2` | PHPDoc `@var` type resolution |
| `webman/cache ^2.0` | Cache store abstraction |
| `webman/event ^1.0` | Event dispatch/listen |
| `webman/redis ^2.1` | Redis lock & cache collection |

---

## Configuration

### `config/plugin/vzina/attributes/app.php`

```php
return [
    'enable' => true,   // Plugin master switch
];
```

### `config/plugin/vzina/attributes/attribute.php`

```php
return [
    'cacheable' => true,                                    // Enable scan caching
    'scan_path' => [app_path()],                            // Directories to scan
    'excludes'  => ['config', 'Install.php', 'functions.php'], // Excluded paths
    'class_map' => [],                                      // Manual class mappings
    'ignores'   => [],                                      // Attribute classes to ignore
];
```

> **Built-in components** (collectors, aspects, visitors, handlers, proxy loaders) ship with sensible defaults in `AttributeLoader::DEFAULTS`. Override any key in `attribute.php` to extend.

---

## Architecture

```
Composer autoload
  └── bootstrap.php ─────────────→ AttributeLoader::init()
                                     ├── Load config
                                     ├── Register AST visitors
                                     ├── Register property handlers
                                     └── Scanner::scan()
                                           ├── Discover classes (AST-only, no autoload)
                                           ├── PcntlHandler::fork() — child process
                                           │     ├── require_once original files
                                           │     ├── collect attributes → Collectors
                                           │     ├── generate proxies → AspectProxyLoader + LazyLoader
                                           │     └── write cache → exit(0)
                                           └── Parent: load cache → update classMap

Config loading
  └── route.php ───────────────→ DispatcherFactory::init()
                                   └── Register routes from @Controller / @AutoController

Worker startup
  └── AttributeBootstrap::start()
        ├── ListenerHandler::start()   → register @Listener handlers
        ├── DependHandler::start()     → register @Depend services
        ├── Event::dispatch(BootAttribute)
        └── clearReflectionCache()
```

### Key Design Decisions

**Child process isolation** — `PcntlHandler` forks a child during the initial scan. The child loads original classes (for attribute collection), generates proxy files, and exits. The parent/webman worker never loads originals, so the Composer class map can safely redirect to proxy files.

**PHP cache files** — All collector data is serialized via `var_export()` into `.cache.php` files. This leverages OPcache for zero-overhead deserialization and avoids the security risks of `unserialize()`.

**AST-level method rewriting** — `AstProxyCallVisitor` rewrites method bodies at the AST level to delegate through `__proxyCall()`, which drives the Laravel Pipeline of aspect instances.

---

## Feature Reference

### 1. Dependency Injection (`#[Inject]`)

```php
use Vzina\Attributes\Attribute\Inject;

class UserController
{
    #[Inject(lazy: true)]
    private ?UserService $userService = null;

    #[Inject]
    private Logger $logger;

    public function index()
    {
        return $this->userService->find(1);  // resolved on first access
    }
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `value` | `?string` | `null` | Service class FQCN. Auto-resolved from property type if null. |
| `required` | `bool` | `true` | Throw if service not found in container. |
| `lazy` | `bool` | `false` | Generate lazy proxy; service resolved on first method/property access. |

### 2. Configuration Injection (`#[Value]`)

```php
use Vzina\Attributes\Attribute\Value;

class Mailer
{
    #[Value(key: 'mail.host')]
    private string $host = 'localhost';

    #[Value(key: 'mail.port')]
    private int $port = 25;
}
```

Reads from `config()` (Webman Config). The property default value serves as fallback.

### 3. Method Caching (`#[Cacheable]`)

```php
use Vzina\Attributes\Attribute\Cacheable;

class OrderService
{
    #[Cacheable(
        prefix: 'order',
        value:  '#{params.order_id}',
        ttl:    300,
        group:  'redis',
        offset: 30,
    )]
    public function find(array $params): array
    {
        return DB::table('orders')->find($params['order_id']);
    }
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `prefix` | `?string` | `null` | Cache key prefix. Final key: `{prefix}{resolved_value}` |
| `value` | `?string` | `null` | Key template. `#{params.id}` → argument value. Null → md5 of arguments. |
| `ttl` | `?int` | `null` | TTL in seconds. Falls back to `cache.ttl` config. |
| `offset` | `int` | `0` | Random offset added to ttl (anti-thundering-herd). |
| `aheadSeconds` | `int` | `0` | Refresh cache this many seconds before expiry. |
| `lockSeconds` | `int` | `10` | Redis lock TTL for cache refresh (prevents stampede). |
| `group` | `?string` | `null` | Cache store. Falls back to `cache.default`. |
| `collect` | `bool` | `false` | Track cache keys in a Redis SET for bulk eviction. |
| `evict` | `bool` | `false` | Eviction mode: delete cached entry, then execute method. |
| `put` | `bool` | `false` | Write-only mode: skip cache read, always execute + store. |

### 4. Scheduled Tasks (`#[Crontab]`)

```php
use Vzina\Attributes\Attribute\Crontab;

#[Crontab(rule: '*/5 * * * *', name: 'order-sync')]
class OrderSync
{
    public function handle()
    {
        // Runs every 5 minutes
    }
}
```

Requires `workerman/crontab ^1.0`.

### 5. Event Listeners (`#[Listener]`)

```php
use Vzina\Attributes\Attribute\Listener;

#[Listener(event: 'user.registered', priority: 10)]
class SendWelcomeEmail
{
    public function handle(array $data)
    {
        mail($data['email'], 'Welcome!', '...');
    }
}

// Dispatch anywhere:
Event::dispatch('user.registered', ['email' => 'user@example.com']);
```

### 6. Route Registration

```php
use Vzina\Attributes\Attribute\Route\{Controller, RequestMapping, AutoController, Resource};

#[Controller(prefix: 'order')]
class OrderController
{
    #[RequestMapping(path: 'list', methods: ['GET'], options: ['middleware' => [Auth::class]])]
    public function list() { return json([]); }
}

#[AutoController(prefix: 'api')]
class ApiController
{
    public function status() { return json(['ok' => true]); }
}

#[Resource(prefix: 'users')]
class UserController { /* RESTful: index, show, store, update, destroy */ }
```

### 7. Enums & Constants (`#[Constants]` + `#[Message]`)

```php
use Vzina\Attributes\Attribute\{Constants, ConstantsTrait, Message};

#[Constants]
enum OrderStatus: int
{
    use ConstantsTrait;

    #[Message('Pending')]
    case PENDING = 0;

    #[Message('Completed')]
    case COMPLETED = 1;
}

// Usage:
echo OrderStatus::getMessage(OrderStatus::COMPLETED); // "Completed"
```

### 8. AOP Aspects (`#[Aspect]`)

```php
use Vzina\Attributes\Attribute\{Aspect, AspectInterface};
use Vzina\Attributes\Ast\ProceedingJoinPoint;

#[Aspect]
class LoggingAspect implements AspectInterface
{
    // Classes/methods to intercept (supports * wildcard)
    public array $classes = [
        'App\Service\*::*',
    ];

    // OR: match by attribute presence
    public array $attributes = [];

    // Priority (higher = runs first)
    public ?int $priority = 10;

    public function process(ProceedingJoinPoint $point)
    {
        $start = microtime(true);
        $result = $point->process(); // call next aspect or original method

        logger()->info(sprintf('%s::%s took %.2fms',
            $point->className, $point->methodName,
            (microtime(true) - $start) * 1000
        ));

        return $result;
    }
}
```

### 9. Service Registration (`#[Depend]`)

```php
use Vzina\Attributes\Attribute\Depend;

// 基础用法：自动装配
#[Depend(id: LoggerInterface::class, priority: 10)]
class FileLogger implements LoggerInterface { }

// 显式传参
#[Depend(id: MailerInterface::class, params: ['host' => 'smtp.example.com', 'port' => 587])]
class SmtpMailer implements MailerInterface
{
    public function __construct(private string $host, private int $port) {}
}

// 单例模式
#[Depend(id: ExpensiveService::class, singleton: true)]
class ExpensiveService
{
    public function __construct() { /* 只初始化一次 */ }
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `id` | `?string` | `null` | Container key. Defaults to the class FQCN. |
| `priority` | `int` | `0` | Registration priority (higher = registered first, wins on duplicate id). |
| `params` | `array` | `[]` | Explicit constructor argument overrides: `['paramName' => 'value']`. |
| `singleton` | `bool` | `false` | Cache the instance across `Container::get()` calls (one per worker). |

**Argument resolution priority:** `params['name']` → container auto-wiring → default value → throws.

### 10. Custom Processes (`#[Process]`)

```php
use Vzina\Attributes\Attribute\Process;

#[Process(name: 'metrics-collector')]
class MetricsCollector
{
    public function onWorkerStart(): void
    {
        // Custom Workerman process
    }
}
```

---

### 11. Database Transactions (`#[Transactional]`)

```php
use Vzina\Attributes\Attribute\Transactional;

class OrderService
{
    #[Transactional(connection: 'default', attempts: 3)]
    public function placeOrder(array $data): Order
    {
        $order = Order::create($data);
        $order->items()->createMany($data['items']);
        return $order;
    }
}
```

正常返回 commit，异常 rollback。`attempts > 1` 时自动重试死锁（MySQL 1213 / PostgreSQL 40P01）。

| Parameter | Type | Default | Description |
|---|---|---|---|
| `connection` | `string` | `'default'` | 数据库连接名 |
| `attempts` | `int` | `1` | 死锁重试次数 |
| `transactionHandler` | `mixed` | `null` | 自定义事务处理器 callable |

### 12. Method Logging (`#[Log]`)

```php
use Vzina\Attributes\Attribute\Log;

class PaymentService
{
    #[Log(level: 'info', channel: 'payment', logArgs: true)]
    public function charge(int $userId, float $amount): Receipt
    {
        // [vzina] PaymentService::charge called
        // [vzina] PaymentService::charge completed in 12.34ms
        return new Receipt(...);
    }
}
```

支持消息模板：`#[Log(message: 'Processing #{orderId}')]`。

| Parameter | Type | Default | Description |
|---|---|---|---|
| `level` | `string` | `'info'` | 日志级别 |
| `channel` | `?string` | `null` | 日志通道 |
| `message` | `?string` | `null` | 消息模板，`#{params.key}` 插值 |
| `logArgs` | `bool` | `false` | 上下文包含入参 |
| `logResult` | `bool` | `false` | 上下文包含返回值 |

### 13. Request Validation (`#[Validate]`)

```php
use Vzina\Attributes\Attribute\Validate;

#[Validate(rules: [
    'name'  => 'required|min:3',
    'email' => 'required|email',
])]
public function store(Request $request): Response
{
    // 校验通过才执行，失败抛 ValidateException (HTTP 422)
    return json(User::create($request->all()));
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `rules` | `array` | `[]` | 校验规则 |
| `messages` | `array` | `[]` | 自定义错误消息 |
| `requestParam` | `?string` | `null` | Request 参数名，null=自动发现 |
| `validator` | `mixed` | `null` | 自定义校验器 callable |

### 14. Automatic Retry (`#[Retry]`)

```php
use Vzina\Attributes\Attribute\Retry;

#[Retry(maxAttempts: 3, delayMs: 100, backoff: 2.0, on: [NetworkException::class])]
public function callApi(): array
{
    return $this->http->get('https://api.example.com/data');
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `maxAttempts` | `int` | `3` | 最大尝试次数，硬上限 100 |
| `delayMs` | `int` | `100` | 基础延迟毫秒 |
| `backoff` | `float` | `1.0` | 退避倍率 |
| `on` | `array` | `[]` | 仅重试这些异常，空=全部 |

### 15. Controller Middleware (`#[Middleware]`)

```php
use Vzina\Attributes\Attribute\Middleware;

#[Controller(prefix: '/api')]
#[Middleware(App\Middleware\Auth::class)]
class ApiController
{
    #[GetMapping(path: '/profile')]
    #[Middleware(App\Middleware\RateLimit::class)]
    public function profile(): Response { ... }
}
```

可重复使用。类级作用于全部路由，方法级仅作用于当前。

### 16. Distributed Tracing (`#[Trace]`)

```php
use Vzina\Attributes\Attribute\Trace;

#[Trace(spanName: 'order.checkout')]
public function checkout(int $orderId): Order { ... }
```

W3C Trace Context 标准，通过 `support\Context` 传播 traceId/spanId。

| Parameter | Type | Default | Description |
|---|---|---|---|
| `spanName` | `?string` | `null` | span 名称 |
| `tracer` | `mixed` | `null` | 自定义 tracer callable |

### 17. OpenAPI Generator

```bash
php webman attributes:openapi --output=public/openapi.json
```

扫描 `#[Controller]` + 路由注解 → OpenAPI 3.0 JSON。

### 18. CLI Commands (`#[Command]`)

```php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vzina\Attributes\Attribute\Command as CommandAttribute;

#[CommandAttribute(name: 'app:greet', description: 'Say hello to the user')]
class GreetCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello, Webman!');
        return Command::SUCCESS;
    }
}
```

`#[Command]` 标记的类会被自动注册到 webman 命令行，无需手动编辑 `command.php`。

```bash
php webman app:greet
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `name` | `string` | **required** | 命令名称 |
| `description` | `?string` | `null` | 命令描述 |

---

## Cache & Performance

### Cache Directory Structure

```
runtime/attributes/
├── scan.cache.php                          # Scan completion marker
├── classmap.cache.php                      # className => proxyFilePath
├── classes.cache.php                       # Tracked class list (for removal detection)
├── aspects.cache                           # Aspect change tracking
├── Vzina_Attributes_Collector_AttributeCollector.cache.php
├── Vzina_Attributes_Collector_AspectCollector.cache.php
├── Vzina_Attributes_Collector_ConstantsCollector.cache.php
└── proxy/
    ├── app_controller_ChatController.proxy.php    # AOP proxy
    └── LazyProxy_app_service_Bar.proxy.php        # Lazy proxy
```

### Clearing Cache

```bash
rm -rf runtime/attributes/
php start.php restart -d
```

First boot after cache clear triggers a full scan (child process), second boot loads from cache.

---

## Testing

```bash
php82 vendor/bin/phpunit test/

# By module
php82 vendor/bin/phpunit test/Attribute/   # 291 tests, 620 assertions
php82 vendor/bin/phpunit test/Collector/
php82 vendor/bin/phpunit test/Ast/
php82 vendor/bin/phpunit test/Scan/
php82 vendor/bin/phpunit test/Reflection/
php82 vendor/bin/phpunit test/OpenApi/
```

---

## Troubleshooting

### All functionality stopped working
Check `config/plugin/vzina/attributes/bootstrap.php` registers `AttributeBootstrap::class`, not individual handlers.

### `Call to a member function on null` with `#[Inject(lazy: true)]`
1. Delete `runtime/attributes/` cache
2. Restart webman — first boot generates proxies, second boot applies them
3. Verify `runtime/attributes/proxy/` contains `LazyProxy_*.proxy.php` files

### AOP proxy not taking effect
Same as above: clear cache, restart twice. The child-process scan isolation requires two boots for proxies to replace originals.

### `pcntl_fork() has been disabled`
Auto-falls back to `DirectHandler`. Scanning runs in-process; proxies activate after next restart.

### Routes not registered
Ensure `config/plugin/vzina/attributes/route.php` contains `DispatcherFactory::init()` and `config/plugin/vzina/attributes/app.php` has `enable => true`.
