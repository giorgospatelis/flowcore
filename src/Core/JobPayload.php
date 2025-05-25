<?php

declare(strict_types=1);

namespace FlowCore\Core;

use FlowCore\Contracts\JobPayloadInterface;
use InvalidArgumentException;
use JsonException;

class JobPayload implements JobPayloadInterface
{
    private string $id = '';
    private array $data;
    private array $options;
    private int $attempts = 0;

    public function __construct(array $data = [], array $options = [])
    {
        $this->validateData($data);
        $this->validateOptions($options);

        $this->data = $data;
        $this->options = $options;
    }

    public static function unserialize(string $data): self
    {
        if ($data === '' || $data === '0') {
            throw new InvalidArgumentException('Cannot unserialize empty data');
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON data provided: ' . $e->getMessage(), 0, $e);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Serialized data must be a JSON object');
        }

        $requiredFields = ['id', 'data', 'options', 'attempts'];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $decoded)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
        }

        if (!is_string($decoded['id'])) {
            throw new InvalidArgumentException('Job ID must be a string');
        }

        if (!is_array($decoded['data'])) {
            throw new InvalidArgumentException('Job data must be an array');
        }

        if (!is_array($decoded['options'])) {
            throw new InvalidArgumentException('Job options must be an array');
        }

        if (!is_int($decoded['attempts']) || $decoded['attempts'] < 0) {
            throw new InvalidArgumentException('Job attempts must be a non-negative integer');
        }

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

    public function setAttempts(int $attempts): self
    {
        if ($attempts < 0) {
            throw new InvalidArgumentException('Attempts must be a non-negative integer');
        }

        $this->attempts = $attempts;

        return $this;
    }

    public function setId(string $id): self
    {
        if ($id === '' || $id === '0') {
            throw new InvalidArgumentException('Job ID cannot be empty');
        }

        $this->id = $id;

        return $this;
    }

    public function serialize(): string
    {
        try {
            return json_encode([
                'id' => $this->id,
                'data' => $this->data,
                'options' => $this->options,
                'attempts' => $this->attempts,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to serialize job payload: ' . $e->getMessage(), 0, $e);
        }
    }

    public function incrementAttempts(): self
    {
        $this->attempts++;

        return $this;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    public function withOption(string $key, mixed $value): self
    {
        $new = clone $this;
        $new->options[$key] = $value;

        return $new;
    }

    public function withData(array $data): self
    {
        $this->validateData($data);

        $new = clone $this;
        $new->data = $data;

        return $new;
    }

    private function validateData(array $data): void
    {
        try {
            json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Job data must be JSON serializable: ' . $e->getMessage(), 0, $e);
        }
    }

    private function validateOptions(array $options): void
    {
        try {
            json_encode($options, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Job options must be JSON serializable: ' . $e->getMessage(), 0, $e);
        }

        // Validate specific option types if they exist
        if (isset($options['priority']) && (!is_int($options['priority']) || $options['priority'] < 0)) {
            throw new InvalidArgumentException('Priority must be a non-negative integer');
        }

        if (isset($options['delay']) && (!is_int($options['delay']) || $options['delay'] < 0)) {
            throw new InvalidArgumentException('Delay must be a non-negative integer');
        }

        if (isset($options['max_attempts']) && (!is_int($options['max_attempts']) || $options['max_attempts'] < 1)) {
            throw new InvalidArgumentException('Max attempts must be a positive integer');
        }
    }
}
