<?php

namespace FederatedJsonStore\Service;

use FederatedJsonStore\Config;
use FederatedJsonStore\Node\NodeClient;
use FederatedJsonStore\Node\NodeRegistry;
use Psr\Log\LoggerInterface;

class FederationService
{
    private NodeRegistry $nodeRegistry;
    private Config $config;
    private LoggerInterface $logger;
    private string $currentNodeId;

    public function __construct(NodeRegistry $nodeRegistry, Config $config, LoggerInterface $logger)
    {
        $this->nodeRegistry = $nodeRegistry;
        $this->config = $config;
        $this->logger = $logger;
        $this->currentNodeId = $config->getNodeId();
    }

    /**
     * Initializes the federation process by registering the current node and discovering peers.
     */
    public function initializeFederation(): void
    {
        $this->logger->info("Initializing federation for node: {$this->currentNodeId}");
        $this->registerSelf();
        $this->discoverPeers();
    }

    /**
     * Registers the current node with a central registry or designated seed nodes.
     * In a truly decentralized system, this might involve broadcasting.
     */
    private function registerSelf(): void
    {
        $nodeInfo = [
            'node_id' => $this->currentNodeId,
            'api_endpoint' => $this->config->getApiEndpoint(),
            'last_seen' => time(),
            'status' => 'active'
        ];
        $this->nodeRegistry->registerNode($nodeInfo);
        $this->logger->info("Current node ({$this->currentNodeId}) registered successfully.");
    }

    /**
     * Discovers peer nodes from the registry and attempts to establish communication.
     */
    private function discoverPeers(): void
    {
        $this->logger->info("Discovering peer nodes...");
        $peers = $this->nodeRegistry->getKnownNodes();

        foreach ($peers as $peerId => $peerInfo) {
            if ($peerId === $this->currentNodeId) {
                continue; // Don't try to connect to self
            }

            try {
                $client = new NodeClient($peerInfo['api_endpoint'], $this->logger);
                // Ping the peer to verify connectivity and exchange basic info
                $peerStatus = $client->ping();
                if ($peerStatus['status'] === 'ok') {
                    $this->logger->info("Successfully connected to peer node: {$peerId} at {$peerInfo['api_endpoint']}");
                    // Optionally, update peer info with more details from the ping response
                    $this->nodeRegistry->updateNodeStatus($peerId, 'active', $peerStatus['last_seen']);
                } else {
                    $this->logger->warning("Peer node {$peerId} at {$peerInfo['api_endpoint']} responded with non-ok status: " . json_encode($peerStatus));
                    $this->nodeRegistry->updateNodeStatus($peerId, 'unresponsive');
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to connect to peer node {$peerId} at {$peerInfo['api_endpoint']}: " . $e->getMessage());
                $this->nodeRegistry->updateNodeStatus($peerId, 'unresponsive');
            }
        }
    }

    /**
     * Propagates a data change (e.g., a new document, an update) to all active peer nodes.
     * This method assumes an eventual consistency model.
     *
     * @param string $documentId The ID of the document changed.
     * @param array $data The new or updated document data.
     * @param string $changeType 'create', 'update', or 'delete'.
     * @return array An array of results from peer propagation.
     */
    public function propagateDataChange(string $documentId, array $data, string $changeType): array
    {
        $this->logger->info("Propagating data change for document '{$documentId}' (type: {$changeType}) to peer nodes.");
        $results = [];
        $activePeers = $this->nodeRegistry->getActiveNodesExceptSelf($this->currentNodeId);

        foreach ($activePeers as $peerId => $peerInfo) {
            try {
                $client = new NodeClient($peerInfo['api_endpoint'], $this->logger);
                switch ($changeType) {
                    case 'create':
                        $response = $client->createDocument($documentId, $data);
                        break;
                    case 'update':
                        $response = $client->updateDocument($documentId, $data);
                        break;
                    case 'delete':
                        $response = $client->deleteDocument($documentId);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown change type: {$changeType}");
                }
                $results[$peerId] = ['status' => 'success', 'response' => $response];
                $this->logger->debug("Data change for '{$documentId}' propagated to {$peerId}. Response: " . json_encode($response));
            } catch (\Exception $e) {
                $results[$peerId] = ['status' => 'failure', 'error' => $e->getMessage()];
                $this->logger->error("Failed to propagate data change for '{$documentId}' to {$peerId}: " . $e->getMessage());
                // Consider marking node as unresponsive or triggering a re-sync
                $this->nodeRegistry->updateNodeStatus($peerId, 'unresponsive');
            }
        }
        return $results;
    }

    /**
     * Handles incoming data updates from a peer node.
     * This method would typically be called by an API endpoint.
     *
     * @param string $sourceNodeId The ID of the node sending the update.
     * @param string $documentId The ID of the document.
     * @param array $data The document data.
     * @param string $changeType The type of change ('create', 'update', 'delete').
     * @return bool True on success, false on failure.
     */
    public function handleIncomingDataUpdate(string $sourceNodeId, string $documentId, array $data, string $changeType): bool
    {
        $this->logger->info("Received incoming data update from {$sourceNodeId} for document '{$documentId}' (type: {$changeType}).");
        // This is where conflict resolution logic would go for an eventually consistent system.
        // For simplicity, we'll assume the incoming update is authoritative for now.
        // In a real system, timestamps, versions, or CRDTs would be used.

        try {
            // Placeholder for actual data storage interaction
            // Example: $this->dataStore->applyChange($documentId, $data, $changeType);
            $this->logger->debug("Applied incoming change for '{$documentId}' locally.");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to apply incoming data update for '{$documentId}' from {$sourceNodeId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Periodically checks the health of known nodes and updates their status.
     */
    public function performHealthCheck(): void
    {
        $this->logger->info("Performing health check on peer nodes.");
        $peers = $this->nodeRegistry->getKnownNodes();

        foreach ($peers as $peerId => $peerInfo) {
            if ($peerId === $this->currentNodeId) {
                continue;
            }

            try {
                $client = new NodeClient($peerInfo['api_endpoint'], $this->logger);
                $peerStatus = $client->ping();
                if ($peerStatus['status'] === 'ok') {
                    $this->nodeRegistry->updateNodeStatus($peerId, 'active', $peerStatus['last_seen']);
                    $this->logger->debug("Node {$peerId} is healthy.");
                } else {
                    $this->nodeRegistry->updateNodeStatus($peerId, 'unresponsive');
                    $this->logger->warning("Node {$peerId} responded with non-ok status during health check.");
                }
            } catch (\Exception $e) {
                $this->nodeRegistry->updateNodeStatus($peerId, 'unresponsive');
                $this->logger->error("Node {$peerId} is unreachable during health check: " . $e->getMessage());
            }
        }
    }
}