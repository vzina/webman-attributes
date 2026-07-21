<?php
/**
 * AttributeListCommand — 属性调试命令。
 *
 * 列出指定类或全部已扫描类的注解信息。
 *
 *   php webman attributes:list                          # 列出所有
 *   php webman attributes:list --class=App\Controller\UserController  # 指定类
 *   php webman attributes:list --type=Inject             # 按类型过滤
 */
declare (strict_types=1);

namespace Vzina\Attributes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vzina\Attributes\Collector\AttributeCollector;

class AttributeListCommand extends Command
{
    protected static $defaultName = 'attributes:list';
    protected static $defaultDescription = '列出已扫描的类及其注解信息';

    protected function configure(): void
    {
        $this
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED, '过滤指定类名（支持短名匹配）')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, '过滤指定注解类型（类名简短匹配）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $classFilter = $input->getOption('class');
        $typeFilter  = $input->getOption('type');
        $all = AttributeCollector::list();

        if (empty($all)) {
            $output->writeln('<comment>未找到任何已扫描的类。请确保 attributes 缓存已生成。</comment>');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($all as $className => $meta) {
            if ($classFilter !== null && !str_contains($className, $classFilter)) {
                continue;
            }

            $classAttrs = implode(', ', array_keys($meta['_c'] ?? []));
            $methodAttrs = [];
            foreach ($meta['_m'] ?? [] as $method => $attrs) {
                $methodAttrs[$method] = implode(', ', array_keys($attrs));
            }
            $propAttrs = [];
            foreach ($meta['_p'] ?? [] as $prop => $attrs) {
                $propAttrs[$prop] = implode(', ', array_keys($attrs));
            }

            if ($typeFilter !== null) {
                $matched = str_contains($classAttrs, $typeFilter)
                    || !empty(array_filter($methodAttrs, fn($v) => str_contains($v, $typeFilter)))
                    || !empty(array_filter($propAttrs, fn($v) => str_contains($v, $typeFilter)));
                if (!$matched) continue;
            }

            $rows[] = [$className, $classAttrs ?: '-'];

            foreach ($methodAttrs as $method => $mAttrs) {
                $rows[] = ["  ↳ {$method}()", $mAttrs ?: '-'];
            }
            foreach ($propAttrs as $prop => $pAttrs) {
                $rows[] = ["  \${$prop}", $pAttrs ?: '-'];
            }
        }

        if (empty($rows)) {
            $output->writeln('<comment>无匹配结果。</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['类 / 成员', '注解']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $output->writeln('<info>共 ' . count($all) . ' 个类已扫描，当前过滤显示 ' . count($rows) . ' 行。</info>');

        return Command::SUCCESS;
    }
}