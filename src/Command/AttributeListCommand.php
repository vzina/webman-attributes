<?php
/**
 * AttributeListCommand — 属性调试命令。
 *
 * 列出已扫描类的注解信息，支持过滤、详细模式、JSON 输出。
 *
 *   php webman attributes:list                                         # 全部
 *   php webman attributes:list -c UserController                       # 指定类
 *   php webman attributes:list -t Inject                               # 按注解类型
 *   php webman attributes:list -c UserController -v                    # 详细属性值
 *   php webman attributes:list --format=json                           # JSON 输出
 *   php webman attributes:list --count                                 # 仅计数
 */
declare (strict_types=1);

namespace Vzina\Attributes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
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
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED, '过滤指定类名（支持部分匹配）')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, '过滤指定注解类型')
            ->addOption('method', 'm', InputOption::VALUE_REQUIRED, '过滤指定方法名')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, '输出格式：table（默认）| json')
            ->addOption('count', null, InputOption::VALUE_NONE, '仅显示每个类的注解计数');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $classFilter  = $input->getOption('class');
        $typeFilter   = $input->getOption('type');
        $methodFilter = $input->getOption('method');
        $format       = $input->getOption('format') ?? 'table';
        $countOnly    = $input->getOption('count');
        $verbose      = $output->isVerbose();
        $all          = AttributeCollector::list();

        if (empty($all)) {
            $output->writeln('<comment>未找到任何已扫描的类。请确保 attributes 缓存已生成。</comment>');
            return Command::SUCCESS;
        }

        $result = $this->buildResult($all, $classFilter, $typeFilter, $methodFilter);

        if ($format === 'json') {
            return $this->renderJson($output, $result);
        }

        if ($countOnly) {
            return $this->renderCount($output, $result);
        }

        return $this->renderTable($output, $result, $verbose, count($all));
    }

    /** ── 构建过滤后的结果 ── */
    private function buildResult(array $all, ?string $classFilter, ?string $typeFilter, ?string $methodFilter): array
    {
        $result = [];
        foreach ($all as $className => $meta) {
            if ($classFilter !== null && !str_contains($className, $classFilter)) {
                continue;
            }

            $entry = [
                'class'    => $className,
                'class_attrs'  => [],
                'methods'  => [],
                'properties' => [],
                'constants' => [],
            ];

            // 类级注解（仅当未按方法过滤时才收集）
            if ($methodFilter === null) {
                foreach ($meta['_c'] ?? [] as $attrName => $attr) {
                    if ($typeFilter !== null && !str_contains($attrName, $typeFilter)) continue;
                    $entry['class_attrs'][$attrName] = $attr;
                }
            }

            // 方法级注解
            foreach ($meta['_m'] ?? [] as $method => $attrs) {
                if ($methodFilter !== null && !str_contains($method, $methodFilter)) continue;
                foreach ($attrs as $attrName => $attr) {
                    if ($typeFilter !== null && !str_contains($attrName, $typeFilter)) continue;
                    $entry['methods'][$method][$attrName] = $attr;
                }
            }

            // 属性级注解（仅当未按方法过滤时才收集）
            if ($methodFilter === null) {
                foreach ($meta['_p'] ?? [] as $prop => $attrs) {
                    foreach ($attrs as $attrName => $attr) {
                        if ($typeFilter !== null && !str_contains($attrName, $typeFilter)) continue;
                        $entry['properties'][$prop][$attrName] = $attr;
                    }
                }
            }

            // 常量级注解（仅当未按方法过滤时才收集）
            if ($methodFilter === null) {
                foreach ($meta['_cc'] ?? [] as $const => $attrs) {
                    foreach ($attrs as $attrName => $attr) {
                        if ($typeFilter !== null && !str_contains($attrName, $typeFilter)) continue;
                        $entry['constants'][$const][$attrName] = $attr;
                    }
                }
            }

            // 检查是否有匹配内容
            $hasContent = !empty($entry['class_attrs'])
                || !empty($entry['methods'])
                || !empty($entry['properties'])
                || !empty($entry['constants']);

            if ($typeFilter !== null && !$hasContent) continue;
            if ($methodFilter !== null && empty($entry['methods'])) continue;

            $result[] = $entry;
        }

        return $result;
    }

    /** ── Table 输出 ── */
    private function renderTable(OutputInterface $output, array $result, bool $verbose, int $totalCount): int
    {
        $rows = [];
        foreach ($result as $entry) {
            $classAttrsStr = $this->formatAttrs($entry['class_attrs'], $verbose);
            $rows[] = ["<info>{$entry['class']}</info>", $classAttrsStr ?: '-'];

            foreach ($entry['properties'] as $prop => $attrs) {
                $rows[] = ["  <comment>\${$prop}</comment>", $this->formatAttrs($attrs, $verbose)];
            }
            foreach ($entry['constants'] as $const => $attrs) {
                $rows[] = ["  <comment>{$const}</comment>", $this->formatAttrs($attrs, $verbose)];
            }
            foreach ($entry['methods'] as $method => $attrs) {
                $rows[] = ["  <comment>{$method}()</comment>", $this->formatAttrs($attrs, $verbose)];
            }

            $rows[] = new TableSeparator();
        }
        array_pop($rows); // 移除最后的 separator

        if (empty($rows)) {
            $output->writeln('<comment>无匹配结果。</comment>');
            return Command::SUCCESS;
        }

        $header = $verbose ? ['类 / 成员', '注解（参数）'] : ['类 / 成员', '注解'];
        $table = new Table($output);
        $table->setHeaders($header);
        $table->setRows($rows);
        $table->render();

        $classCount = count($result);
        $output->writeln('');
        $output->writeln("<info>共 {$totalCount} 个类已扫描，匹配 {$classCount} 个类。</info>");

        return Command::SUCCESS;
    }

    /** ── JSON 输出 ── */
    private function renderJson(OutputInterface $output, array $result): int
    {
        $data = [];
        foreach ($result as $entry) {
            $item = ['class' => $entry['class']];

            if ($entry['class_attrs']) {
                $item['class_attributes'] = $this->toArray($entry['class_attrs']);
            }
            foreach ($entry['methods'] as $method => $attrs) {
                $item['methods'][$method] = $this->toArray($attrs);
            }
            foreach ($entry['properties'] as $prop => $attrs) {
                $item['properties'][$prop] = $this->toArray($attrs);
            }
            foreach ($entry['constants'] as $const => $attrs) {
                $item['constants'][$const] = $this->toArray($attrs);
            }

            $data[] = $item;
        }

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return Command::SUCCESS;
    }

    /** ── 计数模式 ── */
    private function renderCount(OutputInterface $output, array $result): int
    {
        $table = new Table($output);
        $table->setHeaders(['类', '类注解数', '方法注解数', '属性注解数']);
        foreach ($result as $entry) {
            $table->addRow([
                $entry['class'],
                count($entry['class_attrs']),
                count($entry['methods']),
                count($entry['properties']),
            ]);
        }
        $table->render();

        $output->writeln('');
        $output->writeln('<info>共 ' . count($result) . ' 个类。</info>');
        return Command::SUCCESS;
    }

    /** ── Helpers ── */

    /** 格式化注解列表：verbose 模式展示参数值 */
    private function formatAttrs(array $attrs, bool $verbose): string
    {
        if (empty($attrs)) return '';

        $parts = [];
        foreach ($attrs as $name => $attr) {
            $shortName = substr($name, strrpos($name, '\\') + 1);
            if ($verbose && is_object($attr) && method_exists($attr, 'toArray')) {
                $params = $attr->toArray();
                // 过滤掉 null 和空数组，保留有意义的值
                $params = array_filter($params, fn($v) => $v !== null && $v !== [] && $v !== '');
                if ($params) {
                    $paramStr = json_encode($params, JSON_UNESCAPED_UNICODE);
                    $paramStr = strlen($paramStr) > 60 ? substr($paramStr, 0, 57) . '...' : $paramStr;
                    $parts[] = "{$shortName}<fg=#888> {$paramStr}</>";
                } else {
                    $parts[] = $shortName;
                }
            } else {
                $parts[] = $shortName;
            }
        }

        return implode(', ', $parts);
    }

    /** 属性对象转数组 */
    private function toArray(array $attrs): array
    {
        $result = [];
        foreach ($attrs as $name => $attr) {
            $shortName = substr($name, strrpos($name, '\\') + 1);
            $result[$shortName] = (is_object($attr) && method_exists($attr, 'toArray'))
                ? $attr->toArray()
                : (is_scalar($attr) ? $attr : get_class($attr));
        }
        return $result;
    }
}