<?php
namespace Magomogo\Persisted\PropertyContainer;
use Doctrine\DBAL\Connection;
use Magomogo\Persisted\PropertyBag;
use Magomogo\Persisted\PersistedInterface;
use Magomogo\Persisted\Exception;

class Db implements ContainerInterface
{
    /**
     * @var string
     */
    private $modelsNamespace;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @param Connection $db
     * @param string $modelsNamespace
     */
    public function __construct($db, $modelsNamespace = '')
    {
        $this->db = $db;
        $this->modelsNamespace = $modelsNamespace;
    }

    /**
     * @param \Magomogo\Persisted\PropertyBag $propertyBag
     * @return \Magomogo\Persisted\PropertyBag
     */
    public function loadProperties($propertyBag)
    {
        $row = $this->begin($propertyBag);
        $propertyBag->assertOriginIs($this);

        foreach ($propertyBag as $name => &$property) {
            $property = array_key_exists($name, $row) ? $this->fromDbValue($property, $row[$name]) : null;
        }
        $this->collectReferences($row, $propertyBag->foreign());

        return $propertyBag;
    }

    /**
     * @param \Magomogo\Persisted\PropertyBag $propertyBag
     * @return \Magomogo\Persisted\PropertyBag
     */
    public function saveProperties($propertyBag)
    {
        $row = $this->foreignKeys($propertyBag->foreign());
        if (!is_null($propertyBag->id)) {
            $row['id'] = $propertyBag->id;
        }
        foreach ($propertyBag as $name => $property) {
            $row[$name] = $this->toDbValue($property);
        }

        return $this->commit($row, $propertyBag);
    }

    /**
     * @param array $propertyBags
     */
    public function deleteProperties(array $propertyBags)
    {
        foreach ($propertyBags as $bag) {
            $this->db->delete($this->classToName($bag), array('id' => $bag->id));
        }
    }

    /**
     * @param string $referenceName
     * @param \Magomogo\Persisted\PropertyBag $leftProperties
     * @param array $connections
     */
    public function referToMany($referenceName, $leftProperties, array $connections)
    {
        $this->db->delete($referenceName, array($this->classToName($leftProperties) => $leftProperties->id));

        /** @var PropertyBag $propertyBag */
        foreach ($connections as $rightProperties) {
            $this->db->insert($referenceName, array(
                $this->classToName($leftProperties) => $leftProperties->id,
                $this->classToName($rightProperties) => $rightProperties->id,
            ));
        }
    }

    /**
     * @param string $referenceName
     * @param \Magomogo\Persisted\PropertyBag $leftProperties
     * @param \Magomogo\Persisted\PropertyBag $rightPropertiesSample
     * @return array
     */
    public function listReferences($referenceName, $leftProperties, $rightPropertiesSample)
    {
        $rightPropertiesName = $this->classToName($rightPropertiesSample);

        $statement = $this->db->executeQuery(
            "SELECT $rightPropertiesName FROM $referenceName WHERE " . $this->classToName($leftProperties) . '=?',
            array($leftProperties->id)
        );

        $connections = array();
        while ($id = $statement->fetchColumn()) {
            $rightPropertiesSample->persisted($id, $this);
            $connections[] = $this->loadProperties(clone $rightPropertiesSample);
        }

        return $connections;
    }

//----------------------------------------------------------------------------------------------------------------------

    private function fromDbValue($property, $column)
    {
        if ($property instanceof PersistedInterface) {
            return $property::loadFrom($this, $column);
        } elseif($property instanceof \DateTime) {
            return new \DateTime($column);
        }
        return $column;
    }

    private function toDbValue($property)
    {
        if (is_scalar($property) || is_null($property)) {
            return $property;
        } elseif ($property instanceof PersistedInterface) {
            return $property->putIn($this);
        } elseif ($property instanceof \DateTime) {
            return $property->format('c');
        } else {
            throw new Exception\Type;
        }
    }

    /**
     * @param \Magomogo\Persisted\PropertyBag $propertyBag
     * @return array
     * @throws \Magomogo\Persisted\Exception\NotFound
     */
    private function begin($propertyBag)
    {
        if (!is_null($propertyBag->id)) {
            $table = $this->classToName($propertyBag);
            $row = $this->db->fetchAssoc("SELECT * FROM $table WHERE id=?", array($propertyBag->id));

            if (is_array($row)) {
                $propertyBag->persisted($propertyBag->id, $this);
                return $row;
            } else {
                throw new Exception\NotFound;
            }
        }
        return array();
    }

    /**
     * @param array $row
     * @param \Magomogo\Persisted\PropertyBag $properties
     * @return \Magomogo\Persisted\PropertyBag
     */
    private function commit(array $row, $properties)
    {
        $this->confirmPersistency($properties);

        if (!$properties->isPersistedIn($this)) {
            $this->db->insert($this->classToName($properties), $row);
            $properties->persisted($properties->id ?: $this->db->lastInsertId(), $this);
        } else {
            $this->db->update($this->classToName($properties), $row, array('id' => $properties->id));
        }

        return $properties;
    }

    /**
     * @param \Magomogo\Persisted\PropertyBag $properties
     */
    private function confirmPersistency($properties)
    {
        try {
            $properties->assertOriginIs($this);
        } catch (Exception\Origin $e) {
            if ($properties->id && $this->db->fetchColumn(
                'SELECT 1 FROM ' . $this->classToName($properties) . ' WHERE id=?', array($properties->id)
            )) {
                $properties->persisted($properties->id, $this);
            }
        }
    }

    private function classToName($class)
    {
        $name = strtolower(str_replace('\\', '_', get_class($class)));
        $namespacePart = strtolower(str_replace('\\', '_', $this->modelsNamespace));
        return preg_replace('/^' . preg_quote($namespacePart) . '/', '', $name);
    }

    private function collectReferences(array $row, $references)
    {
        /* @var PropertyBag $properties */
        foreach ($references as $referenceName => $properties) {
            $properties->persisted($row[$referenceName], $this);
            $this->loadProperties($properties);
        }
        return $references;
    }

    private function foreignKeys($references)
    {
        $keys = array();
        /* @var PropertyBag $properties */
        foreach ($references as $referenceName => $properties) {
            $keys[$referenceName] = $properties->id;
        }

        return $keys;
    }
}