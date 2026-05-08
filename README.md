# php-federated-json-store

## Project Overview

`php-federated-json-store` is a robust and scalable federated JSON document store built primarily with PHP. It enables distributed data management across multiple nodes, offering high availability, eventual consistency, and flexible schema-less data storage. Designed for modern web applications requiring real-time data synchronization and complex query capabilities across disparate data sources, this project provides a solid foundation for building resilient data infrastructure.

## Key Features

*   **Federated Architecture**: Distributes data across multiple, independent nodes for enhanced scalability and fault tolerance.
*   **JSON Document Storage**: Native support for storing and querying JSON documents, accommodating flexible data structures.
*   **Eventual Consistency**: Employs a robust synchronization mechanism to ensure data consistency across the federation.
*   **Real-time Synchronization**: Efficiently propagates data changes across nodes with minimal latency.
*   **Pluggable Storage Backends**: Supports various underlying storage mechanisms (e.g., file system, relational databases) for document persistence.
*   **Comprehensive API**: RESTful interface for document manipulation, querying, and federation management.
*   **Access Control**: Granular permissions for data operations and node interactions.

## System Architecture

The `php-federated-json-store` operates as a network of independent nodes, each capable of storing and serving JSON documents. A central coordination layer facilitates discovery and health monitoring, while a peer-to-peer synchronization protocol ensures data propagation. Clients interact with any available node, which can then proxy requests or serve data directly based on the federation's routing rules.

### Architecture Diagram

