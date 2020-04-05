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

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface SearchResultsInterface
{
    /**
     * Get the object names.
     *
     * @return string[]
     */
    public function getObjectNames(): iterable;

    /**
     * Get the result of all selected objects.
     *
     * @return SearchResultInterface[]
     */
    public function getObjects(): iterable;

    /**
     * Check if the object is present.
     *
     * @param string $name The object name
     */
    public function hasObject(string $name): bool;

    /**
     * Get the result of one object.
     *
     * @param string $name The object name
     */
    public function getObject(string $name): ?SearchResultInterface;
}
