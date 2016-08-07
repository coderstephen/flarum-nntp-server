<?php
namespace coderstephen\flarum\nntpServer;

class User
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

    public function username(): string
    {
        return $this->data->attributes->username;
    }
}
