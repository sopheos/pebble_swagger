<?php

namespace Pebble\Swagger;

class Parser
{
    private ApiParser $apiParser;
    private SchemaParsers $schemaParsers;
    private array $json = [];

    public function __construct(string $path)
    {
        $this->schemaParsers = new SchemaParsers();
        $this->apiParser = new ApiParser($path, $this->schemaParsers);

        $this->json['openapi'] = "3.0.0";
        $this->json['info'] = [
            'title' => 'Title',
            'description' => 'Description',
            'version' => '0.0.1',
        ];
        $this->json['servers'] = [
            ['url' => 'http://127.0.0.1']
        ];
        $this->json['components'] = [];

        $this->jwtTokens(true, 'accessToken');
    }

    /**
     * @param array $config
     * @return \static
     */
    public static function create(string $path)
    {
        return new static($path);
    }

    /**
     * @param string $value
     * @return \static
     */
    public function title(string $title)
    {
        $this->json['info']['title'] = $title;
        return $this;
    }

    /**
     * @param string $value
     * @return \static
     */
    public function description(string $value)
    {
        $this->json['info']['description'] = $value;
        return $this;
    }

    /**
     * @param string $value
     * @return \static
     */
    public function version(string $value)
    {
        $this->json['info']['version'] = $value;
        return $this;
    }

    /**
     * @return \static
     */
    public function servers(...$values)
    {
        $this->json['servers'] = [];
        foreach ($values as $value) {
            $this->json['servers'][] = ['url' => $value];
        }
        return $this;
    }

    public function jwtTokens(bool $global = true, ...$names): static
    {
        $this->json['security'] = null;
        $this->json['components']['securitySchemes'] = null;
        unset($this->json['security']);
        unset($this->json['components']['securitySchemes']);

        foreach ($names as $name) {
            $this->json['components']['securitySchemes'][$name] = [
                "type" => "http",
                "scheme" => "bearer",
                "bearerFormat" => "JWT"
            ];
        }

        if ($global && count($names) === 1) {
            $this->json['security'] = [
                [$names[0] => []]
            ];
        }

        return $this;
    }

    public function parser(string $namespace): static
    {
        $this->schemaParsers->add(new SchemaParser($namespace));
        return $this;
    }

    public function run(): array
    {
        $json = $this->json;
        $json['paths'] = $this->apiParser->run();
        if (($schemas = $this->schemaParsers->cache())) {
            $json['components']['schemas'] = $schemas;
        }

        return $json;
    }
}
