<?php

namespace Pebble\Swagger;

class DocCollection implements \Countable
{
    /**
     * @var DocEntity[]
     */
    protected $data = [];

    /**
     * @param string $doc
     * @return \static
     */
    public static function create(string $doc)
    {
        $doc = str_replace("\r", "", $doc);

        $docs = new static();

        if (!$doc || false === mb_strpos($doc, 'oa-')) {
            return $docs;
        }

        foreach (explode("\n", $doc) as $line) {
            if (false !== ($pos = mb_strpos($line, 'oa-'))) {
                $line = trim(mb_substr($line, $pos));
                $line = array_filter(explode(' ', $line));
                $name = array_shift($line);
                $docs->add(new DocEntity($name, $line));
            }
        }

        return $docs;
    }

    /**
     * @param DocEntity $doc
     * @return \static
     */
    public function add(DocEntity $doc)
    {
        $this->data[] = $doc;
        return $this;
    }

    public function has(string $name): bool
    {
        foreach ($this->data as $doc) {
            if ($doc->name === $name) return true;
        }

        return false;
    }

    /**
     * @param string $name
     * @return DocEntity|null
     */
    public function one(string $name)
    {
        foreach ($this->data as $doc) {
            if ($doc->name === $name) return $doc;
        }

        return null;
    }

    /**
     * @param string $name
     * @return DocEntity[]
     */
    public function all(string $name)
    {
        $data = [];
        foreach ($this->data as $doc) {
            if ($doc->name === $name) $data[] = $doc;
        }

        return $data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function parseMultiline(string $name, $sep = "\n")
    {
        $text = '';
        foreach ($this->all($name) as $doc) {
            $text .= $sep . $doc->text();
        }

        return $text ? mb_substr($text, mb_strlen($sep)) : '';
    }


    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function parseType(string $type, array $schema = []): array
    {
        if ($type) {
            $map = explode(':', $type);
            $type = $map[0];
            $opt = $map[1] ?? null;
            $schema['type'] = $type;
            if ($type === 'array') {
                $schema['items']['type'] = $opt ?? 'string';
                $schema['items'] = self::parseFormat($map[2] ?? '', $schema['items']);
            } elseif ($opt) {
                $schema = self::parseFormat($opt, $schema);
            }
        }

        return $schema;
    }

    private static function parseFormat(string $format, array $schema)
    {
        if (!$format) {
            return $schema;
        }

        if ($format[0] === '/') {
            $schema['pattern'] = $format;
        } else {
            $schema['format'] = $format;
        }

        return $schema;
    }

    // -------------------------------------------------------------------------
}
