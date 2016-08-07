<?php
namespace coderstephen\flarum\nntpServer;

use ArrayAccess;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * Simple JSON API document wrapper.
 */
class Document implements ArrayAccess, Countable, IteratorAggregate
{
    private $document;

    public function __construct($document)
    {
        $this->document = $document;
    }

    public function isCollection(): bool
    {
        return is_array($this->document->data);
    }

    public function count(): int
    {
        return is_array($this->document->data) ? count($this->document->data) : 1;
    }

    public function getData()
    {
        return $this->resolveRelationships($this->document->data);
    }

    public function hasNextLink(): bool
    {
        return isset($this->document->links) && isset($this->document->links->next);
    }

    public function getNextLink()
    {
        return $this->document->links->next;
    }

    public function hasPrevLink(): bool
    {
        return isset($this->document->links) && isset($this->document->links->prev);
    }

    public function getPrevLink()
    {
        return $this->document->links->next;
    }

    public function offsetExists($offset)
    {
        return $offset < $this->count();
    }

    public function offsetGet($offset)
    {
        return $this->document->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->document->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->document->data[$offset]);
    }

    public function getIterator(): Iterator
    {
        foreach ($this->document->data as $object) {
            yield $this->resolveRelationships($object);
        }
    }

    public function getIncluded(string $type, string $id)
    {
        foreach ($this->document->included as $included) {
            if ($included->type === $type && $included->id === $id) {
                return $this->resolveRelationships($included);
            }
        }
    }

    public function getIncludedOfType(string $type): Iterator
    {
        foreach ($this->document->included as $included) {
            if ($included->type === $type) {
                yield $this->resolveRelationships($included);
            }
        }
    }

    private function resolveRelationships($object)
    {
        if (!is_object($object) || !isset($object->relationships)) {
            return $object;
        }

        foreach ($object->relationships as $relationship) {
            if (is_array($relationship->data)) {
                $relationship->data = array_map(function ($related) {
                    return $this->getIncluded($related->type, $related->id);
                }, $relationship->data);
            } else {
                $included = $this->getIncluded($relationship->data->type, $relationship->data->id);
                if ($included !== null) {
                    $relationship->data = $included;
                }
            }
        }

        return $object;
    }
}
