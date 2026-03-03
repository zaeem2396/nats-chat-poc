<?php

declare(strict_types=1);

/**
 * NATS subject structure for nats-chat-poc.
 * See README "Subject structure" table and architecture notes.
 *
 * chat.room.{roomId}.message  - Chat message published when user sends
 * chat.room.{roomId}.deleted  - Reserved
 * chat.room.*.message         - Moderation subscriber (single-level wildcard)
 * chat.room.>                - JetStream stream + analytics (multi-level wildcard)
 * user.rpc.preferences       - RPC: get user notification preferences
 * notifications.email        - Reserved
 */
return [
    'chat' => [
        'room_message' => 'chat.room.%s.message',
        'room_deleted' => 'chat.room.%s.deleted',
        'room_message_wildcard' => 'chat.room.*.message',
        'room_all_wildcard' => 'chat.room.>',
    ],
    'user' => [
        'rpc_preferences' => 'user.rpc.preferences',
    ],
    'notifications' => [
        'email' => 'notifications.email',
    ],
    'dlq' => 'chat.dlq',
];
