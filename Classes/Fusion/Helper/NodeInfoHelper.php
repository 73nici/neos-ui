<?php
namespace Neos\Neos\Ui\Fusion\Helper;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\CountAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\NodeHiddenState\NodeHiddenStateFinder;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateClassification;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Neos\FrontendRouting\NodeAddress;
use Neos\Neos\FrontendRouting\NodeAddressFactory;
use Neos\Neos\FrontendRouting\NodeUriBuilder;
use Neos\Neos\Ui\Domain\Service\NodePropertyConverterService;
use Neos\Neos\Ui\Domain\Service\UserLocaleService;
use Neos\Neos\Utility\NodeTypeWithFallbackProvider;

/**
 * @internal
 * @Flow\Scope("singleton")
 * @todo EEL helpers are still to be declared as internal
 */
class NodeInfoHelper implements ProtectedContextAwareInterface
{
    use NodeTypeWithFallbackProvider;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * @Flow\Inject
     * @var UserLocaleService
     */
    protected $userLocaleService;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var NodePropertyConverterService
     */
    protected $nodePropertyConverterService;

    /**
     * @Flow\InjectConfiguration(path="userInterface.navigateComponent.nodeTree.presets.default.baseNodeType", package="Neos.Neos")
     * @var string
     */
    protected $baseNodeType;

    /**
     * @Flow\InjectConfiguration(path="nodeTypeRoles.document", package="Neos.Neos.Ui")
     * @var string
     */
    protected $documentNodeTypeRole;

    /**
     * @Flow\InjectConfiguration(path="nodeTypeRoles.ignored", package="Neos.Neos.Ui")
     * @var string
     */
    protected $ignoredNodeTypeRole;

    /**
     * @return ?array<string,mixed>
     */
    public function renderNodeWithMinimalPropertiesAndChildrenInformation(
        Node $node,
        ActionRequest $actionRequest = null,
        string $nodeTypeFilterOverride = null
    ): ?array {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $nodeHiddenStateFinder = $contentRepository->projectionState(NodeHiddenStateFinder::class);

        /** @todo implement custom node policy service
        if (!$this->nodePolicyService->isNodeTreePrivilegeGranted($node)) {
        return null;
        }*/
        $this->userLocaleService->switchToUILocale();

        $nodeInfo = $this->getBasicNodeInformation($node);
        $nodeInfo['properties'] = [
            // if we are only rendering the tree state,
            // ensure _isHidden is sent to hidden nodes are correctly shown in the tree.
            '_hidden' => $nodeHiddenStateFinder->findHiddenState(
                $node->subgraphIdentity->contentStreamId,
                $node->subgraphIdentity->dimensionSpacePoint,
                $node->nodeAggregateId
            )->isHidden,
            '_hiddenInIndex' => $node->getProperty('_hiddenInIndex'),
            //'_hiddenBeforeDateTime' => $node->getHiddenBeforeDateTime() instanceof \DateTimeInterface,
            //'_hiddenAfterDateTime' => $node->getHiddenAfterDateTime() instanceof \DateTimeInterface,
        ];

        if ($actionRequest !== null) {
            $nodeInfo = array_merge($nodeInfo, $this->getUriInformation($node, $actionRequest));
        }

        $baseNodeType = $nodeTypeFilterOverride ?: $this->baseNodeType;
        $nodeTypeFilter = $this->buildNodeTypeFilterString(
            $this->nodeTypeStringsToList($baseNodeType),
            $this->nodeTypeStringsToList($this->ignoredNodeTypeRole)
        );

        $nodeInfo['children'] = $this->renderChildrenInformation($node, $nodeTypeFilter);

        $this->userLocaleService->switchToUILocale(true);

        return $nodeInfo;
    }

    /**
     * @return ?array<string,mixed>
     */
    public function renderNodeWithPropertiesAndChildrenInformation(
        Node $node,
        ActionRequest $actionRequest = null,
        string $nodeTypeFilterOverride = null
    ): ?array {
        /** @todo implement custom node policy service
        if (!$this->nodePolicyService->isNodeTreePrivilegeGranted($node)) {
        return null;
        }**/

        $this->userLocaleService->switchToUILocale();

        $nodeInfo = $this->getBasicNodeInformation($node);
        $nodeInfo['properties'] = $this->nodePropertyConverterService->getPropertiesArray($node);
        $nodeInfo['isFullyLoaded'] = true;

        if ($actionRequest !== null) {
            $nodeInfo = array_merge($nodeInfo, $this->getUriInformation($node, $actionRequest));
        }

        $baseNodeType = $nodeTypeFilterOverride ? $nodeTypeFilterOverride : $this->baseNodeType;
        $nodeInfo['children'] = $this->renderChildrenInformation($node, $baseNodeType);

        $this->userLocaleService->switchToUILocale(true);

        return $nodeInfo;
    }

