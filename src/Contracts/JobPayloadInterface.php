<?php

declare(strict_types=1);

namespace FlowCore\Contracts;

interface JobPayloadInterface
{
    public static function unserialize(string $data): self;

    public function getId(): string;

    public function getData(): array;

    public function getOptions(): array;

    public function getAttempts(): int;

    public function setId(string $id): self;

    public function serialize(): string;
}
