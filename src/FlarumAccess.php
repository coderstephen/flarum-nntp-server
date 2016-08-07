<?php
namespace coderstephen\flarum\nntpServer;

use Generator;
use Icicle\Cache\MemoryCache;
use Icicle\Http\Client\Client;
use Icicle\Http\Message\BasicUri;
use Icicle\Http\Message\Uri;
use Icicle\Stream;
use nntp\Article;
use nntp\Group;

/**
 * Simple barebones API client for Flarum for use in the access layer.
 */
class FlarumAccess
{
    private $httpClient;
    private $baseUri;
    private $cacheExpiration;
    private $cache;
    private $token;

    public function __construct(string $baseUri, int $cacheExpiration)
    {
        $this->httpClient = new Client();
        $this->baseUri = new BasicUri($baseUri);
        $this->cacheExpiration = $cacheExpiration;
        $this->cache = new MemoryCache();
    }

    public function getDomain(): string
    {
        return $this->baseUri->getHost();
    }

    /**
     * Gets a map of all tags.
     *
     * The map is a mapping from tag IDs to tag objects.
     */
    public function getTags(): Generator
    {
        if (yield from $this->cache->exists('tags')) {
            return yield from $this->cache->get('tags');
        }

        $document = yield from $this->endpoint('GET', '/forum');
        $tags = [];

        // Iterate over included data and look for tags.
        foreach ($document->getIncludedOfType('tags') as $object) {
            // Create a new tag object and store it.
            $tag = new Tag($object);
            $tags[$tag->id()] = $tag;
        }

        yield from $this->cache->set('tags', $tags, $this->cacheExpiration);
        return $tags;
    }

    /**
     * Get a tag by its full slug.
     */
    public function getTagBySlug(string $slug): Generator
    {
        $tags = yield from $this->getTags();

        foreach ($tags as $tag) {
            if ($tag->slug() === $slug) {
                return $tag;
            }
        }
    }

    /**
     * Get a post by its unique ID.
     */
    public function getPostById(int $id): Generator
    {
        $document = yield from $this->endpoint('GET', '/posts/' . $id);
        $data = $document->getData();
        return new Post($data);
    }

    public function getPostByNumberInTag(Tag $tag, int $number): Generator
    {
        // Fetch from cache if possible.
        $cacheKey = 'posts/' . $tag->id() . '/' . $number;
        if (yield from $this->cache->exists($cacheKey)) {
            return yield from $this->cache->get($cacheKey);
        }

        // Fetch articles in bulk so they can be cached.
        $posts = yield from $this->getPostsTagged($tag, $number);

        // Cache all results, even though we need only one right now.
        for ($i = 0; $i < count($posts); ++$i) {
            $cacheKey = 'posts/' . $tag->id() . '/' . ($number + $i);
            yield from $this->cache->set($cacheKey, $posts[$i], $this->cacheExpiration);
        }

        // Return the requested article.
        if (isset($posts[0])) {
            return $posts[0];
        }
    }

    /**
     * Gets posts in batches whose discussions have a particular tag.
     *
     * In order to produce a reliable, sequential article number ordering, the posts are sorted by date. This ensures
     * that article numbers are stable.
     */
    public function getPostsTagged(Tag $tag, int $offset = 0): Generator
    {
        $document = yield from $this->endpoint('GET', '/posts', [
            'filter[type]' => 'comment',
            'filter[tag]' => $tag->id(),
            'page[offset]' => $offset,
            'sort' => 'time',
        ]);

        $posts = [];
        foreach ($document as $post) {
            $posts[] = new Post($post);
        }

        return $posts;
    }

    /**
     * Count the number of posts in a given tag.
     */
    public function countPostsTagged(Tag $tag): Generator
    {
        $cacheKey = 'count/' . $tag->id();
        if (yield from $this->cache->exists($cacheKey)) {
            return yield from $this->cache->get($cacheKey);
        }

        $count = 0;
        $offset = 0;
        do {
            $document = yield from $this->endpoint('GET', '/discussions', [
                'filter[q]' => 'tag:' . $tag->shortSlug(),
                'page[offset]' => $offset,
            ]);

            foreach ($document as $discussion) {
                $count += $discussion->attributes->commentsCount;
            }

            $offset += $document->count();
        } while ($document->hasNextLink());

        yield from $this->cache->set($cacheKey, $count, $this->cacheExpiration);
        return $count;
    }

    public function getDiscussionForPost(Post $post): Generator
    {
        $id = $post->data->relationships->discussion->data->id;
        $cacheKey = 'discussions/' . $id;

        if (yield from $this->cache->exists($cacheKey)) {
            return yield from $this->cache->get($cacheKey);
        }

        $document = yield from $this->endpoint('GET', '/discussions/' . $id);
        $discussion = $document->getData();

        yield from $this->cache->set($cacheKey, $discussion, $this->cacheExpiration);
        return $discussion;
    }

    public function getTagForPost(Post $post): Generator
    {
        $discussion = yield from $this->getDiscussionForPost($post);

        if (isset($discussion->relationships->tags->data[0])) {
            return new Tag($discussion->relationships->tags->data[0]);
        }
    }

    public function getParentPostForPost(Post $post): Generator
    {
        $discussion = yield from $this->getDiscussionForPost($post);

        if (isset($discussion->relationships->posts->data[0])) {
            return new Post($discussion->relationships->posts->data[0]);
        }
    }

    public function tagAsGroup(Tag $tag): Group
    {
        return new Group($tag->slug(), $tag->data->attributes->discussionsCount, 1, $tag->data->attributes->discussionsCount);
    }

    public function postAsArticle(Post $post): Generator
    {
        $host = $this->getDomain();
        $tag = yield from $this->getTagForPost($post);
        $parent = yield from $this->getParentPostForPost($post);

        $headers = [
            'Newsgroups' => $tag->slug(),
            'Path' => $host,
            'Message-ID' => sprintf('<%d@%s>', $post->id(), $host),
            'Subject' => $post->title(),
            'Date' => $post->date()->format(Article::DATE_FORMAT),
            'Content-Type' => 'text/html',
            'From' => sprintf('%s@%s', $post->user()->username(), $host),
        ];

        if ($parent->id() !== $post->id()) {
            $headers['In-Reply-To'] = sprintf('<%d@%s>', $parent->id(), $host);
            $headers['References'] = sprintf('<%d@%s>', $parent->id(), $host);
        }

        return new Article($post->content(), $headers, $post->number());
    }

    public function login(string $username, string $password): Generator
    {
        $document = yield from $this->endpoint('GET', '/token', [
            'identification' => $username,
            'password' => $password,
        ]);
    }

    public function endpoint(string $method, string $endpoint, array $params = [], array $data = null): Generator
    {
        $uri = $this->baseUri->withPath('/api/' . $endpoint);
        foreach ($params as $name => $value) {
            $uri = $uri->withQueryValue($name, $value);
        }

        return $this->request($method, $uri, $data);
    }

    private function request(string $method, Uri $uri, array $data = null): Generator
    {
        $requestBody = $data !== null ? json_encode($data) : null;
        $headers = [];

        if ($this->token) {
            $headers['Authorization'] = 'Token ' . $this->token;
        }

        $response = yield from $this->httpClient->request($method, $uri, $headers, $requestBody);

        // Read the response body into a string.
        $responseBody = yield from Stream\readAll($response->getBody());

        // Parse the response JSON.
        $responseData = json_decode($responseBody, false);

        return new Document($responseData);
    }
}
