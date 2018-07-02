<?php
/**
 * @author      Roland Schilffarth <roland@schilffarth.org>
 * @license     https://opensource.org/licenses/GPL-3.0 General Public License (GNU 3.0)
 */

namespace Schilffarth\DependencyInjection\Source;

class ObjectManager
{

    private $injector;

    public function __construct()
    {
        $this->injector = new DependencyInjector();
    }

    /**
     * Return singleton of the given class
     */
    public function getSingleton(string $class): object
    {
        return $this->injector->inject($class);
    }

    /**
     * Return a newly created object of the given class
     */
    public function createObject(string $class): object
    {
        return $this->injector->inject($class, true);
    }

}
