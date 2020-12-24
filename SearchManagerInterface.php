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

use Klipper\Component\Search\Exception\InvalidArgumentException;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface SearchManagerInterface
{
    /**
     * Search in one object.
     *
     * @param string   $object      The object
     * @param string   $query       The query
     * @param string[] $queryFields The query fields
     *
     * @throws InvalidArgumentException When the object doesn't exist
     */
    public function searchByObject(string $object, string $query, array $queryFields = []): SearchResultInterface;

    /**
     * Search in objects.
     *
     * @param string   $query       The query
     * @param string[] $objects     The objects
     * @param string[] $queryFields The query fields
     */
    public function search(string $query, array $objects = [], array $queryFields = []): SearchResultsInterface;
}
