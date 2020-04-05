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

use Klipper\Component\DoctrineExtensionsExtra\Representation\Pagination;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class SearchResult extends Pagination implements SearchResultInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * Constructor.
     *
     * @param string   $name    The object name
     * @param object[] $results The results
     * @param null|int $page    The page number
     * @param null|int $limit   The limit of pagination
     * @param null|int $pages   The number of pages
     * @param null|int $total   The size of the collection
     */
    public function __construct(string $name, array $results, ?int $page = null, ?int $limit = null, ?int $pages = null, ?int $total = null)
    {
        $this->name = $name;

        parent::__construct($results, $page, $limit, $pages, $total);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
}
