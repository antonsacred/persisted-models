<?php
namespace Test\Keymarker;

use Magomogo\Persisted\Container\ContainerInterface;
use Magomogo\Persisted\ModelInterface;
use Magomogo\Persisted\PropertyBag;

class Model implements ModelInterface
{
    /**
     * @var Properties
     */
    private $properties;

    /**
     * @param ContainerInterface $container
     * @param string $id
     * @return self
     */
    public static function load($container, $id = null)
    {
        $p = new Properties();
        $p->persisted($id, $container);

        return $p->loadFrom($container)->constructModel();
    }

    /**
     * @param ContainerInterface $container
     * @return PropertyBag
     */
    public function propertiesFrom($container)
    {
        return $this->properties;
    }

//----------------------------------------------------------------------------------------------------------------------

    /**
     * @param Properties $properties
     */
    public function __construct($properties)
    {
        $this->properties = $properties;
    }

    public function __toString()
    {
        return $this->properties->id;
    }
}
