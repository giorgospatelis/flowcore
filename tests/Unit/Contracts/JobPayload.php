<?php
declare(strict_types=1);
namespace FlowCore\Contracts;

use FlowCore\Contracts\JobPayloadInterface;

class JobPayload implements JobPayloadInterface
{
    private string $id = '';
    private array $data;
    private array $options;
    private int $attempts = 0;

    public function __construct(array $data = [], array $options = [])
    {
        $this->data = $data;
        $this->options = $options;
    }

    public static function unserialize(string $data): self
    {
        $decoded = json_decode($data, true);
        $instance = new self($decoded['data'], $decoded['options']);
        $instance->setId($decoded['id']);
        $instance->attempts = $decoded['attempts'];
        return $instance;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function serialize(): string
    {
        return json_encode([
            'id' => $this->id,
            'data' => $this->data,
            'options' => $this->options,
            'attempts' => $this->attempts,
        ]);
    }
}
