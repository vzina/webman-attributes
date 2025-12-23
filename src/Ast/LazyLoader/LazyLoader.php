<?php
/**
 * LazyLoader.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

use Illuminate\Support\Str;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use Vzina\Attributes\Ast\AstParser;
use Workerman\Coroutine\Locker;

class LazyLoader
{
    protected const LAZY_NS = 'InjectLazy\\';
    protected bool $registered = false;
    protected static ?self $instance = null;

    private function __construct(
        protected string $proxyPath,
        protected array $config
    ) {
        $this->register();
    }

    public static function bootstrap(string $proxyPath, array $config = []): LazyLoader
    {
        return static::$instance ??= new static($proxyPath, $config);
    }

    public static function fmt(string $className): string
    {
        return self::LAZY_NS . $className;
    }

    public function load(string $proxy)
    {
        if (array_key_exists($proxy, $this->config) || str_starts_with($proxy, self::LAZY_NS)) {
            $this->loadProxy($proxy);
            return true;
        }
        return null;
    }

    protected function register(): void
    {
        if (! $this->registered) {
            $this->prependToLoaderStack();
            $this->registered = true;
        }
    }

    protected function loadProxy(string $proxy)
    {
        require_once $this->ensureProxyExists($proxy);
    }

    protected function ensureProxyExists(string $proxy): string
    {
        $code = $this->generatorLazyProxy(
            $proxy,
            $this->config[$proxy] ?? Str::after($proxy, self::LAZY_NS)
        );

        $path = str_replace('\\', '_', $this->proxyPath . '/' . $proxy . '_' . crc32($code) . '.php');
        $key = md5($path);
        // If the proxy file does not exist, then try to acquire the coroutine lock.
        if (! file_exists($path) && Locker::lock($key)) {
            $targetPath = $path . '.' . uniqid();
            file_put_contents($targetPath, $code);
            rename($targetPath, $path);
            Locker::unlock($key);
        }
        return $path;
    }

    protected function generatorLazyProxy(string $proxy, string $target): string
    {
        $targetReflection = new ReflectionClass($target);
        if ($this->isUnsupportedReflectionType($targetReflection)) {
            $builder = new FallbackLazyProxyBuilder();
            return $this->buildNewCode($builder, $proxy, $targetReflection);
        }
        if ($targetReflection->isInterface()) {
            $builder = new InterfaceLazyProxyBuilder();
            return $this->buildNewCode($builder, $proxy, $targetReflection);
        }
        $builder = new ClassLazyProxyBuilder();
        return $this->buildNewCode($builder, $proxy, $targetReflection);
    }

    protected function prependToLoaderStack(): void
    {
        $load = [$this, 'load'];
        spl_autoload_register($load, true, true);
    }

    private function isUnsupportedReflectionType(ReflectionClass $targetReflection): bool
    {
        return $targetReflection->isFinal();
    }

    private function buildNewCode(AbstractLazyProxyBuilder $builder, string $proxy, ReflectionClass $reflectionClass): string
    {
        $astParser = AstParser::getInstance();
        $target = $reflectionClass->getName();
        $nodes = $astParser->getNodesFromReflectionClass($reflectionClass);
        $builder->addClassBoilerplate($proxy, $target);
        $builder->addClassRelationship();
        $traverser = new NodeTraverser();
        $methods = $astParser->getAllMethodsFromStmts($nodes);
        $visitor = new PublicMethodVisitor($methods, $builder->getOriginalClassName());
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
        $builder->addNodes($visitor->nodes);

        return (new Standard())->prettyPrintFile([$builder->getNode()]);
    }
}