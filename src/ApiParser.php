<?php

namespace Pebble\Swagger;

class ApiParser
{
    private string $path;
    private SchemaParsers $parsers;
    private array $json = [];
    private string $classname = '';
    private string $methodname = '';

    public function __construct(string $path, SchemaParsers $parsers)
    {
        $this->path = $path;
        $this->parsers = $parsers;
    }

    public function run(): array
    {
        $this->json = [];
        foreach (self::scanClasses($this->path) as $classname) {
            $this->parseClass($classname);
        }

        ksort($this->json);

        return $this->json;
    }

    public function parseClass(string $classname)
    {
        $this->classname = $classname;
        $reflection = new \ReflectionClass($classname);
        foreach ($reflection->getMethods() as $method) {
            $this->methodname = $method->getName();
            $docs = DocCollection::create($method->getDocComment() ?: '');
            if ($docs->count()) {
                $this->parseApi($docs);
            }
        }
    }

    private function parseApi(DocCollection $docs)
    {
        if (($url = $docs->one('oa-url'))) {
            $url = $url->text();
        }

        if (!$url) {
            $this->error("oa-url", "not found");
        }

        if (($method = $docs->one('oa-method'))) {
            $method = $method->text();
        }

        if (!$method) {
            $this->error("oa-method", "not found");
        }

        if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options'])) {
            $this->error('oa-method', 'format is not valid : ' . $method);
        }

        $this->json[$url][$method] = [];

        if (($tags = $docs->one('oa-tags'))) {
            $this->json[$url][$method]['tags'] = $tags->values();
        }

        $summary = [];

        if (($scope = $docs->one('oa-scope'))) {
            $summary[] = '[' . $scope->text() . ']';
        }

        if (($sum = $docs->one('oa-summary'))) {
            $summary[] = $sum->text();
        }

        if ($summary) {
            $this->json[$url][$method]['summary'] = join(' ', $summary);
        }

        if (($description = $docs->parseMultiline('oa-desc'))) {
            $this->json[$url][$method]['description'] = nl2br($description);
        }

        if ($docs->has('oa-public')) {
            $this->json[$url][$method]['security'] = [];
        } elseif (($jwt = $docs->one('oa-private'))) {
            $this->json[$url][$method]['security'] = [
                [$jwt->text() => []]
            ];
        }

        $required = [];
        foreach ($docs->all('oa-required') as $doc) {
            $required = array_merge($required, $doc->values());
        }

        $parameters = [];

        foreach ($docs->all('oa-path') as $doc) {
            $parameters[] = $this->parseParam('path', $doc, $required);
        }

        $multipart_properties = [];
        $multipart_required = [];
        foreach ($docs->all('oa-data') as $doc) {
            //$this->json[$url][$method]['consumes'] = ['multipart/form-data'];
            list($name, $schema) = $this->parseMultipart($doc);
            if (in_array($name, $required)) {
                $multipart_required[] = $name;
            }
            $multipart_properties[$name] = $schema;
        }

        if ($method === 'get' || $method === 'delete') {
            foreach ($docs->all('oa-query') as $doc) {
                $parameters[] = $this->parseParam('query', $doc, $required);
            }
        } elseif (($form = $docs->one('oa-json'))) {
            $this->json[$url][$method]['requestBody'] = $this->parseForm($form);
        } elseif ($multipart_properties) {
            $schema = [
                'type' => 'object',
                'properties' => $multipart_properties
            ];
            if ($multipart_required) {
                $schema['required'] = $multipart_required;
            }

            $this->json[$url][$method]['requestBody'] = [
                'content' => [
                    'multipart/form-data' => [
                        'schema' => $schema
                    ]
                ]
            ];
        }

        if ($parameters) {
            $this->json[$url][$method]['parameters'] = $parameters;
        }

        $responses = [];

        foreach ($docs->all('oa-code') as $doc) {
            list($code, $data) = $this->parseCode($doc);
            if (!isset($responses[$code])) {
                $responses[$code] = $data;
            } else {
                $responses[$code]['description'] .= '<br>' . $data['description'];
            }
        }

