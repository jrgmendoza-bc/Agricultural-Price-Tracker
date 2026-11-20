<?php
/**
 * messaging.php - listing-anchored buyer/farmer messaging, the Help
 * channel (user <-> administrator) and the notification feed.
 */

class Notify
{
    public static function send(int $userId, string $type, string $message): void
    {
        try {
            Database::execute(
                "INSERT INTO notifications (id, type, notifiable_id, data) VALUES (?,?,?,?)",
                [bin2hex(random_bytes(16)), $type, $userId, json_encode(['message' => $message])]
            );
        } catch (Throwable $e) {
            error_log('notify failed: ' . $e->getMessage());
        }
    }
}

class Messaging
{
    /** Conversation threads where $userId is the farmer or the buyer. */
    public static function threadsFor(int $userId): array
    {
        return Database::select(
            "SELECT c.id, c.last_message_at, c.crop_id,
                    cr.quantity, cr.unit_id,
                    p.product_name,
                    u.first_name, u.last_name, u.role AS other_role,
                    (SELECT m.body FROM messages m WHERE m.conversation_id = c.id
                      ORDER BY m.created_at DESC LIMIT 1) AS last_body,
                    (SELECT COUNT(*) FROM messages m
                      WHERE m.conversation_id = c.id AND m.read_at IS NULL AND m.sender_id <> ?) AS unread
               FROM conversations c
               JOIN crops cr   ON cr.id = c.crop_id
               JOIN products p ON p.id = cr.product_id
               JOIN users u    ON u.id = CASE WHEN c.farmer_id = ? THEN c.buyer_id ELSE c.farmer_id END
              WHERE c.farmer_id = ? OR c.buyer_id = ?
              ORDER BY COALESCE(c.last_message_at, c.created_at) DESC",
            [$userId, $userId, $userId, $userId]
        );
    }

    /** One thread, only if $userId participates in it. */
    public static function thread(int $convId, int $userId): ?array
    {
        return Database::first(
            "SELECT c.*, p.product_name, cr.quantity, cr.status AS crop_status,
                    u.first_name, u.last_name
               FROM conversations c
               JOIN crops cr   ON cr.id = c.crop_id
               JOIN products p ON p.id = cr.product_id
               JOIN users u    ON u.id = CASE WHEN c.farmer_id = ? THEN c.buyer_id ELSE c.farmer_id END
              WHERE c.id = ? AND (c.farmer_id = ? OR c.buyer_id = ?)",
            [$userId, $convId, $userId, $userId]
        );
    }

    public static function messages(int $convId): array
    {
        return Database::select(
            "SELECT m.*, u.first_name, u.last_name, u.id AS sender_role_id, us.role AS sender_role
               FROM messages m
               JOIN users u   ON u.id = m.sender_id
               JOIN users us  ON us.id = m.sender_id
              WHERE m.conversation_id = ?
              ORDER BY m.created_at ASC",
            [$convId]
        );
    }

    /** Mark every message the participant did NOT send as read. */
    public static function markRead(int $convId, int $userId): void
    {
        Database::execute(
            "UPDATE messages SET read_at = NOW()
              WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL",
            [$convId, $userId]
        );
    }

    public static function send(int $convId, int $userId, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Message cannot be empty.'];
        }
        if (mb_strlen($body) > 2000) {
            return ['ok' => false, 'error' => 'Message is too long (max 2000 characters).'];
        }
        $thread = self::thread($convId, $userId);
        if (!$thread) {
            return ['ok' => false, 'error' => 'Conversation not found or access denied.'];
        }
        if ($thread['crop_status'] === 'sold') {
            // messaging stays possible about a sold listing (history), nothing to block
        }

        Database::execute(
            "INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)",
            [$convId, $userId, $body]
        );
        Database::execute(
            "UPDATE conversations SET last_message_at = NOW() WHERE id = ?",
            [$convId]
        );

        $otherId = (int)($thread['farmer_id'] === $userId ? $thread['buyer_id'] : $thread['farmer_id']);
        $sender  = Database::first("SELECT first_name FROM users WHERE id = ?", [$userId]);
        Notify::send(
            $otherId,
            'App\\Notifications\\NewMessage',
            'New message from ' . trim(($sender['first_name'] ?? 'someone')) . ' about ' . $thread['product_name'] . '.'
        );

        return ['ok' => true];
    }

    /**
     * Open (or reuse) the buyer's thread about a listing.
     * Buyer must be verified and must not be the listing's own farmer.
     */
    public static function openForListing(int $cropId, int $buyerId): array
    {
        $crop = Database::first(
            "SELECT c.*, p.product_name, u.role AS farmer_role
               FROM crops c
               JOIN products p ON p.id = c.product_id
               JOIN users u    ON u.id = c.farmer_id
              WHERE c.id = ? AND c.deleted_at IS NULL",
            [$cropId]
        );
        if (!$crop) {
            return ['ok' => false, 'error' => 'Listing not found.'];
        }
        if ((int)$crop['farmer_id'] === $buyerId) {
            return ['ok' => false, 'error' => 'This is your own listing.'];
        }
        $buyer = Database::first("SELECT * FROM users WHERE id = ?", [$buyerId]);
        if (!$buyer || $buyer['role'] !== 'buyer' || $buyer['verification_status'] !== 'verified') {
            return ['ok' => false, 'error' => 'Only verified buyers can contact farmers.'];
        }
        if ($crop['status'] === 'sold') {
            return ['ok' => false, 'error' => 'This listing is already sold.'];
        }

        $existing = Database::first(
            "SELECT id FROM conversations WHERE crop_id = ? AND buyer_id = ?",
            [$cropId, $buyerId]
        );
        if ($existing) {
            return ['ok' => true, 'conversation_id' => (int)$existing['id']];
        }

        Database::execute(
            "INSERT INTO conversations (crop_id, farmer_id, buyer_id) VALUES (?,?,?)",
            [$cropId, (int)$crop['farmer_id'], $buyerId]
        );
        $convId = (int)Database::getConnection()->lastInsertId();

        Notify::send(
            (int)$crop['farmer_id'],
            'App\\Notifications\\NewConversation',
            'A verified buyer opened a conversation about your ' . $crop['product_name'] . ' listing.'
        );

        return ['ok' => true, 'conversation_id' => $convId];
    }
}

