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

use Klipper\Component\DoctrineExtensionsExtra\Representation\PaginationInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface SearchResultInterface extends PaginationInterface
{
    /**
     * Get the object name.
     */
    public function getName(): string;
}
