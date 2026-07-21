<?php
/**
 * OperationBuilder — OpenAPI Operation 对象构建器（Builder 模式）。
 *
 * 替代 Generator::buildOperation() 中的直接数组构造 + 条件嵌套，
 * 提供链式 API 逐步构建 summary / description / parameters / responses / tags。
 */
declare (strict_types=1);

namespace Vzina\Attributes\OpenApi;

use PhpDocReader\PhpDocReader;
use ReflectionMethod;

class OperationBuilder
{
    private string $operationId;
    private string $tag;
    private array $parameters = [];
    private array $responses = ['200' => ['description' => 'Successful response']];
    private ?string $summary = null;
    private ?string $description = null;

    public function __construct(string $className, string $methodName, string $tag)
    {
        $this->operationId = $className . '.' . $methodName;
        $this->tag = $tag;
    }

    /** 设置 summary（注解 > Mapping.options > 无） */
    public function summary(?string $summary): self
    {
        if ($summary !== null) {
            $this->summary = $summary;
        }
        return $this;
    }

    /** 设置 description（注解 > PHPDoc > 无） */
    public function description(?string $description): self
    {
        if ($description !== null) {
            $this->description = $description;
        }
        return $this;
    }

    /** 从反射方法构建参数 */
    public function buildParameters(ReflectionMethod $method, PhpDocReader $reader): self
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            try {
                $type = $reader->getParameterType($param);
            } catch (\Throwable) {
                $type = null;
            }

            if ($type !== null && Generator::isFrameworkType($type)) {
                continue;
            }

            $refType = $param->getType();
            if ($refType instanceof \ReflectionNamedType
                && !$refType->isBuiltin()
                && Generator::isFrameworkType($refType->getName())) {
                continue;
            }

            $schema = $type ? Generator::typeToSchema($type) : ['type' => 'string'];
            $params[] = [
                'name'     => $param->getName(),
                'in'       => 'query',
                'required' => !$param->isOptional(),
                'schema'   => $schema,
            ];
        }

        $this->parameters = array_merge($this->parameters, $params);
        return $this;
    }

    /** 设置返回类型 */
    public function responseSchema(?string $returnType): self
    {
        if ($returnType !== null) {
            $this->responses['200']['content'] = [
                'application/json' => ['schema' => Generator::typeToSchema($returnType)],
            ];
        }
        return $this;
    }

    /** 设置 desc 来自 PHPDoc（仅当未显式设置时） */
    public function descriptionFromPhpDoc(ReflectionMethod $method): self
    {
        if ($this->description !== null) {
            return $this;
        }

        $doc = $method->getDocComment();
        if ($doc && $desc = Generator::parseDocSummary($doc)) {
            $this->description = $desc;
        }
        return $this;
    }

    /** 添加 #[Header] 注解的请求头参数 */
    public function headers(array $headerAttrs): self
    {
        foreach ($headerAttrs as $h) {
            if ($h instanceof \Vzina\Attributes\Attribute\Route\Header) {
                $this->parameters[] = [
                    'name'        => $h->name,
                    'in'          => 'header',
                    'required'    => $h->required,
                    'description' => $h->description,
                    'schema'      => ['type' => 'string'],
                ];
            }
        }
        return $this;
    }

    /** 添加 #[ApiResponse] 注解的自定义响应 */
    public function responseDocs(array $responseAttrs): self
    {
        foreach ($responseAttrs as $r) {
            if ($r instanceof \Vzina\Attributes\Attribute\Route\ApiResponse) {
                $this->responses[(string) $r->statusCode] = [
                    'description' => $r->description,
                ];
            }
        }
        return $this;
    }

    /** 追加额外参数（如 Resource 的 {id} path 参数） */
    public function prependParameters(array $params): self
    {
        $this->parameters = array_merge($params, $this->parameters);
        return $this;
    }

    /** 构建最终的 Operation 数组 */
    public function build(): array
    {
        $op = [
            'operationId' => $this->operationId,
            'tags'        => [$this->tag],
            'responses'   => $this->responses,
        ];

        if ($this->summary !== null) {
            $op['summary'] = $this->summary;
        }
        if ($this->description !== null) {
            $op['description'] = $this->description;
        }
        if ($this->parameters) {
            $op['parameters'] = $this->parameters;
        }

        return $op;
    }
}