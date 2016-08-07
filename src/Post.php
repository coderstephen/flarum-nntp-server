<?php
namespace coderstephen\flarum\nntpServer;

use DateTime;
use nntp\Article;

class Post
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

    public function number(): int
    {
        return $this->data->attributes->number;
    }

    public function title(): string
    {
        if ($this->number() > 1) {
            return 'RE: ' . $this->data->relationships->discussion->data->attributes->title;
        } else {
            return $this->data->relationships->discussion->data->attributes->title;
        }
    }

    public function date(): DateTime
    {
        return new DateTime($this->data->attributes->time);
    }

    public function user(): User
    {
        if (isset($this->data->relationships->user)) {
            return new User($this->data->relationships->user->data);
        } else {
            var_dump($this->data->relationships);
        }
    }

    public function content(): string
    {
        return isset($this->data->attributes->contentHtml) ? $this->data->attributes->contentHtml : '';
    }
}
