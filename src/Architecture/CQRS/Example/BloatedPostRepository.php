<?php

namespace Architecture\CQRS\Example;

use Architecture\CQRS\Domain\Post;
use Architecture\CQRS\Domain\PostId;
use Architecture\CQRS\Domain\CategoryId;

class TagId {
    
}

//snippet bloated-post-repository
interface PostRepository
{
    public function save(Post $post): void;
    public function byId(PostId $id): Post;
    public function all(): array;
    public function byCategory(CategoryId $categoryId): Post;
    public function byTag(TagId $tagId): array;
    public function withComments(PostId $id): Post;
    public function groupedByMonth(): array;
    // ...
}
//end-snippet
