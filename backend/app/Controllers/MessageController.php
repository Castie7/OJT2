<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\MessageService;
use CodeIgniter\API\ResponseTrait;

class MessageController extends BaseController
{
    use ResponseTrait;

    private const DEFAULT_THREAD_LIMIT = 100;
    private const MAX_THREAD_LIMIT = 200;
    private const MAX_MESSAGE_LENGTH = 2000;

    protected $authService;
    protected $messageService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->messageService = new MessageService();
        helper('activity');
    }

    protected function getUser()
    {
        $token = $this->request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    public function users()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        return $this->respond($this->messageService->getAvailableUsers((int) $user->id));
    }

    public function conversations()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$this->messageService->isMessagingEnabled()) {
            return $this->fail('Direct messaging is not enabled yet. Run database migrations first.', 503);
        }

        return $this->respond($this->messageService->getConversations((int) $user->id));
    }

    public function thread($partnerId = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$this->messageService->isMessagingEnabled()) {
            return $this->fail('Direct messaging is not enabled yet. Run database migrations first.', 503);
        }

        $currentUserId = (int) $user->id;
        $partnerId = (int) $partnerId;

        if ($partnerId <= 0) {
            return $this->fail('Invalid partner_id', 400);
        }

        if ($partnerId === $currentUserId) {
            return $this->fail('Cannot open a conversation with yourself.', 400);
        }

        if (!$this->messageService->userExists($partnerId)) {
            return $this->failNotFound('User not found');
        }

        $limit = self::DEFAULT_THREAD_LIMIT;
        $limitParam = $this->request->getGet('limit');

        if ($limitParam !== null && $limitParam !== '') {
            if (!ctype_digit((string) $limitParam)) {
                return $this->fail('Invalid limit. Use a numeric value between 1 and 200.', 400);
            }

            $limit = (int) $limitParam;
            if ($limit < 1 || $limit > self::MAX_THREAD_LIMIT) {
                return $this->fail('Invalid limit. Use a numeric value between 1 and 200.', 400);
            }
        }

        return $this->respond($this->messageService->getThread($currentUserId, $partnerId, $limit));
    }

    public function send()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$this->messageService->isMessagingEnabled()) {
            return $this->fail('Direct messaging is not enabled yet. Run database migrations first.', 503);
        }

        $payload = $this->request->getJSON();
        if (!$payload || !isset($payload->recipient_id) || !isset($payload->message)) {
            return $this->fail('recipient_id and message are required', 400);
        }

        $senderId = (int) $user->id;
        $recipientId = (int) $payload->recipient_id;

        if ($recipientId <= 0) {
            return $this->fail('Invalid recipient_id', 400);
        }

        if ($recipientId === $senderId) {
            return $this->fail('You cannot send a message to yourself.', 400);
        }

        if (!$this->messageService->userExists($recipientId)) {
            return $this->failNotFound('Recipient not found');
        }

        $message = trim((string) $payload->message);
        if ($message === '') {
            return $this->fail('Message cannot be empty', 400);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $this->fail('Message is too long (max 2000 characters).', 400);
        }

        try {
            $saved = $this->messageService->send($senderId, $recipientId, $message);

            if ($saved === null) {
                return $this->failServerError('Failed to send message');
            }

            log_activity(
                $senderId,
                session()->get('name'),
                session()->get('role'),
                'SEND_DIRECT_MESSAGE',
                "Sent direct message to user ID: {$recipientId}"
            );

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Message sent',
                'data' => $saved,
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError('Server Error: ' . $e->getMessage());
        }
    }

    public function markAsRead()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$this->messageService->isMessagingEnabled()) {
            return $this->fail('Direct messaging is not enabled yet. Run database migrations first.', 503);
        }

        $payload = $this->request->getJSON();
        if (!$payload || !isset($payload->partner_id)) {
            return $this->fail('partner_id is required', 400);
        }

        $currentUserId = (int) $user->id;
        $partnerId = (int) $payload->partner_id;

        if ($partnerId <= 0 || $partnerId === $currentUserId) {
            return $this->fail('Invalid partner_id', 400);
        }

        if (!$this->messageService->userExists($partnerId)) {
            return $this->failNotFound('User not found');
        }

        $updated = $this->messageService->markConversationAsRead($currentUserId, $partnerId);

        return $this->respond([
            'status' => 'success',
            'updated' => $updated,
        ]);
    }

    public function markAllAsRead()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$this->messageService->isMessagingEnabled()) {
            return $this->fail('Direct messaging is not enabled yet. Run database migrations first.', 503);
        }

        $updated = $this->messageService->markAllAsRead((int) $user->id);

        return $this->respond([
            'status' => 'success',
            'updated' => $updated,
        ]);
    }
}
