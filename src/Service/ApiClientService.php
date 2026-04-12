<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ApiClientService
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $appBaseUrl,
    ) {
        $this->baseUrl = rtrim($appBaseUrl, '/') . '/api';
    }

    // ===== ARTICLES =====

    public function getArticles(array $queryParams = []): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/articles', [
            'query' => $queryParams,
        ]);

        return $this->parseCollection($response);
    }

    public function getArticle(int $id): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/articles/' . $id);
            return $response->toArray();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createArticle(array $data): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/articles', [
            'json' => $data,
        ]);

        return $response->toArray();
    }

    public function updateArticle(int $id, array $data): array
    {
        $response = $this->httpClient->request('PATCH', $this->baseUrl . '/articles/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode($data),
        ]);

        return $response->toArray();
    }

    public function deleteArticle(int $id): void
    {
        $this->httpClient->request('DELETE', $this->baseUrl . '/articles/' . $id);
    }

    public function getArticlesByUser(int $userId): array
    {
        return $this->getArticles([
            'user' => $this->userIri($userId),
            'order[createdAt]' => 'DESC',
        ]);
    }

    // ===== USERS =====

    public function getUsers(array $queryParams = []): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/users', [
            'query' => $queryParams,
        ]);

        return $this->parseCollection($response);
    }

    public function getUser(int $id): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/users/' . $id);
            return $response->toArray();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createUser(array $data): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/users', [
            'json' => $data,
        ]);

        return $response->toArray();
    }

    public function updateUser(int $id, array $data): array
    {
        $response = $this->httpClient->request('PATCH', $this->baseUrl . '/users/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode($data),
        ]);

        return $response->toArray();
    }

    public function deleteUser(int $id): void
    {
        $this->httpClient->request('DELETE', $this->baseUrl . '/users/' . $id);
    }

    // ===== HELPERS =====

    public function userIri(int $userId): string
    {
        return '/api/users/' . $userId;
    }

    private function parseCollection(ResponseInterface $response): array
    {
        $data = $response->toArray();

        // API Platform 4 (JSON-LD simplifié)
        if (isset($data['member'])) {
            return $data['member'];
        }

        // API Platform 3 (JSON-LD avec Hydra)
        if (isset($data['hydra:member'])) {
            return $data['hydra:member'];
        }

        // Tableau JSON simple
        return is_array($data) ? $data : [];
    }
}
