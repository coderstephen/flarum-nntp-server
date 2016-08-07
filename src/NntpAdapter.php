<?php
namespace coderstephen\flarum\nntpServer;

use Generator;
use nntp\Article;
use nntp\Group;
use nntp\server\AccessLayer;

class NntpAdapter implements AccessLayer
{
    private $flarum;

    public function __construct(FlarumAccess $flarum)
    {
        $this->flarum = $flarum;
    }

    public function getFlarumAccess(): FlarumAccess
    {
        return $this->flarum;
    }

    public function isPostingAllowed(): bool
    {
        return false;
    }

    public function getGroups(): Generator
    {
        $tags = yield from $this->flarum->getTags();

        return array_map(function (Tag $tag) {
            return $this->flarum->tagAsGroup($tag);
        }, array_values($tags));
    }

    public function getGroupByName(string $name): Generator
    {
        $tag = yield from $this->flarum->getTagBySlug($name);
        return $this->flarum->tagAsGroup($tag);
    }

    public function getGroupCursor(string $name): Generator
    {
        $group = yield from $this->getGroupByName($name);
        return yield from FlarumGroupCursor::create($this, $group);
    }

    public function getArticleById(string $id): Generator
    {
        list($id, $domain) = sscanf($id, '<%d@%s>');

        $post = yield from $this->flarum->getPostById($id);
        return yield from $this->flarum->postAsArticle($post);
    }

    public function getArticleByNumber(string $group, int $number): Generator
    {
        $tag = yield from $this->flarum->getTagBySlug($group);
        $post = yield from $this->flarum->getPostByNumberInTag($tag, $number - 1);
        return yield from $this->flarum->postAsArticle($post);
    }

    public function postArticle(string $group, Article $article): Generator
    {
    }
}
