<?php
namespace coderstephen\flarum\nntpServer;

use nntp\Group;

/**
 * The server's representation of a Flarum tag.
 */
class Tag
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function id(): int
    {
        return $this->data->id;
    }

    public function name(): string
    {
        return $this->data->attributes->name;
    }

    public function isChild(): bool
    {
        return $this->data->attributes->isChild;
    }

    public function parent(): Tag
    {
        return new Tag($this->data->relationships->parent->data);
    }

    public function slug(): string
    {
        if (!$this->isChild()) {
            return $this->shortSlug();
        }

        return $this->parent()->slug() . '.' . $this->shortSlug();
    }

    public function shortSlug(): string
    {
        return $this->data->attributes->slug;
    }
}
