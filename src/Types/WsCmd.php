<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * WebSocket 命令常量
 */
enum WsCmd: string
{
    // ------------------- 连接建立阶段 -------------------

    // 建立WebSocket连接
    case SUBSCRIBE = 'aibot_subscribe';


    // ------------------- 进入会话事件 -------------------

    // 事件回调
    case EVENT_CALLBACK = 'aibot_event_callback';

    // 回复欢迎语
    case RESPONSE_WELCOME = 'aibot_respond_welcome_msg';


    // ------------------- 消息回调与流式消息 -------------------

    // 消息回调
    case CALLBACK = 'aibot_msg_callback';

    // 回复流式消息 / 更新流式内容(finish=false) / 完成流式消息(finish=true)
    case RESPONSE = 'aibot_respond_msg';


    // ------------------- 模板卡片交互 -------------------

    // 更新卡片
    case RESPONSE_UPDATE = 'aibot_respond_update_msg';


    // ------------------- 主动推送消息（无回调触发） -------------------

    // 主动发送消息
    case SEND_MSG = 'aibot_send_msg';

    // ------------------- 上传素材 -------------------

    // 初始化上传
    case UPLOAD_MEDIA_INIT = 'aibot_upload_media_init';

    // 上传分片
    case UPLOAD_MEDIA_CHUNK = 'aibot_upload_media_chunk';

    // 上传结束
    case UPLOAD_MEDIA_FINISH = 'aibot_upload_media_finish';


    // ------------------- 心跳 -------------------

    // 心跳
    case HEARTBEAT = 'ping';
}
