<?php

declare(strict_types=1);

namespace Multitron\Comms\Server\Storage;

final class CentralMultiReadRequest extends CentralReadRequest
{
    /**
     * @var CentralReadRequest[]
     */
    private array $requests;

    public function __construct()
    {
        $this->requests = [];
    }

    public function with(string $key, CentralReadRequest $request): self
    {
        $this->requests[$key] = $request;
        return $this;
    }

    public function &read(array &$cache): array
    {
        $result = [];
        foreach ($this->requests as $key => $request) {
            $result[$key] = &$request->read($cache);
        }
        return $result;
    }
}
