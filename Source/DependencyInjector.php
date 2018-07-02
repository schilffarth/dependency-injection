<?php
/**
 * @author      Roland Schilffarth <roland@schilffarth.org>
 * @license     https://opensource.org/licenses/GPL-3.0 General Public License (GNU 3.0)
 */

namespace Schilffarth\DependencyInjection\Source;

use Schilffarth\DependencyInjection\{
    Exceptions\ClassNotInstantiableException
};
use Schilffarth\Exception\{
    Handling\ErrorHandler
};

class DependencyInjector
{

    /**
     * Property $loaded used to store singleton instances
     */
    private $loaded = [];

    private $errorHandler;

    public function __construct()
    {
        $this->errorHandler = new ErrorHandler();
    }

    /**
     * Build an instance of the given class
     * If $create is passed as true, there's ALWAYS returned a new instantiated object, regardless of registered
     * singletons
     */
    public function inject(string $class, bool $create = false): object
    {
        try {
            if (isset($this->loaded[$class]) && !$create) {
                // Singleton requested and it has already been instantiated
                return $this->loaded[$class];
            }

            $reflector = new \ReflectionClass($class);

            if (!$reflector->isInstantiable()) {
                // Class is not instantiable
                throw new ClassNotInstantiableException(sprintf('%sis not instantiable.', $class));
            }

            // Build an instance with the given reflector
            // Injects objects of the desired instance for class constructor arguments and return the instantiated class
            return $this->getClass($class, $this->injectConstructorArgs($reflector, $class, $create), $create);
        } catch (\Exception $e) {
            $this->errorHandler->handle($e);
            exit;
        }
    }

    /**
     * Instantiate new constructor parameters
     * Constructor arguments are supposed to have either a valid class type hint or a default value
     *
     * Example:
     * public function __construct(
     *     MyClass $myClass,
     *     AnotherDependency $anotherDependency,
     *     SomeSingleton $someSingleton
     * ) {
     *     $this->myClass = $myClass;
     *     $this->anotherDependency = $anotherDependency;
     *     $this->someSingleton = $someSingleton;
     * }
     *
     * If the class constructor has a dependency specified (stick to the example given above) and is injected with
     * @see DependencyInjector::inject(), the instantiated class itself and the constructors dependencies are stored for
     * usage as singletons in @see DependencyInjector::loaded
     */
    private function injectConstructorArgs(\ReflectionClass $reflector, string $class, bool $create): object
    {
        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            // Class doesn't have a declared constructor, no need to inject any dependencies
            return $this->getClass($class, null, $create);
        }

        // Get an array of the constructor parameters' dependencies
        $dependencies = $this->getDependencies($constructor->getParameters(), $create);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * If class has already been instantiated, return its singleton
     * Otherwise create new object and return its instance
     * if $create is true, always create new object
     */
    private function getClass(string $class, object $instance = null, bool $create): object
    {
        while (strlen($class) && $class[0] === "\\") {
            // Make sure the class name is not preceded by a backslash (Would result in "duplicated" objects, which
            // breaks the purpose of using singletons)
            $class = substr($class, 1);
        }

        if (!isset($this->loaded[$class]) || $create) {
            // The instance does either not exist or is desired to be re-created for a new object
            $toInject = $instance === null ? new $class : $instance;

            if ($create) {
                // New object is desired
                return $toInject;
            } else {
                // Register singleton
                $this->loaded[$class] = $toInject;
            }
        }

        return $this->loaded[$class];
    }

    /**
     * Build up a list of dependencies for given parameters
     */
    private function getDependencies(array $parameters, bool $create): array
    {
        $dependencies = [];

        /** @var \ReflectionParameter $param */
        foreach ($parameters as $param) {
            $dependency = $param->getClass();

            if ($dependency === null) {
                // No class type hint for the parameter available
                $dependencies[] = $this->injectNonClass($param);
            } else {
                // Class is available
                // $instance defaults to null regarding the registered singletons
                $instance = null;
                $dependencyName = $dependency->name;

                if (!isset($this->loaded[$dependencyName]) || $create) {
                    // Create a new instance of the class
                    // If $create is false, the object is added to all registered singletons
                    $instance = $this->inject($dependencyName, $create);
                }

                // Add object to list of instantiated constructor dependencies
                $dependencies[] = $this->getClass($dependencyName, $instance, $create);
            }
        }

        return $dependencies;
    }

    /**
     * No class type hint for the parameter available
     * Return its default value or null
     * @return null|string|int|array
     */
    private function injectNonClass(\ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        } else {
            // No default value available, return null
            return null;
        }
    }

}
