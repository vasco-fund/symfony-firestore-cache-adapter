<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Vasco\FirestoreCache\Client\DocumentSnapshotInterface;

/**
 * Response based implementation of DocumentSnapshotInterface.
 */
final class LazyHttpDocumentSnapshot implements DocumentSnapshotInterface
{
    use HttpDocumentTrait;

    private bool $responseParsed = false;

    /**
     * @var mixed[]|null
     */
    private ?array $data;

    public function __construct(
        private string $id,
        private ResponseInterface $response,
    ) {
    }

    public function exists(): bool
    {
        return $this->response->getStatusCode() !== 404;
    }

    public function getData(): ?array
    {
        if ($this->responseParsed) {
            return $this->data;
        }

        $content = $this->response->toArray();

        if ($content === []) {
            $this->data = null;

            return null;
        }

        $fields = $content['fields'] ?? [];
        if (!\is_array($fields)) {
            $this->data = null;

            return null;
        }

        $this->data = self::parseFields($fields);

        return $this->data;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