class HelpDesk
{
    public static function inquiriesFor(int $userId): array
    {
        return Database::select(
            "SELECT i.*,
                    (SELECT COUNT(*) FROM support_messages m WHERE m.support_inquiry_id = i.id
                       AND m.read_at IS NULL AND m.sender_id <> ?) AS unread
               FROM support_inquiries i
              WHERE i.user_id = ?
              ORDER BY COALESCE(i.last_message_at, i.created_at) DESC",
            [$userId, $userId]
        );
    }

    public static function messages(int $inquiryId): array
    {
        return Database::select(
            "SELECT m.*, u.first_name, u.last_name, us.role AS sender_role
               FROM support_messages m
               JOIN users u  ON u.id = m.sender_id
               JOIN users us ON us.id = m.sender_id
              WHERE m.support_inquiry_id = ?
              ORDER BY m.created_at ASC",
            [$inquiryId]
        );
    }

    /** One inquiry joined with its requester (admin view helper). */
    public static function find(int $inquiryId): ?array
    {
        return Database::first(
            "SELECT i.*, u.first_name, u.last_name, u.username, u.email, u.role AS requester_role
               FROM support_inquiries i
               JOIN users u ON u.id = i.user_id
              WHERE i.id = ?",
            [$inquiryId]
        );
    }

    /** Mark messages the viewer did not send as read (owner or admin only). */
    public static function markRead(int $inquiryId, int $userId): void
    {
        $allowed = Database::first(
            "SELECT 1 AS ok FROM users v
               JOIN support_inquiries i ON i.id = ?
              WHERE v.id = ? AND (v.role = 'admin' OR i.user_id = v.id)",
            [$inquiryId, $userId]
        );
        if (!$allowed) {
            return;
        }
        Database::execute(
            "UPDATE support_messages SET read_at = NOW()
              WHERE support_inquiry_id = ? AND sender_id <> ? AND read_at IS NULL",
            [$inquiryId, $userId]
        );
    }

    public static function create(int $userId, string $subject, string $body): array
    {
        $subject = trim($subject);
        $body    = trim($body);
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'error' => 'Both a subject and a message are required.'];
        }
        Database::execute(
            "INSERT INTO support_inquiries (user_id, subject, status, last_message_at) VALUES (?,?, 'open', NOW())",
            [$userId, $subject]
        );
        $inquiryId = (int)Database::getConnection()->lastInsertId();
        Database::execute(
            "INSERT INTO support_messages (support_inquiry_id, sender_id, body) VALUES (?,?,?)",
            [$inquiryId, $userId, $body]
        );
        foreach (Database::select("SELECT id FROM users WHERE role = 'admin' AND deleted_at IS NULL") as $admin) {
            Notify::send((int)$admin['id'], 'App\\Notifications\\NewInquiry', 'A user opened a Help inquiry: ' . $subject);
        }
        return ['ok' => true, 'id' => $inquiryId];
    }

    /** Reply to an inquiry. Users only see/reply to their own threads. */
    public static function reply(int $inquiryId, int $userId, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Message cannot be empty.'];
        }

        $inquiry = Database::first(
            "SELECT i.*, u.role AS owner_role FROM support_inquiries i JOIN users u ON u.id = i.user_id WHERE i.id = ?",
            [$inquiryId]
        );
        if (!$inquiry) {
            return ['ok' => false, 'error' => 'Inquiry not found.'];
        }

        $isAdmin = false;
        $me = Database::first("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($me && $me['role'] === 'admin') {
            $isAdmin = true;
        } elseif ((int)$inquiry['user_id'] !== $userId) {
            return ['ok' => false, 'error' => 'Access denied.'];
        }

        if ($inquiry['status'] === 'closed' && !$isAdmin) {
            return ['ok' => false, 'error' => 'This inquiry is closed. Please open a new one.'];
        }

        Database::execute(
            "INSERT INTO support_messages (support_inquiry_id, sender_id, body) VALUES (?,?,?)",
            [$inquiryId, $userId, $body]
        );
        $newStatus = $isAdmin ? 'answered' : 'open';
        Database::execute(
            "UPDATE support_inquiries SET status = ?, last_message_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$newStatus, $inquiryId]
        );

        if ($isAdmin) {
            Notify::send((int)$inquiry['user_id'], 'App\\Notifications\\SupportReply',
                'The administrator replied to your inquiry: ' . $inquiry['subject']);
        }

        return ['ok' => true];
    }

    /** Admin closes an inquiry; the requester is notified. */
    public static function close(int $inquiryId, int $adminId): bool
    {
        $inq = self::find($inquiryId);
        if (!$inq) {
            return false;
        }
        $changed = Database::execute(
            "UPDATE support_inquiries SET status = 'closed', updated_at = NOW()
              WHERE id = ? AND status <> 'closed'",
            [$inquiryId]
        );
        if ($changed > 0) {
            Notify::send((int)$inq['user_id'], 'App\\Notifications\\SupportClosed',
                'The administrator closed your inquiry: ' . $inq['subject']);
        }
        return true;
    }
}
