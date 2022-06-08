<?php

namespace Architecture\CQRS\Infrastructure\Projection;

use Spatie\Async\Pool;

use PHPUnit\Framework\TestCase;

use Symfony\Component\Process\Process;

use Architecture\CQRS\Domain\{
    PostWasCreated,
    PostWasPublished,
    PostWasCategorized,
    PostTitleWasChanged,
    PostContentWasChanged
};
use Architecture\CQRS\Infrastructure\Projection\AsyncProjector;
use Architecture\CQRS\Infrastructure\Projection\Elasticsearch\{
    PostWasCreatedProjection,
    PostWasPublishedProjection,
    PostWasCategorizedProjection,
    PostTitleWasChangedProjection,
    PostContentWasChangedProjection
};
use Architecture\CQRS\Infrastructure\Projection\Projector;

class AsyncProjectorTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldProjectIntoElasticsearch(): void
    {
        /*
         * Execute receiver and emitter at the same time
         */
        //Wait to kill the event receiver
        $pool = Pool::create()->timeout(3);

        $pool[] = async(function() {
            sleep(1);//wait for the connection to be ready

            //snippet event-receiver
            $client = \Elasticsearch\ClientBuilder::create()->build();

            $projector = new Projector();
            $projector->register([
                new PostWasCreatedProjection($client),
                new PostWasPublishedProjection($client),
                new PostWasCategorizedProjection($client),
                new PostTitleWasChangedProjection($client),
                new PostContentWasChangedProjection($client)
            ]);

            $serializer = new \Zumba\JsonSerializer\JsonSerializer();

            $bunny = (new \Bunny\Client())->connect();
            $channel = $bunny->channel();
            $channel->exchangeDeclare('events', 'fanout');
            $queue = $channel->queueDeclare('queue');
            $channel->queueBind($queue->queue, 'events');
            $channel->consume(
                function (
                    \Bunny\Message $message,
                    \Bunny\Channel $channel,
                    \Bunny\Client $client
                ) use ($serializer, $projector) {
                    $event = $serializer->unserialize($message->content);
                    $projector->project([$event]);
                },
                $queue->queue
            );
            $bunny->run();
            //end-snippet
        });

        $pool[] = async(function() {
            sleep(2);//wait for the receiver to be launched

            //snippet event-emitter
            $bunny = (new \Bunny\Client())->connect();
            $channel = $bunny->channel();
            $channel->exchangeDeclare('events', 'fanout');

            $serializer = new \Zumba\JsonSerializer\JsonSerializer();

            $postId = PostId::create();
            $categoryId = CategoryId::create();
            $projector = new AsyncProjector($channel, $serializer);
            $projector->project([
                new PostWasCreated($postId, 'A title', 'Some content'),
                new PostWasPublished($postId),
                new PostWasCategorized($postId, $categoryId),
                new PostTitleWasChanged($postId, 'New title'),
                new PostContentWasChanged($postId, 'New content'),
            ]);

            $channel->close();
            $bunny->disconnect();
            //end-snippet
        });

        await($pool);

        $client = \Elasticsearch\ClientBuilder::create()->build();

        $params = [
            'index' => 'posts',
            'type'  => 'post',
            'id'    => 'irrelevant'
        ];

        $document = $client->get($params);
        $this->assertEquals([
            'title' => 'New title',
            'content' => 'New content',
            'is_published' => true,
            'category_id' => 'irrelevant'
        ], $document['_source']);

        $client->delete($params);
        $client->indices()->delete(['index' => 'posts']);
    }
}