        foreach ($docs->all('oa-res') as $doc) {
            list($code, $data) = $this->parseResult($doc);
            $responses[$code] = $data;
        }

        if ($responses) {
            ksort($responses);
            $this->json[$url][$method]['responses'] = $responses;
        }
    }

    private function parseParam(string $in, DocEntity $doc, array $required = [])
    {
        $name = $name = $doc->value(0);
        $type = $doc->value(1);

        if (!$name || !$type) {
            $this->error("oa-{$in}", "format is not valid");
        }

        $parameter = [
            'in' => $in,
            'name' => $name,
            'schema' => DocCollection::parseType($type)
        ];

        if (in_array($name, $required)) {
            $parameter['required'] = true;
        }

        if (($desc = $doc->text(2))) {
            $parameter['description'] = $desc;
        }

        return $parameter;
    }

    private function parseMultipart(DocEntity $doc)
    {
        $name = $name = $doc->value(0);
        $type = $doc->value(1);

        if (!$name || !$type) {
            $this->error("oa-data", "format is not valid " . $doc->text());
        }

        $schema = DocCollection::parseType($type);
        if (($desc = $doc->text(2))) {
            $schema['description'] = $desc;
        }

        return [$name, $schema];
    }

    private function parseForm(DocEntity $doc)
    {
        if (!($ref = $doc->value(0))) {
            $this->error('oa-json', 'format is not valid');
        }

        if (!$this->parsers->get($ref)) {
            $this->error('oa-json', $ref, 'not found');
        }

        return self::schemaRef($ref, $doc->text(1));
    }

    private function parseCode(DocEntity $doc)
    {
        if (!($code = $doc->value(0))) {
            $this->error("oa-code", "format is not valid");
        }

        return [$code, ['description' => $doc->text(1)]];
    }

    private function parseResult(DocEntity $doc)
    {
        if (!($code = $doc->value(0))) {
            $this->error("oa-res", "format is not valid");
        }

        if (!($ref = $doc->value(1))) {
            $this->error("oa-res", "format is not valid");
        }

        if (($multi = !!preg_match("/\[\]$/", $ref))) {
            $ref = mb_substr($ref, 0, -2);
        }

        if (!$this->parsers->get($ref)) {
            $this->error('oa-res', $ref, 'not found');
        }

        $desc = $doc->text(2);
        $schema = $multi ? self::schemaRefs($ref, $desc) : self::schemaRef($ref, $desc);

        return [$code, $schema];
    }

    private static function schemaRefs(string $name, string $description): array
    {
        $data = [
            'content' => [
                'application/json' => [
                    'schema' => [
                        "type" => "array",
                        "items" => [
                            '$ref' => '#/components/schemas/' . str_replace('/', '_', $name)
                        ]
                    ]
                ]
            ]
        ];

        if ($description) {
            $data['description'] = $description;
        }

        return $data;
    }

    private static function schemaRef(string $name, string $description): array
    {
        $data = [
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/' . str_replace('/', '_', $name)
                    ]
                ]
            ]
        ];

        if ($description) {
            $data['description'] = $description;
        }

        return $data;
    }

    private function error(...$messages)
    {
        throw Exception::create("{$this->classname}:{$this->methodname}", ...$messages);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------


    public static function scanClasses(string $folder): array
    {
        $dir_iterator = new \RecursiveDirectoryIterator($folder);
        $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);

        $classnames = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                if (($classname = self::getClass($file->getPathname()))) {
                    $classnames[] = $classname;
                }
            }
        }

        return $classnames;
    }

    public static function getClass(string $filename)
    {
        $content = file_get_contents($filename);

        $namespace = '';
        $matches = [];
        if (preg_match("/\s*namespace\s*([^;]+)/ui", $content, $matches)) {
            $namespace = "\\" . trim($matches[1], "\\");
        }

        $classname = '';
        $matches = [];
        if (preg_match("/\s*class\s*([^\s]+)/ui", $content, $matches)) {
            $classname = $matches[1];
        }

        $fullname = $namespace . '\\' . $classname;

        return class_exists($fullname) ? $fullname : null;
    }
}
