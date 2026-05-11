<?php

namespace FederatedJsonStore\Client;

use FederatedJsonStore\Exception\DataNodeClientException;
use FederatedJsonStore\Exception\NetworkException;
use FederatedJsonStore\Exception\SerializationException;

/**
 * Manages communication with a single federated data node.
 * Provides a robust interface for data retrieval, storage, and deletion.
 */
class DataNodeClient
{
    private string $nodeUrl;
    private array $headers;

    /**
     * DataNodeClient constructor.
     *
     * @param string $nodeUrl The base URL of the data node API.
     * @param array $authHeaders Optional authentication headers.
     */
    public function __construct(string $nodeUrl, array $authHeaders = [])
    {
        if (!filter_var($nodeUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid node URL provided.");
        }
        $this->nodeUrl = rtrim($nodeUrl, '/');
        $this->headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], array_map(function($key, $value) {
            return "$key: $value";
        }, array_keys($authHeaders), array_values($authHeaders)));
    }

    /**
     * Fetches data from a specific path on the data node.
     *
     * @param string $path The data path (e.g., 'users/123').
     * @return mixed The decoded JSON data.
     * @throws DataNodeClientException If the node returns an error.
     * @throws NetworkException If a network error occurs.
     * @throws SerializationException If JSON decoding fails.
     */
    public function get(string $path): mixed
    {
        return $this->request('GET', $path);
    }

    /**
     * Stores or updates data at a specific path on the data node.
     *
     * @param string $path The data path.
     * @param array $data The data to store.
     * @return array The response from the data node, typically a confirmation or updated data.
     * @throws DataNodeClientException If the node returns an error.
     * @throws NetworkException If a network error occurs.
     * @throws SerializationException If JSON encoding fails.
     */
    public function set(string $path, array $data): array
    {
        return $this->request('POST', $path, $data);
    }

    /**
     * Deletes data at a specific path on the data node.
     *
     * @param string $path The data path.
     * @return array The response from the data node, typically a confirmation.
     * @throws DataNodeClientException If the node returns an error.
     * @throws NetworkException If a network error occurs.
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Internal method to execute HTTP requests using cURL.
     *
     * @param string $method HTTP method (GET, POST, DELETE).
     * @param string $path The API path.
     * @param array|null $body Optional request body for POST/PUT.
     * @return mixed Decoded JSON response.
     * @throws DataNodeClientException On API errors.
     * @throws NetworkException On cURL errors.
     * @throws SerializationException On JSON encoding/decoding errors.
     */
    private function request(string $method, string $path, ?array $body = null): mixed
    {
        $url = $this->nodeUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

        switch ($method) {
            case 'GET':
                // No specific body for GET
                break;
            case 'POST':
                if ($body === null) {
                    throw new \InvalidArgumentException("Request body cannot be null for POST method.");
                }
                $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);
                if ($encodedBody === false) {
                    throw new SerializationException("Failed to encode request body to JSON.");
                }
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: $method");
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($curlErrno) {
            throw new NetworkException("cURL error ({$curlErrno}): {$curlError}", $curlErrno);
        }

        if ($response === false) {
            throw new NetworkException("Empty response received from data node.", 0);
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SerializationException("Failed to decode JSON response: " . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            $errorMessage = $decodedResponse['message'] ?? 'An unknown error occurred on the data node.';
            throw new DataNodeClientException("Data node error ({$httpCode}): {$errorMessage}", $httpCode, $decodedResponse);
        }

        return $decodedResponse;
    }
}

// Define custom exceptions
namespace FederatedJsonStore\Exception;

class DataNodeClientException extends \RuntimeException
{
    private array $responseData;

    public function __construct(string $message, int $code = 0, array $responseData = [], \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}

class NetworkException extends \RuntimeException {}
class SerializationException extends \RuntimeException {}