![Architecture Diagram](https://placehold.co/800x400/1e1e1e/00ff00?text=System+Architecture+Flow)

### Component Breakdown

*   **Node Service**: The core PHP application running on each server, responsible for API endpoints, document storage, and inter-node communication.
*   **Storage Adapter**: An abstraction layer allowing the node service to interface with different persistence layers (e.g., local disk, PostgreSQL, MySQL).
*   **Replication Engine**: Manages the eventual consistency model, tracking document versions and synchronizing changes with other federated nodes.
*   **Discovery Service**: A lightweight, optional component (or external service) used by nodes to locate and register with other members of the federation.
*   **Client SDK/API Gateway**: External interface for applications to interact with the federated store, handling request routing and load balancing.

## Data Flow

Data flow within the `php-federated-json-store` is designed for high availability and resilient synchronization. When a client writes data to a node, that node becomes the primary source for that specific change. The change is then asynchronously replicated to other nodes in the federation, ensuring that all nodes eventually converge to the same state. Reads can occur from any node, leveraging local data if available or proxying to the authoritative node if necessary.

### Data Flow Diagram

![Data Model](https://placehold.co/800x400/1e1e1e/00ff00?text=Core+Data+Model)

### Data Ingestion

1.  **Client Request**: A client sends a `POST` or `PUT` request with a JSON document to an available `php-federated-json-store` node.
2.  **Validation & Storage**: The receiving node validates the document, assigns a unique identifier if needed, and stores it using its local Storage Adapter.
3.  **Change Log**: The node records the change in its internal transaction log.
4.  **Replication Trigger**: The Replication Engine is notified of the new change.

### Data Replication

1.  **Change Broadcasting**: The Replication Engine periodically queries its change log and broadcasts new or updated document versions to other registered nodes in the federation.
2.  **Peer Acknowledgment**: Receiving nodes validate the incoming changes and acknowledge receipt.
3.  **Local Application**: Each receiving node applies the changes to its local storage, resolving conflicts based on versioning or a predefined conflict resolution strategy.
4.  **Eventual Consistency**: Through continuous synchronization, all nodes eventually reflect the same set of documents and their latest versions.

## Security Protocols

Security is paramount in a distributed system. `php-federated-json-store` implements several layers of security to protect data integrity, confidentiality, and system availability.

### Authentication

*   **API Key / Token-based Authentication**: Clients authenticate with API keys or JSON Web Tokens (JWTs) presented in request headers. Each key/token is associated with specific permissions.
*   **Inter-Node Authentication**: Nodes authenticate with each other using shared secrets or mutual TLS certificates to ensure only trusted members can participate in the federation.

### Authorization

*   **Role-Based Access Control (RBAC)**: Documents and collections can have associated permissions. Users/clients are assigned roles that grant specific read, write, or administrative privileges.
*   **Attribute-Based Access Control (ABAC)**: More granular control can be achieved by defining policies based on document attributes or request context.

### Data Encryption

*   **TLS/SSL for Transit**: All client-to-node and node-to-node communication is encrypted using TLS 1.2+ to prevent eavesdropping and tampering.
*   **Storage-level Encryption (Optional)**: While the store itself does not enforce encryption at rest, integration with file system or database encryption features is supported through the Storage Adapter.

## Getting Started

This section outlines how to set up and run a `php-federated-json-store` node.

### Prerequisites

*   PHP 8.1+
*   Composer
*   A web server (Apache, Nginx, Caddy) with PHP-FPM configured
*   A local storage solution (e.g., a dedicated directory for file-based storage, or a PostgreSQL/MySQL instance if using a relational adapter)

### Installation

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/your-org/php-federated-json-store.git
    cd php-federated-json-store
    ```

2.  **Install dependencies**:
    ```bash
    composer install
    ```

3.  **Configure your web server**: Point your web server's document root to the `public/` directory and ensure PHP-FPM is correctly configured to handle `.php` files.

4.  **Set up environment variables**: Copy `.env.example` to `.env` and adjust the settings.
    ```bash
    cp .env.example .env
    # Edit .env for database credentials, API keys, etc.
    ```

5.  **Run migrations (if using a database adapter)**:
    ```bash
    php artisan migrate # Example for a Laravel-based adapter
    ```

### Configuration

The primary configuration is managed via the `.env` file and `config/` directory. Key parameters include:

*   `APP_NAME`, `APP_ENV`, `APP_DEBUG`
*   `NODE_ID`: A unique identifier for this specific node.
*   `FEDERATION_PEERS`: A comma-separated list of URLs for other nodes in the federation.
*   `STORAGE_DRIVER`: `file`, `mysql`, `pgsql`, etc.
*   `API_KEYS`: A list of authorized API keys and their associated permissions.

## API Documentation

The `php-federated-json-store` exposes a RESTful API for all document operations and federation management. All requests and responses are in JSON format.

### Core Endpoints

*   **`/collections`**
    *   `GET /collections`: List all available collections.
    *   `POST /collections`: Create a new collection.

*   **`/collections/{collection_name}/documents`**
    *   `GET /collections/{collection_name}/documents`: Retrieve all documents in a collection (with optional query parameters for filtering).
    *   `POST /collections/{collection_name}/documents`: Create a new document in the specified collection.

*   **`/collections/{collection_name}/documents/{document_id}`**
    *   `GET /collections/{collection_name}/documents/{document_id}`: Retrieve a specific document by its ID.
    *   `PUT /collections/{collection_name}/documents/{document_id}`: Update an existing document.
    *   `DELETE /collections/{collection_name}/documents/{document_id}`: Delete a document.

### Example Usage

#### Create a Document

```http
POST /collections/users/documents HTTP/1.1
Host: your-node.example.com
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "metadata": {
        "registered_at": "2023-01-15T10:00:00Z"
    }
}
```

```json
HTTP/1.1 201 Created
Content-Type: application/json

{
    "id": "usr_abc123",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "metadata": {
        "registered_at": "2023-01-15T10:00:00Z"
    },
    "_version": 1,
    "_created_at": "2023-03-10T14:30:00Z",
    "_updated_at": "2023-03-10T14:30:00Z"
}
```

#### Retrieve a Document

```http
GET /collections/users/documents/usr_abc123 HTTP/1.1
Host: your-node.example.com
Authorization: Bearer YOUR_API_KEY
```

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
    "id": "usr_abc123",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "metadata": {
        "registered_at": "2023-01-15T10:00:00Z"
    },
    "_version": 1,
    "_created_at": "2023-03-10T14:30:00Z",
    "_updated_at": "2023-03-10T14:30:00Z"
}
```