    /**
     * Get the "uri" and "previewUri" for the given node
     *
     * @param Node $node
     * @return array<string,string>
     */
    protected function getUriInformation(Node $node, ActionRequest $actionRequest): array
    {
        $nodeInfo = [];
        if (!$this->getNodeType($node)->isOfType($this->documentNodeTypeRole)) {
            return $nodeInfo;
        }
        $nodeInfo['uri'] = $this->previewUri($node, $actionRequest);
        return $nodeInfo;
    }

    /**
     * Get the basic information about a node.
     *
     * @return array<string,mixed>
     */
    protected function getBasicNodeInformation(Node $node): array
    {
        $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
        $parentNode = $subgraph->findParentNode($node->nodeAggregateId);

        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
        $nodeAddress = $nodeAddressFactory->createFromNode($node);

        return [
            'contextPath' => $nodeAddress->serializeForUri(),
            'nodeAddress' => $nodeAddress->serializeForUri(),
            'name' => $node->nodeName?->value ?? '',
            'identifier' => $node->nodeAggregateId->jsonSerialize(),
            'nodeType' => $node->nodeTypeName->value,
            'label' => $node->getLabel(),
            'isAutoCreated' => $node->classification === NodeAggregateClassification::CLASSIFICATION_TETHERED,
            // TODO: depth is expensive to calculate; maybe let's get rid of this?
            'depth' => $subgraph->countAncestorNodes(
                $node->nodeAggregateId,
                CountAncestorNodesFilter::create()
            ),
            'children' => [],
            'parent' => $parentNode ? $nodeAddressFactory->createFromNode($parentNode)->serializeForUri() : null,
            'matchesCurrentDimensions' => $node->subgraphIdentity->dimensionSpacePoint->equals($node->originDimensionSpacePoint),
            'lastModificationDateTime' => $node->timestamps->lastModified?->format(\DateTime::ATOM),
            'creationDateTime' => $node->timestamps->created->format(\DateTime::ATOM),
            'lastPublicationDateTime' => $node->timestamps->originalLastModified?->format(\DateTime::ATOM)
        ];
    }

