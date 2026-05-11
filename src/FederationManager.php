<?php

namespace FederatedJsonStore;

use FederatedJsonStore\Contracts\{NodeRegistryInterface, DataRouterInterface, LoggerInterface};
use FederatedJsonStore\Exceptions\{NodeNotFoundException, DataDistributionException, QueryExecutionException};
use FederatedJsonStore\ValueObjects\Node;

/**
 * Manages the federation of distributed JSON stores, orchestrating node interactions,
 * data distribution, and query routing across the network.
 */
final class FederationManager
{
    private NodeRegistryInterface $nodeRegistry;
    private DataRouterInterface $dataRouter;
    private LoggerInterface $logger;

    public function __construct(
        NodeRegistryInterface $nodeRegistry,
        DataRouterInterface $dataRouter,
        LoggerInterface $logger
    ) {
        $this->nodeRegistry = $nodeRegistry;
        $this->dataRouter = $dataRouter;
        $this->logger = $logger;
    }

    /**
     * Registers a new node within the federation.
     *
     * @param Node $node The node to register.
     * @return bool True if registration was successful, false otherwise.
     */
    public function registerNode(Node $node): bool
    {
        try {
            $this->nodeRegistry->addNode($node);
            $this->logger->info(sprintf("Node '%s' registered successfully.", $node->getId()));
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed to register node '%s': %s", $node->getId(), $e->getMessage()));
            return false;
        }
    }

    /**
     * Deregisters an existing node from the federation.
     *
     * @param string $nodeId The ID of the node to deregister.
     * @return bool True if deregistration was successful, false otherwise.
     * @throws NodeNotFoundException If the specified node does not exist.
     */
    public function deregisterNode(string $nodeId): bool
    {
        try {
            $this->nodeRegistry->removeNode($nodeId);
            $this->logger->info(sprintf("Node '%s' deregistered successfully.", $nodeId));
            return true;
        } catch (NodeNotFoundException $e) {
            $this->logger->warning(sprintf("Attempted to deregister non-existent node '%s'.", $nodeId));
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed to deregister node '%s': %s", $nodeId, $e->getMessage()));
            return false;
        }
    }

    /**
     * Distributes a data payload to the appropriate node(s) based on routing logic.
     *
     * @param string $key The primary key for the data.
     * @param array $data The data payload to distribute.
     * @param array $metadata Optional metadata for distribution.
     * @return array A list of node IDs where the data was successfully distributed.
     * @throws DataDistributionException If data distribution fails for critical nodes.
     */
    public function distributeData(string $key, array $data, array $metadata = []): array
    {
        try {
            $targetNodes = $this->dataRouter->routeData($key, $data, $this->nodeRegistry->getAllNodes());
            if (empty($targetNodes)) {
                throw new DataDistributionException("No suitable nodes found for data distribution with key: " . $key);
            }

            $successfulNodes = [];
            foreach ($targetNodes as $node) {
                // In a real system, this would involve network calls to the node.
                // For this example, we'll simulate success.
                $this->logger->debug(sprintf("Attempting to send data for key '%s' to node '%s'.", $key, $node->getId()));
                // simulate network call
                if (rand(0, 10) < 9) { // 90% success rate simulation
                    $successfulNodes[] = $node->getId();
                    $this->logger->info(sprintf("Data for key '%s' successfully distributed to node '%s'.", $key, $node->getId()));
                } else {
                    $this->logger->warning(sprintf("Failed to distribute data for key '%s' to node '%s'.", $key, $node->getId()));
                }
            }

            if (empty($successfulNodes)) {
                throw new DataDistributionException(sprintf("Failed to distribute data for key '%s' to any target node.", $key));
            }

            return $successfulNodes;
        } catch (DataDistributionException $e) {
            $this->logger->error(sprintf("Critical data distribution failure for key '%s': %s", $key, $e->getMessage()));
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical(sprintf("Unexpected error during data distribution for key '%s': %s", $key, $e->getMessage()));
            throw new DataDistributionException("Unexpected error during data distribution.", 0, $e);
        }
    }

    /**
     * Executes a query across the federated store, routing it to relevant nodes.
     *
     * @param string $query A string representing the query (e.g., JSONPath, custom DSL).
     * @param array $params Optional query parameters.
     * @return array The aggregated results from the queried nodes.
     * @throws QueryExecutionException If the query cannot be executed or results aggregated.
     */
    public function executeQuery(string $query, array $params = []): array
    {
        try {
            $relevantNodes = $this->dataRouter->routeQuery($query, $params, $this->nodeRegistry->getAllNodes());
            if (empty($relevantNodes)) {
                $this->logger->warning(sprintf("No relevant nodes found for query: '%s'", $query));
                return [];
            }

            $aggregatedResults = [];
            foreach ($relevantNodes as $node) {
                // In a real system, this would involve sending the query to the node and collecting results.
                // Simulate query execution and result aggregation.
                $this->logger->debug(sprintf("Executing query '%s' on node '%s'.", $query, $node->getId()));
                // simulate network call and result
                $nodeResults = ['node' => $node->getId(), 'data' => ['simulated_result_field' => uniqid('res_')]];
                $aggregatedResults[] = $nodeResults;
            }

            $this->logger->info(sprintf("Query '%s' executed across %d nodes, %d results aggregated.", $query, count($relevantNodes), count($aggregatedResults)));
            return $aggregatedResults;
        } catch (QueryExecutionException $e) {
            $this->logger->error(sprintf("Query execution failure for '%s': %s", $query, $e->getMessage()));
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical(sprintf("Unexpected error during query execution for '%s': %s", $query, $e->getMessage()));
            throw new QueryExecutionException("Unexpected error during query execution.", 0, $e);
        }
    }
}