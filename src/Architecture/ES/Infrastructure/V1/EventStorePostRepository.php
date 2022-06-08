<?php

namespace Architecture\ES\Infrastructure\V1;

use Architecture\CQRS\Domain\PostId;
use Architecture\CQRS\Infrastructure\Projection\Projector;
use Architecture\ES\Domain\Post;
use Architecture\ES\Domain\PostRepository;
use Architecture\ES\Domain\EventStream;
use Architecture\ES\Infrastructure\EventStore;
use Architecture\ES\Infrastructure\Snapshot;
use Architecture\ES\Infrastructure\SnapshotRepository;

class EventStorePostRepository implements PostRepository
{
    private SnapshotRepository $snapshotRepository;
    private EventStore $eventStore;
    private Projector $projector;

    public function __construct(
        SnapshotRepository $snapshotRepository,
        EventStore $eventStore,
        Projector $projector
    ) {
        $this->snapshotRepository = $snapshotRepository;
        $this->eventStore = $eventStore;
        $this->projector = $projector;
    }

    //snippet event-store-post-repository-by-id
    public function byId(PostId $id): Post
    {
        $snapshot = $this->snapshotRepository->byId($id->id());

        if (null === $snapshot) {
            /** @var Post */
            return Post::reconstitute(
                $this->eventStore->getEventsFor($id->id())
            );
        }

        /** @var Post */
        $post = $snapshot->aggregate();

        $post->replay(
            $this->eventStore->fromVersion($id->id(), $snapshot->version())
        );

        return $post;
    }
    //end-snippet

    //snippet event-store-post-repository-save
    public function save(Post $post): void
    {
        $id = $post->id();

        $events = $post->recordedEvents();
        $post->clearEvents();

        $this->eventStore->append(
            new EventStream($id->id(), $events)
        );

        $countOfEvents = $this->eventStore->countEventsFor(
            $id->id()
        );

        $version = (int) ($countOfEvents / 100);

        if (!$this->snapshotRepository->has($id->id(), $version)) {
            $this->snapshotRepository->save(
                $id->id(),
                new Snapshot(
                    $post,
                    $version
                )
            );
        }

        $this->projector->project($events);
    }
    //end-snippet
}