function escapeChatHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildChatFileUrl(path) {
    return '../image/' + encodeURI(String(path || ''));
}

function formatInternalNoteContent(value) {
    return escapeChatHtml(value || '')
        .replace(/(^|[\s(])(@[A-Za-z0-9._-]{2,64})/g, '$1<span class="message-mention">$2</span>')
        .replace(/\r?\n/g, '<br>');
}

// Add a message to the admin chat UI.
function addMessageToUI(message) {
    const chatText = window.adminChatText || {};
    const meLabel = String(chatText.meLabel || 'Me');
    const internalNoteLabel = String(chatText.internalNoteLabel || 'Internal note');

    if ($(`.message[data-id="${message.message_id}"]`).length > 0) {
        if (message.status === 'read' && message.message_type !== 'internal_note') {
            $(`.message[data-id="${message.message_id}"] .message-status`).html('<i class="fa-solid fa-check-double text-primary"></i>');
        }

        return;
    }

    const isInternalNote = message.message_type === 'internal_note';
    const isMe = (message.sender_id == current_user_id && message.sender_type == 'user');
    const messageClass = isInternalNote ? 'internal-note' : (isMe ? 'sent' : 'received');
    const senderName = isMe ? meLabel : (message.sender_name || 'User');

    let contentHTML = '';

    if (isInternalNote) {
        contentHTML = `
            <div class="message-note-label">
                <i class="fa-solid fa-lock"></i>
                <span>${escapeChatHtml(internalNoteLabel)}</span>
                <span class="message-note-author">${escapeChatHtml(senderName)}</span>
            </div>
            <div class="message-content">${formatInternalNoteContent(message.message)}</div>
        `;
    } else if (message.message_type === 'image') {
        const imageUrl = buildChatFileUrl(message.message);
        contentHTML = `<div class="message-content message-image"><a href="${imageUrl}" target="_blank" rel="noopener"><img src="${imageUrl}" class="img-fluid" style="max-width: 200px; border-radius: 8px;"></a></div>`;
    } else if (message.message_type === 'file') {
        const fileUrl = buildChatFileUrl(message.message);
        const filename = String(message.message || '').split('/').pop();
        contentHTML = `<div class="message-content message-file"><a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file"></i> ${escapeChatHtml(filename)}</a></div>`;
    } else {
        contentHTML = `<div class="message-content">${escapeChatHtml(message.message)}</div>`;
    }

    let statusHTML = '';

    if (isMe && !isInternalNote) {
        const statusIcon = message.status === 'read' ? 'fa-check-double text-primary' : 'fa-check';
        statusHTML = `<span class="message-status" style="margin-left: 5px;"><i class="fa-solid ${statusIcon}"></i></span>`;
    }

    const messageHTML = `
        <div class="message ${messageClass}" data-id="${message.message_id}">
            ${isInternalNote ? '' : `<div class="message-info" style="font-size: 10px; margin-bottom: 2px; opacity: 0.8;">${escapeChatHtml(senderName)}</div>`}
            ${contentHTML}
            <div class="message-footer" style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                <div class="message-time">${formatTime(message.created_at)}</div>
                ${statusHTML}
            </div>
        </div>
    `;

    $('#chat-messages-container').append(messageHTML);

    const container = $('#chat-messages-container');
    container.scrollTop(container[0].scrollHeight);
}

function formatTime(datetime) {
    if (!datetime) {
        return '';
    }

    const date = new Date(datetime);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
