<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportAttachmentService;
use App\Domain\Support\Services\SupportChatService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\SupportMessageResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chatService,
        private readonly SupportAttachmentService $attachmentService
    ) {}

    public function start(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'agent' => ['nullable', Rule::in(['auto', 'ai', 'human'])],
            'topic' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
        ]);

        $result = $this->chatService->startConversation(
            $customer,
            (string) ($validated['agent'] ?? 'auto'),
            'web',
            [
                'topic' => $validated['topic'] ?? null,
                'tags' => $validated['tags'] ?? [],
            ]
        );

        /** @var SupportConversation $conversation */
        $conversation = $result['conversation'];

        $messages = $conversation->messages()
            ->where('is_internal_note', false)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $this->chatService->markMessagesReadByCustomer($conversation);

        return response()->json([
            'data' => [
                'session_id' => $conversation->uuid,
                'status' => $conversation->status,
                'agent_type' => $result['agent_type'],
                'welcome' => $result['welcome'],
                'messages' => SupportMessageResource::collection($messages)->resolve(),
                'unread_for_customer' => 0,
            ],
        ]);
    }

    public function respond(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:80'],
            'input' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = SupportConversation::query()
            ->where('uuid', $validated['session_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'Support conversation not found.'], 404);
        }

        $result = $this->chatService->replyToCustomer($conversation, $customer, (string) $validated['input']);

        return response()->json([
            'data' => [
                'session_id' => $conversation->uuid,
                'status' => $conversation->fresh()->status,
                'agent_type' => $result['agent_type'],
                'reply' => $result['reply'],
                'messages' => SupportMessageResource::collection(collect($result['messages']))->resolve(),
            ],
        ]);
    }

    public function forward(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = SupportConversation::query()
            ->where('uuid', $validated['session_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'Support conversation not found.'], 404);
        }

        $result = $this->chatService->forwardToHuman($conversation, $customer, (string) $validated['message']);

        return response()->json([
            'data' => [
                'session_id' => $conversation->uuid,
                'status' => $conversation->fresh()->status,
                'agent_type' => 'human',
                'ack' => $result['ack'],
                'messages' => SupportMessageResource::collection(collect($result['messages']))->resolve(),
            ],
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:80'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $conversation = SupportConversation::query()
            ->where('uuid', $validated['session_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'Support conversation not found.'], 404);
        }

        $messages = $this->chatService->getMessages(
            $conversation,
            isset($validated['after_id']) ? (int) $validated['after_id'] : null,
            (int) ($validated['limit'] ?? 50)
        );

        $this->chatService->markMessagesReadByCustomer($conversation);

        return response()->json([
            'data' => [
                'session_id' => $conversation->uuid,
                'status' => $conversation->status,
                'agent_type' => $conversation->active_agent === 'human' ? 'human' : 'ai',
                'messages' => SupportMessageResource::collection($messages)->resolve(),
                'next_after_id' => optional($messages->last())->id,
                'unread_for_customer' => 0,
            ],
        ]);
    }

    public function attachment(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $maxKb = max(1024, (int) config('support_chat.attachments.max_kb', 10240));
        $mimeRule = $this->attachmentMimeRule();
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:500'],
            'file' => array_values(array_filter(['required', 'file', 'max:' . $maxKb, $mimeRule])),
        ]);

        $conversation = SupportConversation::query()
            ->where('uuid', $validated['session_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'Support conversation not found.'], 404);
        }

        $file = $request->file('file');
        if (! $file) {
            return response()->json(['message' => 'Attachment file is required.'], 422);
        }

        $attachment = $this->attachmentService->store($file);

        $result = $this->chatService->forwardAttachmentToHuman(
            $conversation,
            $customer,
            $attachment,
            isset($validated['message']) ? (string) $validated['message'] : null
        );

        return response()->json([
            'data' => [
                'session_id' => $conversation->uuid,
                'status' => $conversation->fresh()->status,
                'agent_type' => 'human',
                'ack' => $result['ack'],
                'messages' => SupportMessageResource::collection(collect($result['messages']))->resolve(),
            ],
        ]);
    }

    private function attachmentMimeRule(): ?string
    {
        $allowed = $this->attachmentService->allowedMimes();
        if ($allowed === []) {
            return null;
        }

        return 'mimetypes:' . implode(',', $allowed);
    }
}
