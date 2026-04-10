<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * WebSocket 命令常量
 */
enum WsCmd: string
{
    case SUBSCRIBE = 'aibot_subscribe';
    case HEARTBEAT = 'ping';
    case CALLBACK = 'aibot_msg_callback';
    case EVENT_CALLBACK = 'aibot_event_callback';
    case RESPONSE = 'aibot_respond_msg';
    case RESPONSE_WELCOME = 'aibot_respond_welcome_msg';
    case RESPONSE_UPDATE = 'aibot_respond_update_msg';
    case SEND_MSG = 'aibot_send_msg';
    case UPLOAD_MEDIA_INIT = 'aibot_upload_media_init';
    case UPLOAD_MEDIA_CHUNK = 'aibot_upload_media_chunk';
    case UPLOAD_MEDIA_FINISH = 'aibot_upload_media_finish';
}
