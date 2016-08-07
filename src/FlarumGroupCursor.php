<?php
namespace coderstephen\flarum\nntpServer;

use Generator;
use nntp\Article;
use nntp\Group;
use nntp\server\GroupCursor;

class FlarumGroupCursor implements GroupCursor
{
    private $nntp;
    private $group;
    private $currentArticle;
    private $offset = 1;

    public static function create(NntpAdapter $nntp, Group $group): Generator
    {
        $cursor = new self();
        $cursor->nntp = $nntp;
        $cursor->group = $group;
        yield from $cursor->seek(1);

        return $cursor;
    }

    public function valid(): bool
    {
        return $this->offset >= 1 && $this->currentArticle != null;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getArticle(): Article
    {
        return $this->currentArticle;
    }

    public function next(): Generator
    {
        return yield from $this->seek($this->offset + 1);
    }

    public function previous(): Generator
    {
        return yield from $this->seek($this->offset - 1);
    }

    public function seek(int $number): Generator
    {
        $article = yield from $this->nntp->getArticleByNumber($this->group->name(), $number);

        if (!$article) {
            return false;
        }

        $this->offset = $number;
        $this->currentArticle = $article;

        return true;
    }
}
