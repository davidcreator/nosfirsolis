'use strict';

(function($) {
    $(function() {
        const $navChat = $('#nav-chat');

        if (!$navChat.length) {
            return;
        }

        const apiUrl = String($navChat.data('chatHeaderApi') || '').trim();
        const chatLink = String($navChat.data('chatLink') || '').trim();
        const emptyLabel = String($navChat.data('emptyLabel') || '').trim();
        const viewAllLabel = String($navChat.data('viewAllLabel') || '').trim();
        const browserTitle = String($navChat.data('browserTitle') || 'Direct Chat').trim();
        const $badge = $('#nav-chat-badge');
        const $menu = $('#nav-chat-menu');
        const notificationSound = new Audio('catalog/view/theme/default/sound/notification.mp3');
        const activeChatId = parseInt(window.current_chat_id || 0, 10) || 0;
        const isActiveChatView = activeChatId > 0 && $('#chat-messages-container').length > 0;

        if (!apiUrl || !$menu.length) {
            return;
        }

        let isPolling = false;
        let stopped = false;
        let highlightTimeout = null;
        let attentionTotal = parseInt($navChat.data('attentionTotal'), 10) || 0;
        let latestNotificationId = parseInt($navChat.data('latestNotificationId'), 10) || 0;
        let latestNotificationCreatedAt = String($navChat.data('latestNotificationCreatedAt') || '').trim();
        let hasAttemptedBrowserPermission = false;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderBadge(total) {
            if (!$badge.length) {
                return;
            }

            if (total > 0) {
                $badge.text(total).removeClass('d-none');
            } else {
                $badge.text('').addClass('d-none');
            }
        }

        function renderMenu(notifications) {
            if (!Array.isArray(notifications) || !notifications.length) {
                $menu.html('<span class="dropdown-item text-center">' + escapeHtml(emptyLabel) + '</span>');
                return;
            }

            const itemsHtml = notifications.map(function(notification) {
                const unreadClass = notification.is_unread ? ' fw-semibold' : '';
                const title = escapeHtml(notification.title || '');
                const meta = String(notification.meta || '').trim();

                return ''
                    + '<a href="' + escapeHtml(notification.href || chatLink) + '" class="dropdown-item' + unreadClass + '">'
                    + '  <div>' + title + '</div>'
                    + (meta ? '  <small class="text-muted d-block">' + escapeHtml(meta) + '</small>' : '')
                    + '</a>';
            }).join('');

            $menu.html(itemsHtml + '<a href="' + escapeHtml(chatLink) + '" class="dropdown-item text-center text-primary">' + escapeHtml(viewAllLabel) + '</a>');
        }

        function flashHeaderSignal() {
            $navChat.addClass('nav-chat-has-update');

            clearTimeout(highlightTimeout);
            highlightTimeout = setTimeout(function() {
                $navChat.removeClass('nav-chat-has-update');
            }, 3600);
        }

        function shouldPlaySound(notifications) {
            if (!Array.isArray(notifications) || !notifications.length) {
                return false;
            }

            if (!isActiveChatView) {
                return true;
            }

            const primaryChatId = parseInt(notifications[0].reference_id, 10) || 0;

            return primaryChatId > 0 && primaryChatId !== activeChatId;
        }

        function supportsBrowserNotifications() {
            return 'Notification' in window;
        }

        function shouldShowBrowserNotification(notifications) {
            if (!supportsBrowserNotifications() || Notification.permission !== 'granted' || !document.hidden) {
                return false;
            }

            return shouldPlaySound(notifications);
        }

        function showBrowserNotification(notifications) {
            if (!shouldShowBrowserNotification(notifications)) {
                return;
            }

            const notification = notifications[0] || {};
            const href = String(notification.href || chatLink).trim();
            const title = String(notification.title || '').trim();
            const referenceId = parseInt(notification.reference_id, 10) || 0;
            const browserNotification = new Notification(browserTitle, {
                body: title,
                tag: 'reamur-chat-' + (referenceId || 'general'),
                icon: 'view/image/reamurcms.png'
            });

            browserNotification.onclick = function() {
                window.focus();

                if (href) {
                    window.location.href = href;
                }

                browserNotification.close();
            };

            setTimeout(function() {
                browserNotification.close();
            }, 8000);
        }

        function maybeRequestBrowserPermission() {
            if (hasAttemptedBrowserPermission || !supportsBrowserNotifications() || Notification.permission !== 'default') {
                return;
            }

            hasAttemptedBrowserPermission = true;
            const permissionRequest = Notification.requestPermission();

            if (permissionRequest && typeof permissionRequest.catch === 'function') {
                permissionRequest.catch(function() {});
            }
        }

        function announceRealtimeUpdate(notifications) {
            flashHeaderSignal();

            if (shouldPlaySound(notifications)) {
                notificationSound.currentTime = 0;
                notificationSound.play().catch(function() {});
            }

            showBrowserNotification(notifications);
        }

        function applyPayload(payload) {
            const nextAttentionTotal = parseInt(payload.attention_total, 10) || 0;
            const nextLatestNotificationId = parseInt(payload.latest_notification_id, 10) || 0;
            const nextLatestNotificationCreatedAt = String(payload.latest_notification_created_at || '').trim();
            const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
            const hasNewAttention = nextAttentionTotal > attentionTotal;
            const hasNewSnapshot = nextLatestNotificationId > 0 && (
                nextLatestNotificationId !== latestNotificationId
                || nextLatestNotificationCreatedAt !== latestNotificationCreatedAt
            );

            attentionTotal = nextAttentionTotal;
            latestNotificationId = nextLatestNotificationId;
            latestNotificationCreatedAt = nextLatestNotificationCreatedAt;

            renderBadge(attentionTotal);
            renderMenu(notifications);

            if (hasNewAttention || hasNewSnapshot) {
                announceRealtimeUpdate(notifications);
            }
        }

        function pollChatHeader() {
            if (stopped || isPolling) {
                return;
            }

            isPolling = true;

            $.ajax({
                url: apiUrl,
                type: 'GET',
                dataType: 'json',
                timeout: 25000,
                data: {
                    attention_total: attentionTotal,
                    latest_notification_id: latestNotificationId,
                    latest_notification_created_at: latestNotificationCreatedAt
                }
            }).done(function(json) {
                if (json && json.success) {
                    applyPayload(json);
                }
            }).always(function() {
                isPolling = false;

                if (!stopped) {
                    setTimeout(pollChatHeader, 500);
                }
            });
        }

        renderBadge(attentionTotal);
        pollChatHeader();

        $navChat.on('show.bs.dropdown click', function() {
            $navChat.removeClass('nav-chat-has-update');
            clearTimeout(highlightTimeout);
            maybeRequestBrowserPermission();
        });

        $(window).on('beforeunload', function() {
            stopped = true;
        });
    });
})(jQuery);
