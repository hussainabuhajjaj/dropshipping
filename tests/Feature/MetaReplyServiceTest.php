<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MetaReplyStatus;
use App\Models\MetaInboxMessage;
use App\Jobs\SendMetaReplyJob;
use App\Services\AI\DeepSeekClient;
use App\Services\Meta\DispatchMetaReply;
use App\Services\Meta\MetaReplyService;
use App\Services\Meta\MetaWebhookParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaReplyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $fake = new class extends DeepSeekClient {
            public function chat(array $messages, float $temperature = 0.4, ?int $customTimeout = null): string
            {
                return 'Thanks for your message! You can find more details on our store.';
            }
        };

        $this->app->instance(DeepSeekClient::class, $fake);
    }

    public function test_classify_marks_safe_topics_as_safe(): void
    {
        $service = app(MetaReplyService::class);

        $this->assertSame('safe', $service->classify('Hi, how much does this dress cost?'));
        $this->assertSame('safe', $service->classify('Do you have size M?'));
        $this->assertSame('safe', $service->classify('What is the delivery time?'));
    }

    public function test_classify_marks_refund_queries_as_escalate(): void
    {
        $service = app(MetaReplyService::class);

        $this->assertSame('escalate', $service->classify('I want a refund for my order'));
        $this->assertSame('escalate', $service->classify('My item arrived damaged'));
        $this->assertSame('escalate', $service->classify('Where is my order, tracking please'));
    }

    public function test_classify_marks_spam_messages(): void
    {
        $service = app(MetaReplyService::class);

        $this->assertSame('spam', $service->classify('Get free promo offers here'));
    }

    public function test_parser_ingests_a_comment_and_creates_draft(): void
    {
        Queue::fake();
        Config::set('services.meta.auto_reply', false);

        $parser = app(MetaWebhookParser::class);

        $entries = [
            [
                'id' => '17841480584591912',
                'changes' => [
                    [
                        'field' => 'comments',
                        'value' => [
                            'id' => 'comment-123',
                            'from' => ['id' => 'user-1', 'username' => 'tester'],
                            'text' => 'Hi, how much is this?',
                            'media' => [],
                        ],
                    ],
                ],
            ],
        ];

        $count = $parser->process($entries);

        $this->assertSame(1, $count);

        $message = MetaInboxMessage::where('external_id', 'comment-123')->first();
        $this->assertNotNull($message);
        $this->assertSame('comment', $message->channel);
        $this->assertSame('tester', $message->sender_handle);

        $reply = $message->reply;
        $this->assertNotNull($reply);
        $this->assertSame('safe', $reply->classification);
        $this->assertSame(MetaReplyStatus::Draft, $reply->status);
    }

    public function test_parser_ingests_a_direct_message(): void
    {
        Queue::fake();
        Config::set('services.meta.auto_reply', false);

        $parser = app(MetaWebhookParser::class);

        $entries = [
            [
                'id' => '17841480584591912',
                'messaging' => [
                    [
                        'sender' => ['id' => 'ig-user-1'],
                        'recipient' => ['id' => '17841480584591912'],
                        'message' => ['mid' => 'dm-1', 'text' => 'Is delivery to Dakar available?'],
                    ],
                ],
            ],
        ];

        $parser->process($entries);

        $message = MetaInboxMessage::where('external_id', 'dm-1')->first();
        $this->assertNotNull($message);
        $this->assertSame('message', $message->channel);
        $this->assertSame('ig-user-1', $message->sender_id);
    }

    public function test_auto_reply_safe_message_when_enabled(): void
    {
        Queue::fake();
        Config::set('services.meta.auto_reply', true);

        $parser = app(MetaWebhookParser::class);

        $entries = [
            [
                'id' => '17841480584591912',
                'changes' => [
                    [
                        'field' => 'comments',
                        'value' => [
                            'id' => 'comment-auto',
                            'from' => ['id' => 'user-2', 'username' => 'autobuyer'],
                            'text' => 'Hi, how much is this?',
                            'media' => [],
                        ],
                    ],
                ],
            ],
        ];

        $parser->process($entries);

        $reply = MetaInboxMessage::where('external_id', 'comment-auto')->first()->reply;
        $this->assertSame(MetaReplyStatus::Auto, $reply->status);
        $this->assertTrue($reply->auto_send);

        Queue::assertPushed(SendMetaReplyJob::class);
    }

    public function test_send_job_sends_approved_reply(): void
    {
        Queue::fake();

        $message = MetaInboxMessage::create([
            'platform' => 'instagram',
            'channel' => 'comment',
            'external_id' => 'comment-send',
            'sender_id' => 'user-3',
            'text' => 'How much?',
        ]);

        $reply = $message->reply()->create([
            'draft_text' => 'Hi! You can see the price on our store.',
            'classification' => 'safe',
            'auto_send' => false,
            'status' => MetaReplyStatus::Approved,
        ]);

        $job = new SendMetaReplyJob($reply->id);
        Config::set('services.meta.page_access_token', 'test-page-token');
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'reply-1'], 200)]);
        $job->handle($this->app->make(\App\Services\Meta\MetaGraphApiService::class));

        $reply->refresh();
        $this->assertSame(MetaReplyStatus::Sent, $reply->status);
    }
}
