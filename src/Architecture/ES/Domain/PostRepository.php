<?php

namespace Architecture\ES\Domain;

use Architecture\CQRS\Domain\PostId;

interface PostRepository
{
    public function save(Post $post): void;
    public function byId(PostId $id): Post;
}
