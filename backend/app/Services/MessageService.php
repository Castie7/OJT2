<?php

namespace App\Services;

use App\Models\DirectMessageModel;
use App\Models\UserModel;

class MessageService extends BaseService
{
    protected $messageModel;
    protected $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->messageModel = new DirectMessageModel();
        $this->userModel = new UserModel();
    }

    public function getAvailableUsers(int $userId): array
    {
        return $this->userModel
            ->select('id, name, email, role, created_at')
            ->where('id !=', $userId)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function userExists(int $userId): bool
    {
        return $this->userModel
            ->select('id')
            ->where('id', $userId)
            ->first() !== null;
    }

    public function isMessagingEnabled(): bool
    {
        return $this->db->tableExists('direct_messages');
    }

    public function getConversations(int $userId): array
    {
        if (!$this->db->tableExists('direct_messages')) {
            return [];
        }

        $sql = <<<SQL
SELECT
    conv.partner_id AS user_id,
    u.name,
    u.email,
    u.role,
    dm.message AS last_message,
    dm.created_at AS last_message_at,
    COALESCE(unread.unread_count, 0) AS unread_count
FROM (
    SELECT
        CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner_id,
        MAX(id) AS last_message_id
    FROM direct_messages
    WHERE sender_id = ? OR recipient_id = ?
    GROUP BY CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END
) conv
INNER JOIN direct_messages dm ON dm.id = conv.last_message_id
INNER JOIN users u ON u.id = conv.partner_id
LEFT JOIN (
    SELECT sender_id AS partner_id, COUNT(*) AS unread_count
    FROM direct_messages
    WHERE recipient_id = ? AND is_read = 0
    GROUP BY sender_id
) unread ON unread.partner_id = conv.partner_id
ORDER BY dm.created_at DESC, dm.id DESC
SQL;

        return $this->db
            ->query($sql, [$userId, $userId, $userId, $userId, $userId])
            ->getResultArray();
    }

    public function getThread(int $userId, int $partnerId, int $limit = 100): array
    {
        if (!$this->db->tableExists('direct_messages')) {
            return [];
        }

        $rows = $this->db
            ->table('direct_messages dm')
            ->select('dm.id, dm.sender_id, dm.recipient_id, dm.message, dm.is_read, dm.created_at, sender.name AS sender_name, recipient.name AS recipient_name')
            ->join('users sender', 'sender.id = dm.sender_id', 'left')
            ->join('users recipient', 'recipient.id = dm.recipient_id', 'left')
            ->groupStart()
                ->groupStart()
                    ->where('dm.sender_id', $userId)
                    ->where('dm.recipient_id', $partnerId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('dm.sender_id', $partnerId)
                    ->where('dm.recipient_id', $userId)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('dm.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_reverse($rows);
    }

    public function send(int $senderId, int $recipientId, string $message): ?array
    {
        if (!$this->db->tableExists('direct_messages')) {
            return null;
        }

        $messageId = $this->messageModel->insert([
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'message' => $message,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        if (!$messageId) {
            return null;
        }

        return $this->db
            ->table('direct_messages dm')
            ->select('dm.id, dm.sender_id, dm.recipient_id, dm.message, dm.is_read, dm.created_at, sender.name AS sender_name, recipient.name AS recipient_name')
            ->join('users sender', 'sender.id = dm.sender_id', 'left')
            ->join('users recipient', 'recipient.id = dm.recipient_id', 'left')
            ->where('dm.id', (int) $messageId)
            ->get()
            ->getRowArray();
    }

    public function markConversationAsRead(int $userId, int $partnerId): int
    {
        if (!$this->db->tableExists('direct_messages')) {
            return 0;
        }

        $this->messageModel
            ->where('recipient_id', $userId)
            ->where('sender_id', $partnerId)
            ->where('is_read', 0)
            ->set(['is_read' => 1])
            ->update();

        return (int) $this->db->affectedRows();
    }

    public function markAllAsRead(int $userId): int
    {
        if (!$this->db->tableExists('direct_messages')) {
            return 0;
        }

        $this->messageModel
            ->where('recipient_id', $userId)
            ->where('is_read', 0)
            ->set(['is_read' => 1])
            ->update();

        return (int) $this->db->affectedRows();
    }
}
