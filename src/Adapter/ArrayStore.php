<?php

namespace Adagio\DataStore\Adapter;

use Adagio\DataStore\DataStore;
use Adagio\DataStore\Exception\NotFound;
use Adagio\DataStore\Traits\GuessOrCreateIdentifier;

final class ArrayStore implements DataStore
{
    use GuessOrCreateIdentifier;

    /**
     *
     * @var array
     */
    private $entries = [];

    /**
     *
     * @param array $data
     * @param string $identifier
     *
     * @return $identifier
     */
    public function store(array $data, $identifier = null)
    {
        $id = $this->guessOrCreateIdentifier($data, $identifier);
        $this->entries[$id] = $data;

        return $id;
    }

    /**
     *
     * @param mixed $identifier
     *
     * @return bool
     */
    public function has($identifier)
    {
        return array_key_exists($identifier, $this->entries);
    }

    /**
     *
     * @param string $identifier
     */
    public function remove($identifier)
    {
        if (!$this->has($identifier)) {
            return;
        }

        unset($this->entries[$identifier]);
    }

    /**
     *
     * @param string $identifier
     *
     * @return array
     *
     * @throws NotFound
     */
    public function get($identifier)
    {
        if (!array_key_exists($identifier, $this->entries)) {
            throw NotFound::fromIdentifier($identifier);
        }

        return $this->entries[$identifier];
    }

    /**
     *
     * @param string|array $property
     * @param mixed $value
     * @param string $comparator
     *
     * @return array
     */
    public function findBy($property, $value = null, $comparator = '=')
    {
        $properties = is_array($property) ?
                $property :
                [[ $property, $value, $comparator ]];

        $entries = [];
        foreach ($this->entries as $identifier => $entry) {
            foreach ($properties as $criterion) {
                @list($propertyName, $value, $comparator) = $criterion;

                if (!$comparator) {
                    $comparator = '==';
                } elseif ($comparator === '=') {
                    $comparator = '==';
                }

                if ($this->matches($entry, $propertyName, $comparator, $value)) {
                    $entries[$identifier] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     *
     * @param string|array $property
     * @param mixed $value
     * @param string $comparator
     *
     * @return array
     *
     * @throws NotFound
     */
    public function findOneBy($property, $value = null, $comparator = '=')
    {
        if ($comparator === '=') {
            $comparator = '==';
        }

        $properties = is_array($property) ?
                $property :
                [[ $property, $value, $comparator ]];

        foreach ($this->entries as $entry) {
            $matches = true;
            foreach ($properties as $criterion) {
                @list($propertyName, $value, $comparator) = $criterion;

                if (!$comparator) {
                    $comparator = '==';
                } elseif ($comparator === '=') {
                    $comparator = '==';
                }

                if (!$this->matches($entry, $propertyName, $comparator, $value)) {
                    $matches = false;
                    continue;
                }
            }

            if ($matches) {
                return $entry;
            }
        }

        throw NotFound::fromProperty($property, $comparator, $value);
    }

    /**
     *
     * @return object[]
     */
    public function findAll()
    {
        return $this->entries;
    }

    /**
     *
     * @param array $entry
     * @param string $property
     * @param string $comparator
     * @param mixed $value
     *
     * @return boolean
     */
    private function matches(array $entry, $property, $comparator, $value)
    {
        $entryValue = $this->getEntryProperty($entry, $property);

        switch (true) {
            case ($comparator === '==' && $entryValue == $value): return true;
            case ($comparator === '===' && $entryValue === $value): return true;
            case ($comparator === '!=' && $entryValue != $value): return true;
            case ($comparator === '!==' && $entryValue !== $value): return true;
            case ($comparator === '>'  && $entryValue >  $value): return true;
            case ($comparator === '>=' && $entryValue >= $value): return true;
            case ($comparator === '<'  && $entryValue <  $value): return true;
            case ($comparator === '>=' && $entryValue >= $value): return true;
            case ($comparator === 'IN' && in_array($entryValue, (array) $value)): return true;
        }

        return false;
    }

    /**
     *
     * @param array $entry
     * @param string $propertyPath
     *
     * @return mixed
     */
    private function getEntryProperty(array $entry, $propertyPath)
    {
        // Example: $propertyPath = 'aaa.bbb.ccc' -> $propertyName = 'aaa'
        list($propertyName,) = explode('.', $propertyPath);

        if (!array_key_exists($propertyName, $entry)) {
            return null;
        }

        // Example: $remainingPath = 'bbb.ccc'
        $remainingPath = substr($propertyPath, 1 + strlen($propertyName));

        if (!$remainingPath) {
            return $entry[$propertyName];
        }

        if (!is_array($entry[$propertyName])) {
            return null;
        }

        return $this->getEntryProperty($entry[$propertyName], $remainingPath);
    }
}
