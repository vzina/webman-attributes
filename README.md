# vzina/attributes

Lightweight PHP 8.1+ attribute-driven AOP & DI toolkit for the [Webman](https://github.com/walkor/webman) framework.

- **Zero boilerplate** — `#[Inject]`, `#[Cacheable]`, `#[Listener]`, `#[Crontab]` and more with a single attribute
- **AOP via AST** — method-level proxy generation using nikic/php-parser, transparent to business code
- **Lazy injection** — auto-generated proxy classes defer service resolution until first access
- **OPcache-friendly caching** — PHP-native `var_export`/`include` cache format, no `unserialize` overhead
- **Multi-process safe** — per-worker scanning with `pcntl_fork` isolation (30s timeout guard)
- **17 built-in annotations** — DI, AOP, cache, cron, events, routes, validation, transactions, retry, tracing, middleware, CLI commands

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

**PHP cache files** — All collector data is serialized via `var_export()` into `.cache.php` files. Safe `include`-in-closure reading bypasses OPcache file caching without `eval()` security risks.

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
| `singleton` | `bool` | `true` | 容器单例，true 时 `Container::get()` 缓存实例 |

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

### 12. Request Validation (`#[Validate]`)

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
| `requestParam` | `?string` | `null` | Request 参数名，null=自动发现（含子类） |

**自定义校验器：** 在容器中绑定 `ValidatorContract` 即可替换默认实现：
```php
// dependence.php
return [
    ValidatorContract::class => App\Validate\CustomValidator::class,
];
```

### 13. Automatic Retry (`#[Retry]`)

```php
use Vzina\Attributes\Attribute\Retry;

#[Retry(maxAttempts: 3, delayMs: 100, backoff: 2.0, on: [NetworkException::class])]
public function callApi(): array
{
    return $this->http->get('https://api.example.com/data');
}
```

指数退避 + 随机抖动，单次延迟自动封顶 60s 防溢出。

| Parameter | Type | Default | Description |
|---|---|---|---|
| `maxAttempts` | `int` | `3` | 最大尝试次数，硬上限 100 |
| `delayMs` | `int` | `100` | 基础延迟毫秒 |
| `backoff` | `float` | `1.0` | 退避倍率 |
| `on` | `array` | `[]` | 仅重试这些异常，空=全部 |

### 14. Controller Middleware (`#[Middleware]`)

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

### 15. Distributed Tracing (`#[Trace]`)

符合 W3C Trace Context Level 2 规范，traceId: 32 hex (16 bytes)，spanId: 16 hex (8 bytes)。

```php
use Vzina\Attributes\Attribute\Trace;

#[Trace(spanName: 'order.checkout')]
public function checkout(int $orderId): Order
{
    // 在 span 内写入自定义属性
    Span::setAttribute('order_id', $orderId);
    Span::setAttribute('amount', 99.9);

    return $this->service->checkout($orderId);
}
```

**跨进程传播（traceparent）：**

```php
// 1. 从上游 HTTP 请求恢复追踪上下文
Span::applyTraceparent($request->header('traceparent'));

// 2. 在 #[Trace] 包裹的方法内正常执行

// 3. 向下游发起请求时，传播 traceparent
$response = $http->get($url, ['traceparent' => Span::getTraceparent()]);
```

**自定义 Tracer（替换默认 W3C 实现）：**

在 `dependence.php` 中绑定 `TracerContract`：
```php
return [
    TracerContract::class => App\Trace\JaegerTracer::class,
];
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `spanName` | `?string` | `null` | span 名称，null 时自动 `ClassName::methodName` |

### 16. OpenAPI Generator + Swagger UI

```bash
php webman attributes:openapi --output=public/openapi.json
```

启动后访问 `http://localhost:8787/openapi` 即可使用交互式 Swagger UI。

生产环境可关闭路由，改用命令行导出：
```bash
# 生产关闭路由，仅导出静态文件
php webman attributes:openapi --output=public/openapi.json
```

**配置开关**（`config/plugin/vzina/attributes/app.php`）：
```php
'openapi' => [
    'enable'  => true,                    // 注册 /openapi 路由
    'title'   => 'API Documentation',     // 文档标题
    'version' => '1.0.0',                 // API 版本
],
```

扫描 `#[Controller]` + 路由注解 → OpenAPI 3.0 JSON。

### 17. CLI Commands (`#[Command]`)

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
php82 vendor/bin/phpunit test/Attribute/   # 308 tests, 672 assertions
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

### Tracing not propagating across services
1. Ensure upstream service sends `traceparent` header
2. In middleware, call `Span::applyTraceparent($request->header('traceparent'))` before business logic
3. Use `Span::getTraceparent()` when making downstream HTTP requests
