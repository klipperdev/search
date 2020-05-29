<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\Search;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Klipper\Component\DoctrineExtensionsExtra\Filterable\RequestFilterableQuery;
use Klipper\Component\DoctrineExtensionsExtra\Model\Traits\TranslatableInterface;
use Klipper\Component\DoctrineExtensionsExtra\Pagination\RequestPaginationQuery;
use Klipper\Component\DoctrineExtensionsExtra\Sortable\RequestSortableQuery;
use Klipper\Component\DoctrineExtensionsExtra\Util\QueryUtil;
use Klipper\Component\Metadata\MetadataContexts;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Metadata\ObjectMetadataInterface;
use Klipper\Component\Search\Exception\InvalidArgumentException;
use Klipper\Component\Security\Organizational\OrganizationalContextInterface;
use Klipper\Component\Security\Permission\PermissionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class SearchManager implements SearchManagerInterface
{
    /**
     * @var MetadataManagerInterface
     */
    protected $metadataManager;

    /**
     * @var PermissionManagerInterface
     */
    protected $permissionManager;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authChecker;

    /**
     * @var RequestPaginationQuery
     */
    protected $requestPagination;

    /**
     * @var RequestFilterableQuery
     */
    protected $requestFilterable;

    /**
     * @var RequestSortableQuery
     */
    protected $requestSortable;

    /**
     * @var null|OrganizationalContextInterface
     */
    protected $organizationalContext;

    /**
     * @var null|string[]
     */
    protected $cacheObjects;

    /**
     * Constructor.
     *
     * @param MetadataManagerInterface            $metadataManager       The metadata manager
     * @param PermissionManagerInterface          $permissionManager     The permission manager
     * @param ManagerRegistry                     $registry              The doctrine registry
     * @param AuthorizationCheckerInterface       $authChecker           The authorization checker
     * @param RequestPaginationQuery              $requestPagination     The request pagination query
     * @param RequestFilterableQuery              $requestFilterable     The request filterable query
     * @param RequestSortableQuery                $requestSortable       The request sortable query
     * @param null|OrganizationalContextInterface $organizationalContext The organizational context
     */
    public function __construct(
        MetadataManagerInterface $metadataManager,
        PermissionManagerInterface $permissionManager,
        ManagerRegistry $registry,
        AuthorizationCheckerInterface $authChecker,
        RequestPaginationQuery $requestPagination,
        RequestFilterableQuery $requestFilterable,
        RequestSortableQuery $requestSortable,
        ?OrganizationalContextInterface $organizationalContext
    ) {
        $this->metadataManager = $metadataManager;
        $this->permissionManager = $permissionManager;
        $this->registry = $registry;
        $this->authChecker = $authChecker;
        $this->requestPagination = $requestPagination;
        $this->requestFilterable = $requestFilterable;
        $this->requestSortable = $requestSortable;
        $this->organizationalContext = $organizationalContext;
    }

    /**
     * {@inheritdoc}
     */
    public function searchByObject(string $object, string $query): SearchResultInterface
    {
        $results = $this->search($query, [$object]);

        if (!$results->hasObject($object)) {
            throw new InvalidArgumentException(sprintf('The "%s" object doesn\'t exist', $object));
        }

        return $results->getObject($object);
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query, array $objects = []): SearchResultsInterface
    {
        $words = empty($query) ? [] : array_map('trim', explode(' ', $query));
        $lockPage = 1 === \count($objects);
        $objects = $this->validateObjects($objects);
        $results = [];

        foreach ($objects as $object => $class) {
            $results[$object] = $this->searchObject($object, $class, $words, $lockPage);
        }

        return new SearchResults($results);
    }

    /**
     * Search in object.
     *
     * @param string   $object   The object name
     * @param string   $class    The class name
     * @param string[] $words    The words
     * @param bool     $lockPage Check if the request is locked on first page
     */
    protected function searchObject(string $object, string $class, array $words, bool $lockPage): SearchResult
    {
        /** @var EntityRepository $repo */
        $repo = $this->registry->getRepository($class);
        $alias = QueryUtil::getAlias($object);
        $total = 0;
        $results = [];
        $page = 1;
        $pages = 1;

        if (!empty($words)) {
            $qb = $repo->createQueryBuilder($alias);
            $this->injectFilter($qb, $class, $alias, $words);
            $query = $qb->getQuery();

            $this->requestPagination->paginate($query, $lockPage);
            $this->requestSortable->sort($query);

            if ($lockPage) {
                $this->requestFilterable->filter($query);
            }

            if (\in_array(TranslatableInterface::class, class_implements($class), true)) {
                QueryUtil::translateQuery($query);
            }

            $paginator = new Paginator($query);
            $total = $paginator->count();
            $limit = $query->getMaxResults();

            if ($total > 0) {
                $results = $paginator->getIterator()->getArrayCopy();
                $page = (int) $query->getHint(RequestPaginationQuery::HINT_PAGE_NUMBER);
                $pages = (int) ceil($total / $query->getMaxResults());
            }
        } else {
            $query = $repo->createQueryBuilder($alias)->getQuery();
            $this->requestPagination->paginate($query, $lockPage);
            $limit = $query->getMaxResults();
        }

        return new SearchResult(
            $object,
            $results,
            $page,
            $limit,
            $pages,
            $total
        );
    }

    /**
     * Validate the objects.
     *
     * @param string[] $objects The objects
     *
     * @return string[]
     */
    protected function validateObjects(array $objects): array
    {
        $validObjects = $this->getObjectClasses();
        $valid = [];

        if (empty($objects)) {
            $valid = $validObjects;
        } else {
            foreach ($objects as $object) {
                if (isset($validObjects[$object])) {
                    $valid[$object] = $validObjects[$object];
                }
            }
        }

        return $valid;
    }

    /**
     * Get the objects.
     *
     * @return string[]
     */
    protected function getObjectClasses(): array
    {
        if (null === $this->cacheObjects) {
            $this->cacheObjects = [];

            foreach ($this->metadataManager->all() as $metadata) {
                if ($metadata->isPublic() && $metadata->isSearchable()
                        && $this->isValidContext($metadata)
                        && $this->authChecker->isGranted('perm:view', $metadata->getClass())) {
                    $config = $this->permissionManager->hasConfig($metadata->getClass())
                        ? $this->permissionManager->getConfig($metadata->getClass())
                        : null;

                    if ($config && null === $config->getMaster()) {
                        $this->cacheObjects[$metadata->getName()] = $metadata->getClass();
                    }
                }
            }
        }

        return $this->cacheObjects;
    }

    /**
     * Inject the filter in the query builder.
     *
     * @param QueryBuilder $qb    The query builder for filter
     * @param string       $class The class name
     * @param string       $alias The alias
     * @param string[]     $words The words
     */
    private function injectFilter(QueryBuilder $qb, string $class, string $alias, array $words): QueryBuilder
    {
        $fields = $this->metadataManager->get($class)->getFields();
        $filter = '';

        foreach ($fields as $fieldMeta) {
            $field = $alias.'.'.$fieldMeta->getField();

            if ($fieldMeta->isPublic() && $fieldMeta->isSearchable()) {
                $filter .= '' === $filter ? '(' : ' OR (';

                foreach ($words as $i => $word) {
                    $key = 'search_'.str_replace(['.'], '_', $field).'_'.($i + 1);
                    $qb->setParameter($key, '%'.$word.'%');
                    $filter .= 0 === $i ? '' : ' AND ';
                    $filter .= 'UNACCENT(LOWER('.$field.')) LIKE UNACCENT(LOWER(:'.$key.'))';
                }

                $filter .= ')';
            }
        }

        return $qb->andWhere($filter);
    }

    /**
     * Check if the metadata is compatible with organizational context.
     *
     * @param ObjectMetadataInterface $metadata The object metadata
     */
    private function isValidContext(ObjectMetadataInterface $metadata): bool
    {
        if ($this->organizationalContext && $this->organizationalContext->isOrganization()) {
            return \in_array(MetadataContexts::ORGANIZATION, $metadata->getAvailableContexts(), true);
        }

        return \in_array(MetadataContexts::USER, $metadata->getAvailableContexts(), true);
    }
}
