<?php
namespace flarumNntp\api;

use Generator;
use Icicle\Http\Client\Client;
use Icicle\Http\Message\Uri;
use Icicle\Stream;


class PaginatedResults
{
    private $forum;
    private $data;
    private $offset;
    private $pageSize;

    public function __construct(Forum $forum, $initialData)
    {
        $this->forum = $forum;
        $this->data = $initialData;
        $this->offset = 0;
        $this->pageSize = count($this->data);
    }

    public function valid(): bool
    {
        return $this->data !== null;
    }

    public function next(): Generator
    {
        // End of page?
        if ($this->offset === $this->pageSize) {
            if (isset($this->data->links->next)) {
                $this->data = yield from $this->forum->callUri('GET', $this->data->links->next);
                $this->offset = 0;
                $this->pageSize = count($this->data);
            } else {
                $this->data = null;
            }
        }

        // Get the next item in the available list.
        return $this->data->data[$this->offset++];
    }
}