    /**
     * Get information for all children of the given parent node.
     *
     * @param Node $node
     * @param string $nodeTypeFilterString
     * @return array<int,array<string,string>>
     */
    protected function renderChildrenInformation(Node $node, string $nodeTypeFilterString): array
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);

        $documentChildNodes = $subgraph->findChildNodes(
            $node->nodeAggregateId,
            FindChildNodesFilter::create(nodeTypes: $nodeTypeFilterString)
        );
        // child nodes for content tree, must not include those nodes filtered out by `baseNodeType`
        $contentChildNodes = $subgraph->findChildNodes(
            $node->nodeAggregateId,
            FindChildNodesFilter::create(
                nodeTypes: $this->buildContentChildNodeFilterString()
            )
        );
        $childNodes = $documentChildNodes->merge($contentChildNodes);

        $infos = [];
        foreach ($childNodes as $childNode) {
            $contentRepository = $this->contentRepositoryRegistry->get($childNode->subgraphIdentity->contentRepositoryId);
            $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
            $infos[] = [
                'contextPath' => $nodeAddressFactory->createFromNode($childNode)->serializeForUri(),
                'nodeType' => $childNode->nodeTypeName->value // TODO: DUPLICATED; should NOT be needed!!!
            ];
        };
        return $infos;
    }

    /**
     * @param array<int,Node> $nodes
     * @return array<int,?array<string,mixed>>
     */
    public function renderNodes(
        array $nodes,
        ActionRequest $actionRequest,
        bool $omitMostPropertiesForTreeState = false
    ): array {
        $mapper = function (Node $node) use ($actionRequest, $omitMostPropertiesForTreeState) {
            return $omitMostPropertiesForTreeState
                ? $this->renderNodeWithMinimalPropertiesAndChildrenInformation($node, $actionRequest)
                : $this->renderNodeWithPropertiesAndChildrenInformation($node, $actionRequest);
        };
        return array_values(array_filter(array_map($mapper, $nodes)));
    }

    /**
     * @param array<int,?array<string,mixed>> $nodes
     * @return array<int,?array<string,mixed>>
     */
    public function renderNodesWithParents(array $nodes, ActionRequest $actionRequest): array
    {
        // For search operation we want to include all nodes, not respecting the "baseNodeType" setting
        $baseNodeTypeOverride = $this->documentNodeTypeRole;
        $renderedNodes = [];

        /** @var Node $node */
        foreach ($nodes as $node) {
            $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);

            if (array_key_exists($node->nodeAggregateId->value, $renderedNodes)) {
                $renderedNodes[$node->nodeAggregateId->value]['matched'] = true;
            } elseif ($renderedNode = $this->renderNodeWithMinimalPropertiesAndChildrenInformation(
                $node,
                $actionRequest,
                $baseNodeTypeOverride
            )) {
                $renderedNode['matched'] = true;
                $renderedNodes[$node->nodeAggregateId->value] = $renderedNode;
            } else {
                continue;
            }

            $parentNode = $subgraph->findParentNode($node->nodeAggregateId);
            if ($parentNode === null) {
                // There are a multitude of reasons why a node might not have a parent
                // and we should ignore these gracefully.
                continue;
            }

            while ($this->getNodeType($parentNode)->isOfType($baseNodeTypeOverride)) {
                if (array_key_exists($parentNode->nodeAggregateId->value, $renderedNodes)) {
                    $renderedNodes[$parentNode->nodeAggregateId->value]['intermediate'] = true;
                } else {
                    $renderedParentNode = $this->renderNodeWithMinimalPropertiesAndChildrenInformation(
                        $parentNode,
                        $actionRequest,
                        $baseNodeTypeOverride
                    );
                    if ($renderedParentNode) {
                        $renderedParentNode['intermediate'] = true;
                        $renderedNodes[$parentNode->nodeAggregateId->value] = $renderedParentNode;
                    }
                }
                $parentNode = $subgraph->findParentNode($parentNode->nodeAggregateId);
                if ($parentNode === null) {
                    // There are a multitude of reasons why a node might not have a parent
                    // and we should ignore these gracefully.
                    break;
                }
            }
        }

        return array_values($renderedNodes);
    }

    /**
     * @return array<string,array<string,mixed>|null>
     */
    public function defaultNodesForBackend(
        Node $site,
        Node $documentNode,
        ActionRequest $actionRequest
    ): array {
        // does not support multiple CRs here yet
        $contentRepository = $this->contentRepositoryRegistry->get($site->subgraphIdentity->contentRepositoryId);
        $nodeAddressFactory = NodeAddressFactory::create($contentRepository);

        return [
            ($nodeAddressFactory->createFromNode($site)->serializeForUri())
            => $this->renderNodeWithPropertiesAndChildrenInformation($site, $actionRequest),
            ($nodeAddressFactory->createFromNode($documentNode)->serializeForUri())
            => $this->renderNodeWithPropertiesAndChildrenInformation($documentNode, $actionRequest)
        ];
    }

    public function uri(Node|NodeAddress $nodeAddress, ActionRequest $actionRequest): string
    {
        if ($nodeAddress instanceof Node) {
            $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->subgraphIdentity->contentRepositoryId);
            $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
            $nodeAddress = $nodeAddressFactory->createFromNode($nodeAddress);
        }
        return (string)NodeUriBuilder::fromRequest($actionRequest)->uriFor($nodeAddress);
    }

    public function previewUri(Node $node, ActionRequest $actionRequest): string
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
        $nodeAddress = $nodeAddressFactory->createFromNode($node);
        return (string)NodeUriBuilder::fromRequest($actionRequest)->previewUriFor($nodeAddress);
    }

    public function createRedirectToNode(Node $node, ActionRequest $actionRequest): string
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
        $nodeAddress = $nodeAddressFactory->createFromNode($node);
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($actionRequest);
        return $uriBuilder
            ->setCreateAbsoluteUri(true)
            ->setFormat('html')
            ->uriFor('redirectTo', ['node' => $nodeAddress->serializeForUri()], 'Backend', 'Neos.Neos.Ui');
    }

    /**
     * @param string ...$nodeTypeStrings
     * @return string[]
     */
    protected function nodeTypeStringsToList(string ...$nodeTypeStrings)
    {
        $reducer = function ($nodeTypeList, $nodeTypeString) {
            $nodeTypeParts = explode(',', $nodeTypeString);
            foreach ($nodeTypeParts as $nodeTypeName) {
                $nodeTypeList[] = trim($nodeTypeName);
            }

            return $nodeTypeList;
        };

        return array_reduce($nodeTypeStrings, $reducer, []);
    }

    /**
     * @param array<int,string> $includedNodeTypes
     * @param array<int,string> $excludedNodeTypes
     */
    protected function buildNodeTypeFilterString(array $includedNodeTypes, array $excludedNodeTypes): string
    {
        $preparedExcludedNodeTypes = array_map(function ($nodeTypeName) {
            return '!' . $nodeTypeName;
        }, $excludedNodeTypes);
        $mergedIncludesAndExcludes = array_merge($includedNodeTypes, $preparedExcludedNodeTypes);
        return implode(',', $mergedIncludesAndExcludes);
    }

    protected function buildContentChildNodeFilterString(): string
    {
        return $this->buildNodeTypeFilterString(
            [],
            $this->nodeTypeStringsToList(
                $this->documentNodeTypeRole,
                $this->ignoredNodeTypeRole
            )
        );
    }

    public function nodeAddress(Node $node): NodeAddress
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
        return $nodeAddressFactory->createFromNode($node);
    }

    public function serializedNodeAddress(Node $node): string
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $nodeAddressFactory = NodeAddressFactory::create($contentRepository);
        return $nodeAddressFactory->createFromNode($node)->serializeForUri();
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
