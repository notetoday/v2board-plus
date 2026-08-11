<?php

namespace App\Services\ThirdParty;

/**
 * A temporary third-party node.
 *
 * This class is a pure in-memory value object. It intentionally does NOT
 * extend a database Model, does not implement Eloquent persistence and never
 * touches the database. Third-party nodes must never be written to any
 * persistent node table.
 */
final class TemporaryNode
{
    public string $name = '';
    public string $type = '';
    public ?string $server = null;
    public ?int $port = null;
    public array $settings = [];
    public array $metadata = [];

    public function __construct(string $type, string $name, ?string $server, ?int $port, array $settings = [], array $metadata = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->server = $server;
        $this->port = $port;
        $this->settings = $settings;
        $this->metadata = $metadata;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'server' => $this->server,
            'port' => $this->port,
            'settings' => $this->settings,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['type'] ?? ''),
            (string)($data['name'] ?? ''),
            isset($data['server']) ? (string)$data['server'] : null,
            isset($data['port']) ? (int)$data['port'] : null,
            (array)($data['settings'] ?? []),
            (array)($data['metadata'] ?? [])
        );
    }
}
