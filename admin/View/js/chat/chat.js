$(document).ready(function() {
    const chat_id = typeof current_chat_id !== 'undefined' ? current_chat_id : 0;
    const token = typeof user_token !== 'undefined' ? '&user_token=' + user_token : '';
    const chatListFilters = window.currentChatListFilters || {};
    const text = window.adminChatText || {
        noResults: 'No conversations found.',
        typingSuffix: 'is typing...',
        assignedToPrefix: 'Assigned to',
        unassigned: 'Unassigned',
        metaSaved: 'Conversation updated.',
        metaSaveError: 'Unable to update the conversation right now.',
        noteSaved: 'Internal note added.',
        noteSaveError: 'Unable to save the internal note right now.',
        noteEmpty: 'Write an internal note before sending.',
        internalNoteLabel: 'Internal note',
        currentTagsLabel: 'Current tags',
        currentDepartmentLabel: 'Current department',
        currentQueueLabel: 'Current queue',
        meLabel: 'Me',
        unrouted: 'Unrouted',
        playbookIdle: 'No immediate automation playbook action is recommended.',
        playbookAssign: 'This conversation is unassigned. Take ownership before the delay grows.',
        playbookReply: 'The internal side owes the next reply. Keep the conversation moving now.',
        playbookEscalate: 'The internal wait is under pressure. Raise priority and answer quickly.',
        playbookFollowUp: 'The customer has been idle for a while. Prepare a follow-up to restart the exchange.',
        playbookResolved: 'The case is resolved. Close it after confirmation or keep monitoring for a reply.',
        playbookFollowUpMessage: 'Just checking in on this request. If you still need help, reply here and we will continue from the same conversation.',
        playbookFollowUpDrafted: 'Follow-up drafted in the message box.',
        playbookFocusReplyLabel: 'Write reply',
        playbookDraftFollowUpLabel: 'Draft follow-up',
        playbookRaisePriorityLabel: 'Raise priority',
        playbookAssignMeLabel: 'Assign to me',
        playbookMarkResolvedLabel: 'Mark resolved',
        engagementWaitNone: 'No pending reply',
        engagementNoClock: 'No active wait clock',
        engagementSinceLabel: 'Since',
        engagementStateIdle: 'Up to date',
        engagementStateFresh: 'Fresh',
        engagementStateAging: 'Needs follow-up',
        engagementStateStale: 'Overdue follow-up',
        engagementWaitTeam: 'Team reply',
        engagementWaitCustomer: 'Customer reply',
        engagementWaitTeammate: 'Teammate reply',
        engagementHintIdle: 'No pending reply clock is running right now.',
        engagementHintTeam: 'The internal team owes the next response.',
        engagementHintCustomer: 'The conversation is waiting on the customer.',
        engagementHintTeammate: 'Another internal participant owes the next response.',
        slaDueAtLabel: 'Due',
        slaCompletedAtLabel: 'Completed',
        slaNoClock: 'Clock not started yet',
        slaHintDefault: 'Automation guidance will appear here as the conversation evolves.',
        slaStateIdle: 'No clock',
        slaStateOnTrack: 'On track',
        slaStateDueSoon: 'Due soon',
        slaStateBreached: 'Breached',
        slaStateMet: 'Met',
        slaStateMissed: 'Missed',
        slaHintReply: 'Reply now to protect the first response commitment.',
        slaHintResolution: 'Advance the case now or escalate the priority to protect the resolution commitment.',
        slaHintFollowUp: 'The conversation is waiting on the customer. Follow up if the pause becomes too long.',
        slaHintResolved: 'Resolution recorded. Close after confirmation or reopen if the customer returns.',
        slaHintReview: 'Keep the conversation moving and update metadata as the case evolves.',
        activityEmpty: 'No recent activity recorded for this conversation.',
        supportsOperationalMeta: false,
        supportsChatRouting: false,
        supportsInternalNotes: false,
        supportsChatTags: false,
        supportsSla: false,
        supportsAuditLogs: false,
        supportsEngagement: false
    };

    let last_message_id = typeof current_chat_last_message_id !== 'undefined'
        ? (parseInt(current_chat_last_message_id, 10) || 0)
        : (parseInt($('#chat-messages-container').find('.message').last().data('id'), 10) || 0);
    let is_typing = false;
    let typing_timeout = null;
    let last_chat_list = [];
    let last_saved_tags = String((window.currentChatMeta && window.currentChatMeta.tag_list) || '').trim();
    const routing = window.currentChatRouting || { departments: [], queues: [] };
    const agents = Array.isArray(window.currentChatAgents) ? window.currentChatAgents : [];

    const $messageInput = $('#chat-message-input');
    const $emojiPicker = $('#emoji-picker');
    const $emojiBtn = $('#chat-emoji-btn');
    const $searchInput = $('#chat-search');
    const $quickReplySelect = $('#chat-quick-reply');
    const $mentionUserSelect = $('#chat-mention-user');
    const $statusSelect = $('#chat-status');
    const $prioritySelect = $('#chat-priority');
    const $departmentSelect = $('#chat-department');
    const $queueSelect = $('#chat-queue');
    const $assigneeSelect = $('#chat-assignee');
    const $tagsInput = $('#chat-tags');
    const $notePanel = $('#chat-note-panel');
    const $noteInput = $('#chat-note-input');
    const $metaFeedback = $('#chat-meta-feedback');
    const $playbookSummary = $('#chat-playbook-summary');
    const $playbookActions = $('#chat-playbook-actions');
    const $engagementHint = $('#chat-engagement-hint');
    const $slaHint = $('#chat-sla-hint');
    const notificationSound = new Audio('catalog/view/theme/default/sound/notification.mp3');

    $emojiBtn.on('click', function(e) {
        e.preventDefault();
        $emojiPicker.toggle();
    });

    $(document).on('click', function(e) {
        if (
            !$emojiPicker.is(e.target) &&
            $emojiPicker.has(e.target).length === 0 &&
            !$emojiBtn.is(e.target) &&
            $emojiBtn.has(e.target).length === 0
        ) {
            $emojiPicker.hide();
        }
    });

    $emojiPicker.on('click', '.emoji', function() {
        insertAtCursor($messageInput[0], $(this).text());
        $emojiPicker.hide();
        $messageInput.trigger('input').focus();
    });

    $searchInput.on('input', function() {
        renderChatList(last_chat_list);
    });

    $('#chat-quick-reply-apply').on('click', function() {
        const selectedOption = $quickReplySelect.find('option:selected');
        const replyText = String(selectedOption.data('message') || '').trim();

        if (!replyText) {
            return;
        }

        insertAtCursor($messageInput[0], replyText);
        $quickReplySelect.val('');
        $messageInput.trigger('input').focus();
    });

    $('#chat-list-container').on('click', '.chat-list-item', function() {
        const href = $(this).data('href');

        if (href) {
            window.location.href = href;
        }
    });

    $('#chat-note-toggle').on('click', function() {
        if (!$notePanel.length) {
            return;
        }

        if ($notePanel.is(':visible')) {
            $notePanel.stop(true, true).slideUp(140);
        } else {
            $notePanel.stop(true, true).slideDown(140, function() {
                $noteInput.trigger('focus');
            });
        }
    });

    $('#chat-insert-mention').on('click', function() {
        const username = String($mentionUserSelect.val() || '').trim();

        if (!username) {
            return;
        }

        if ($notePanel.length && !$notePanel.is(':visible')) {
            $notePanel.stop(true, true).slideDown(140);
        }

        insertAtCursor($noteInput[0], '@' + username + ' ');
        $mentionUserSelect.val('');
        $noteInput.trigger('focus');
    });

    $noteInput.on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            sendInternalNote();
        }
    });

    $('#chat-assign-me').on('click', function() {
        updateChatMeta({
            assigned_user_id: current_user_id
        });
    });

    $('#chat-mark-resolved').on('click', function() {
        updateChatMeta({
            status: 'resolved'
        });
    });

    $statusSelect.on('change', function() {
        updateChatMeta({
            status: $(this).val()
        });
    });

    $prioritySelect.on('change', function() {
        updateChatMeta({
            priority: $(this).val()
        });
    });

    $departmentSelect.on('change', function() {
        const departmentId = parseInt($(this).val(), 10) || 0;
        syncQueueSelect(departmentId, parseInt($queueSelect.val(), 10) || 0);
        syncAssigneeSelect(departmentId, parseInt($queueSelect.val(), 10) || 0, parseInt($assigneeSelect.val(), 10) || 0);
        updateChatMeta({
            department_id: departmentId,
            queue_id: parseInt($queueSelect.val(), 10) || 0
        });
    });

    $queueSelect.on('change', function() {
        syncAssigneeSelect(
            parseInt($departmentSelect.val(), 10) || 0,
            parseInt($(this).val(), 10) || 0,
            parseInt($assigneeSelect.val(), 10) || 0
        );
        updateChatMeta({
            queue_id: parseInt($(this).val(), 10) || 0
        });
    });

    $assigneeSelect.on('change', function() {
        updateChatMeta({
            assigned_user_id: $(this).val()
        });
    });

    $tagsInput.on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveTagsIfChanged();
            $tagsInput.blur();
        }
    });

    $tagsInput.on('change blur', function() {
        saveTagsIfChanged();
    });

    function insertAtCursor(input, textValue) {
        if (!input) {
            return;
        }

        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const value = input.value || '';
        input.value = value.slice(0, start) + textValue + value.slice(end);

        const caret = start + textValue.length;
        input.selectionStart = input.selectionEnd = caret;
    }

    function draftMessage(textValue) {
        const nextValue = String(textValue || '').trim();

        if (!$messageInput.length || !nextValue) {
            return;
        }

        const currentValue = String($messageInput.val() || '').trim();

        if (!currentValue) {
            $messageInput.val(nextValue);
        } else if (currentValue.indexOf(nextValue) === -1) {
            $messageInput.val(currentValue + '\n\n' + nextValue);
        }

        $messageInput.trigger('focus');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cssToken(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '-')
            .replace(/_+/g, '-');
    }

    function getRoutingQueuesForDepartment(departmentId) {
        const normalizedDepartmentId = parseInt(departmentId, 10) || 0;

        return Array.isArray(routing.queues)
            ? routing.queues.filter(function(queue) {
                return normalizedDepartmentId <= 0 || (parseInt(queue.chat_department_id, 10) || 0) === normalizedDepartmentId;
            })
            : [];
    }

    function syncQueueSelect(departmentId, selectedQueueId) {
        if (!$queueSelect.length) {
            return;
        }

        const normalizedDepartmentId = parseInt(departmentId, 10) || 0;
        const normalizedQueueId = parseInt(selectedQueueId, 10) || 0;
        const availableQueues = getRoutingQueuesForDepartment(normalizedDepartmentId);
        let hasSelectedQueue = normalizedQueueId === 0;
        let optionsHtml = '<option value="0">' + escapeHtml(text.unrouted) + '</option>';

        availableQueues.forEach(function(queue) {
            const queueId = parseInt(queue.chat_queue_id, 10) || 0;
            const queueName = String(queue.name || '').trim();
            const selected = queueId === normalizedQueueId ? ' selected' : '';

            if (selected) {
                hasSelectedQueue = true;
            }

            optionsHtml += '<option value="' + queueId + '" data-department-id="' + (parseInt(queue.chat_department_id, 10) || 0) + '"' + selected + '>' + escapeHtml(queueName) + '</option>';
        });

        $queueSelect.html(optionsHtml);

        if (!hasSelectedQueue) {
            $queueSelect.val('0');
        }
    }

    function getAgentQueueIdsForDepartment(agent, departmentId) {
        if (!agent || !departmentId) {
            return [];
        }

        const queueMap = agent.queue_ids_by_department && typeof agent.queue_ids_by_department === 'object'
            ? agent.queue_ids_by_department
            : {};

        return Array.isArray(queueMap[String(departmentId)])
            ? queueMap[String(departmentId)]
            : (Array.isArray(queueMap[departmentId]) ? queueMap[departmentId] : []);
    }

    function canAgentAccessQueue(agent, departmentId, queueId) {
        const normalizedDepartmentId = parseInt(departmentId, 10) || 0;
        const normalizedQueueId = parseInt(queueId, 10) || 0;

        if (normalizedDepartmentId <= 0 || normalizedQueueId <= 0) {
            return true;
        }

        const queueIds = getAgentQueueIdsForDepartment(agent, normalizedDepartmentId).map(function(memberQueueId) {
            return parseInt(memberQueueId, 10) || 0;
        }).filter(Boolean);

        if (!queueIds.length) {
            return true;
        }

        return queueIds.includes(normalizedQueueId);
    }

    function getAgentsForRouting(departmentId, queueId) {
        const normalizedDepartmentId = parseInt(departmentId, 10) || 0;
        const normalizedQueueId = parseInt(queueId, 10) || 0;

        if (normalizedDepartmentId <= 0) {
            return agents;
        }

        return agents.filter(function(agent) {
            const departmentIds = Array.isArray(agent.department_ids) ? agent.department_ids : [];

            const inDepartment = departmentIds.some(function(memberDepartmentId) {
                return (parseInt(memberDepartmentId, 10) || 0) === normalizedDepartmentId;
            });

            if (!inDepartment) {
                return false;
            }

            return canAgentAccessQueue(agent, normalizedDepartmentId, normalizedQueueId);
        });
    }

    function syncAssigneeSelect(departmentId, queueId, selectedAssigneeId) {
        if (!$assigneeSelect.length) {
            return;
        }

        const normalizedAssigneeId = parseInt(selectedAssigneeId, 10) || 0;
        const availableAgents = getAgentsForRouting(departmentId, queueId);
        let hasSelectedAgent = normalizedAssigneeId === 0;
        let optionsHtml = '<option value="0">' + escapeHtml(text.unassigned) + '</option>';

        availableAgents.forEach(function(agent) {
            const agentId = parseInt(agent.user_id, 10) || 0;
            const agentName = String(agent.name || '').trim();
            const selected = agentId === normalizedAssigneeId ? ' selected' : '';

            if (selected) {
                hasSelectedAgent = true;
            }

            optionsHtml += '<option value="' + agentId + '" data-department-ids="' + escapeHtml((Array.isArray(agent.department_ids) ? agent.department_ids : []).join(',')) + '"' + selected + '>' + escapeHtml(agentName) + '</option>';
        });

        $assigneeSelect.html(optionsHtml);

        if (!hasSelectedAgent) {
            $assigneeSelect.val('0');
        }
    }

    function formatChatDateTime(value) {
        const rawValue = String(value || '').trim();

        if (!rawValue) {
            return '';
        }

        const parsed = new Date(rawValue.replace(' ', 'T'));

        if (Number.isNaN(parsed.getTime())) {
            return rawValue;
        }

        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'short',
            timeStyle: 'short'
        }).format(parsed);
    }

    function formatCompactDuration(seconds) {
        const totalSeconds = Math.max(0, parseInt(seconds, 10) || 0);

        if (totalSeconds < 60) {
            return '0m';
        }

        if (totalSeconds < 3600) {
            return Math.floor(totalSeconds / 60) + 'm';
        }

        if (totalSeconds < 86400) {
            return Math.max(1, Math.floor(totalSeconds / 3600)) + 'h';
        }

        return Math.max(1, Math.floor(totalSeconds / 86400)) + 'd';
    }

    function getEngagementOwnerLabel(owner) {
        switch (String(owner || 'none')) {
            case 'team':
                return text.engagementWaitTeam;
            case 'customer':
                return text.engagementWaitCustomer;
            case 'teammate':
                return text.engagementWaitTeammate;
            case 'none':
            default:
                return text.engagementWaitNone;
        }
    }

    function getEngagementStateLabel(state) {
        switch (String(state || 'idle')) {
            case 'fresh':
                return text.engagementStateFresh;
            case 'aging':
                return text.engagementStateAging;
            case 'stale':
                return text.engagementStateStale;
            case 'idle':
            default:
                return text.engagementStateIdle;
        }
    }

    function getEngagementHint(engagement) {
        switch (String((engagement && engagement.wait_owner) || 'none')) {
            case 'team':
                return text.engagementHintTeam;
            case 'customer':
                return text.engagementHintCustomer;
            case 'teammate':
                return text.engagementHintTeammate;
            case 'none':
            default:
                return text.engagementHintIdle;
        }
    }

    function getNextPriority(priority) {
        switch (String(priority || 'normal')) {
            case 'low':
                return 'normal';
            case 'normal':
                return 'high';
            case 'high':
                return 'urgent';
            case 'urgent':
            default:
                return '';
        }
    }

    function getSlaStateLabel(state) {
        switch (String(state || 'idle')) {
            case 'on_track':
                return text.slaStateOnTrack;
            case 'due_soon':
                return text.slaStateDueSoon;
            case 'breached':
                return text.slaStateBreached;
            case 'met':
                return text.slaStateMet;
            case 'missed':
                return text.slaStateMissed;
            case 'idle':
            default:
                return text.slaStateIdle;
        }
    }

    function getSlaHint(meta) {
        const status = String((meta && meta.status) || '');
        const sla = meta && meta.sla ? meta.sla : {};
        const firstResponse = sla.first_response || {};
        const resolution = sla.resolution || {};

        if (firstResponse.is_active && ['on_track', 'due_soon', 'breached'].indexOf(String(firstResponse.state || '')) !== -1) {
            return text.slaHintReply;
        }

        if (resolution.is_active && ['due_soon', 'breached'].indexOf(String(resolution.state || '')) !== -1) {
            return text.slaHintResolution;
        }

        if (status === 'waiting_customer') {
            return text.slaHintFollowUp;
        }

        if (status === 'resolved' || status === 'closed') {
            return text.slaHintResolved;
        }

        return text.slaHintReview || text.slaHintDefault;
    }

    function renderSlaMetric($card, metric) {
        if (!$card.length) {
            return;
        }

        const state = String((metric && metric.state) || 'idle');
        let timeLabel = text.slaNoClock;

        if (metric && metric.completed_at) {
            timeLabel = text.slaCompletedAtLabel + ': ' + formatChatDateTime(metric.completed_at);
        } else if (metric && metric.due_at) {
            timeLabel = text.slaDueAtLabel + ': ' + formatChatDateTime(metric.due_at);
        }

        $card
            .attr('class', 'chat-sla-card chat-sla-card-' + cssToken(state))
            .find('[data-role="state"]').text(getSlaStateLabel(state));

        $card.find('[data-role="time"]').text(timeLabel);
    }

    function renderSlaPanel(meta) {
        if (!text.supportsSla) {
            return;
        }

        const sla = meta && meta.sla ? meta.sla : {};
        renderSlaMetric($('#chat-sla-first-response'), sla.first_response || {});
        renderSlaMetric($('#chat-sla-resolution'), sla.resolution || {});

        if ($slaHint.length) {
            $slaHint.text(getSlaHint(meta));
        }
    }

    function renderSlaBadge(sla) {
        if (!text.supportsSla || !sla) {
            return '';
        }

        const summaryState = String(sla.summary_state || 'idle');

        if (!summaryState || summaryState === 'idle') {
            return '';
        }

        return '<span class="chat-pill chat-pill-sla chat-pill-sla-' + cssToken(summaryState) + '">' + escapeHtml(getSlaStateLabel(summaryState)) + '</span>';
    }

    function renderEngagementBadge(engagement) {
        if (!text.supportsEngagement || !engagement) {
            return '';
        }

        const waitOwner = String(engagement.wait_owner || 'none');
        const waitState = String(engagement.wait_state || 'idle');
        const waitSeconds = parseInt(engagement.wait_seconds, 10);

        if (waitOwner === 'none' || Number.isNaN(waitSeconds)) {
            return '';
        }

        return '<span class="chat-pill chat-pill-engagement chat-pill-engagement-' + cssToken(waitState) + '">' + escapeHtml(getEngagementOwnerLabel(waitOwner) + ' · ' + formatCompactDuration(waitSeconds)) + '</span>';
    }

    function renderActivityPanel(entries) {
        if (!text.supportsAuditLogs) {
            return;
        }

        const $activityList = $('#chat-activity-list');

        if (!$activityList.length) {
            return;
        }

        if (!Array.isArray(entries) || !entries.length) {
            $activityList.html('<div class="chat-activity-empty">' + escapeHtml(text.activityEmpty) + '</div>');
            return;
        }

        const html = entries.map(function(entry) {
            const actorName = String(entry.actor_name || '').trim();
            const createdAtLabel = String(entry.created_at_label || '').trim();
            const metaParts = [];

            if (actorName) {
                metaParts.push(escapeHtml(actorName));
            }

            if (createdAtLabel) {
                metaParts.push(escapeHtml(createdAtLabel));
            }

            return `
                <div class="chat-activity-item">
                    <div class="chat-activity-summary">${escapeHtml(entry.summary || '')}</div>
                    <div class="chat-activity-meta">${metaParts.join(' &bull; ')}</div>
                </div>
            `;
        }).join('');

        $activityList.html(html);
    }

    function buildPlaybook(meta) {
        const engagement = meta && meta.engagement ? meta.engagement : {};
        const sla = meta && meta.sla ? meta.sla : {};
        const status = String((meta && meta.status) || '');
        const priority = String((meta && meta.priority) || 'normal');
        const assignedUserId = parseInt((meta && meta.assigned_user_id) || 0, 10) || 0;
        const waitOwner = String(engagement.wait_owner || 'none');
        const waitState = String(engagement.wait_state || 'idle');
        const hasCustomerParticipant = parseInt(engagement.customer_participant_total, 10) > 0;
        const nextPriority = getNextPriority(priority);
        const firstResponseState = String((((sla || {}).first_response || {}).state) || 'idle');
        const resolutionState = String((((sla || {}).resolution || {}).state) || 'idle');
        const needsEscalation = ['due_soon', 'breached'].indexOf(firstResponseState) !== -1
            || ['due_soon', 'breached'].indexOf(resolutionState) !== -1
            || waitState === 'stale';
        const actions = [];
        let summary = text.playbookIdle;

        if (status === 'resolved' || status === 'closed') {
            return {
                summary: text.playbookResolved,
                actions: []
            };
        }

        if (!assignedUserId && waitOwner !== 'none') {
            summary = text.playbookAssign;
            actions.push({ type: 'assign_me', label: text.playbookAssignMeLabel, className: 'btn-primary' });

            if ((waitOwner === 'team' || waitOwner === 'teammate') && nextPriority) {
                actions.push({ type: 'raise_priority', label: text.playbookRaisePriorityLabel, className: 'btn-outline-warning' });
            }

            actions.push({ type: 'focus_reply', label: text.playbookFocusReplyLabel, className: 'btn-outline-secondary' });

            return { summary: summary, actions: actions };
        }

        if (waitOwner === 'team' || waitOwner === 'teammate') {
            summary = needsEscalation ? text.playbookEscalate : text.playbookReply;
            actions.push({ type: 'focus_reply', label: text.playbookFocusReplyLabel, className: 'btn-primary' });

            if (nextPriority && needsEscalation) {
                actions.push({ type: 'raise_priority', label: text.playbookRaisePriorityLabel, className: 'btn-outline-warning' });
            }

            return { summary: summary, actions: actions };
        }

        if (waitOwner === 'customer' && hasCustomerParticipant && (waitState === 'aging' || waitState === 'stale')) {
            summary = text.playbookFollowUp;
            actions.push({ type: 'draft_follow_up', label: text.playbookDraftFollowUpLabel, className: 'btn-outline-secondary' });

            if (waitState === 'stale') {
                actions.push({ type: 'mark_resolved', label: text.playbookMarkResolvedLabel, className: 'btn-outline-success' });
            }

            return { summary: summary, actions: actions };
        }

        return {
            summary: summary,
            actions: actions
        };
    }

    function renderPlaybook(meta) {
        if (!$playbookSummary.length || !$playbookActions.length) {
            return;
        }

        const plan = buildPlaybook(meta || {});
        const actions = Array.isArray(plan.actions) ? plan.actions : [];

        $playbookSummary.text(String(plan.summary || text.playbookIdle));

        if (!actions.length) {
            $playbookActions.empty();
            return;
        }

        $playbookActions.html(actions.map(function(action) {
            const actionType = String((action && action.type) || '').trim();
            const label = String((action && action.label) || '').trim();
            const className = String((action && action.className) || 'btn-outline-secondary').trim();

            if (!actionType || !label) {
                return '';
            }

            return '<button type="button" class="btn btn-sm ' + escapeHtml(className) + '" data-action="' + escapeHtml(actionType) + '">' + escapeHtml(label) + '</button>';
        }).join(''));
    }

    function renderEngagementPanel(meta) {
        if (!text.supportsEngagement) {
            return;
        }

        const engagement = meta && meta.engagement ? meta.engagement : {};
        const waitOwner = String(engagement.wait_owner || 'none');
        const waitState = String(engagement.wait_state || 'idle');
        const waitSeconds = parseInt(engagement.wait_seconds, 10);
        const waitStartedAt = String(engagement.wait_started_at || '').trim();
        const ownerLabel = getEngagementOwnerLabel(waitOwner);
        const waitLabel = waitOwner === 'none' || Number.isNaN(waitSeconds)
            ? text.engagementNoClock
            : formatCompactDuration(waitSeconds);
        const waitMetaLabel = waitStartedAt
            ? text.engagementSinceLabel + ': ' + formatChatDateTime(waitStartedAt)
            : text.engagementNoClock;

        $('#chat-engagement-owner')
            .attr('class', 'chat-engagement-card chat-engagement-card-' + cssToken(waitState))
            .find('[data-role="value"]').text(ownerLabel);

        $('#chat-engagement-owner').find('[data-role="meta"]').text(getEngagementStateLabel(waitState));

        $('#chat-engagement-wait')
            .attr('class', 'chat-engagement-card chat-engagement-card-' + cssToken(waitState))
            .find('[data-role="value"]').text(waitLabel);

        $('#chat-engagement-wait').find('[data-role="meta"]').text(waitMetaLabel);

        if ($engagementHint.length) {
            $engagementHint.text(getEngagementHint(engagement));
        }
    }

    function getPresenceLabel(state) {
        switch (String(state || 'offline')) {
            case 'online':
                return text.onlineNow;
            case 'away':
                return text.away;
            case 'offline':
            default:
                return text.offline;
        }
    }

    function buildOperationalStatusText(meta) {
        if (!text.supportsOperationalMeta || !meta) {
            return '';
        }

        const sla = meta.sla || {};
        const slaText = text.supportsSla && sla.summary_state && sla.summary_state !== 'idle'
            ? getSlaStateLabel(sla.summary_state)
            : '';

        return [
            String(meta.status_label || meta.status || '').trim(),
            text.supportsChatRouting ? (String(meta.queue_name || '').trim() || String(meta.department_name || '').trim() || text.unrouted) : '',
            String(meta.assigned_user_name || '').trim() || text.unassigned,
            slaText
        ].filter(Boolean).join(' | ');
    }

    function renderActiveChatStatus(meta, fallbackStatusText) {
        const $status = $('#active-chat-status');

        if (!$status.length) {
            return;
        }

        const fallback = String(fallbackStatusText || '').trim();
        const presence = Array.isArray(meta && meta.presence) ? meta.presence : [];

        if (!text.supportsPresence || !presence.length) {
            $status.removeClass('has-presence').text(fallback || text.presenceUnknown);
            return;
        }

        if (presence.length === 1) {
            const participant = presence[0] || {};
            const state = String(participant.presence_state || 'offline');
            const label = getPresenceLabel(state);
            const lastSeenAt = String(participant.last_seen_at || '').trim();
            const lastSeenLabel = lastSeenAt && state !== 'online'
                ? text.lastSeenLabel + ': ' + formatChatDateTime(lastSeenAt)
                : '';

            $status
                .addClass('has-presence')
                .html(
                    '<span class="chat-presence-line">'
                    + '<span class="chat-presence-dot chat-presence-dot-' + cssToken(state) + '"></span>'
                    + '<span class="chat-presence-label">' + escapeHtml(label) + '</span>'
                    + (lastSeenLabel ? '<span class="chat-presence-separator">&bull;</span><span class="chat-presence-time">' + escapeHtml(lastSeenLabel) + '</span>' : '')
                    + '</span>'
                );

            return;
        }

        const counts = {
            online: 0,
            away: 0,
            offline: 0
        };

        presence.forEach(function(participant) {
            const state = String((participant && participant.presence_state) || 'offline');

            if (Object.prototype.hasOwnProperty.call(counts, state)) {
                counts[state] += 1;
            } else {
                counts.offline += 1;
            }
        });

        const primaryState = counts.online > 0 ? 'online' : (counts.away > 0 ? 'away' : 'offline');
        const summaryParts = [];

        if (counts.online > 0) {
            summaryParts.push(counts.online + ' ' + String(text.online || '').toLowerCase());
        }

        if (counts.away > 0) {
            summaryParts.push(counts.away + ' ' + String(text.away || '').toLowerCase());
        }

        if (counts.offline > 0) {
            summaryParts.push(counts.offline + ' ' + String(text.offline || '').toLowerCase());
        }

        $status
            .addClass('has-presence')
            .html(
                '<span class="chat-presence-line">'
                + '<span class="chat-presence-dot chat-presence-dot-' + cssToken(primaryState) + '"></span>'
                + '<span class="chat-presence-label">' + escapeHtml(summaryParts.join(' | ') || text.presenceUnknown) + '</span>'
                + '</span>'
            );
    }

    function showMetaFeedback(message, isError) {
        $metaFeedback
            .text(message || '')
            .toggleClass('is-error', !!isError)
            .addClass('is-visible');

        clearTimeout(showMetaFeedback.timeoutId);
        showMetaFeedback.timeoutId = setTimeout(function() {
            $metaFeedback.removeClass('is-visible');
        }, 2200);
    }

    function applyChatMeta(meta) {
        if ((!text.supportsOperationalMeta && !text.supportsChatRouting && !text.supportsChatTags && !text.supportsSla && !text.supportsPresence && !text.supportsEngagement) || !meta) {
            return;
        }

        window.currentChatMeta = Object.assign({}, window.currentChatMeta || {}, meta);

        const status = String(meta.status || 'new');
        const priority = String(meta.priority || 'normal');
        const departmentId = parseInt(meta.chat_department_id, 10) || 0;
        const departmentName = String(meta.department_name || '').trim();
        const queueId = parseInt(meta.chat_queue_id, 10) || 0;
        const queueName = String(meta.queue_name || '').trim();
        const assignedUserId = parseInt(meta.assigned_user_id, 10) || 0;
        const assignedUserName = String(meta.assigned_user_name || '');
        const statusLabel = String(meta.status_label || status);
        const priorityLabel = String(meta.priority_label || priority);
        const tagList = String(meta.tag_list || '').trim();
        last_saved_tags = tagList;

        if ($statusSelect.length) {
            $statusSelect.val(status);
        }

        if ($prioritySelect.length) {
            $prioritySelect.val(priority);
        }

        if ($departmentSelect.length) {
            $departmentSelect.val(String(departmentId));
        }

        if ($queueSelect.length) {
            syncQueueSelect(departmentId, queueId);
            $queueSelect.val(String(queueId || 0));
        }

        if ($assigneeSelect.length) {
            syncAssigneeSelect(departmentId, queueId, assignedUserId);
            $assigneeSelect.val(String(assignedUserId));

            if (assignedUserId > 0 && $assigneeSelect.find('option[value="' + assignedUserId + '"]').length === 0) {
                $assigneeSelect.append('<option value="' + assignedUserId + '">' + escapeHtml(assignedUserName) + '</option>');
                $assigneeSelect.val(String(assignedUserId));
            }
        }

        $('#chat-status-pill')
            .attr('class', 'chat-pill chat-pill-status chat-pill-status-' + cssToken(status))
            .text($('#chat-status-pill').text().split(':')[0] + ': ' + statusLabel);

        $('#chat-priority-pill')
            .attr('class', 'chat-pill chat-pill-priority chat-pill-priority-' + cssToken(priority))
            .text($('#chat-priority-pill').text().split(':')[0] + ': ' + priorityLabel);

        $('#chat-department-label').text(departmentName || text.unrouted);
        $('#chat-queue-label').text(queueName || text.unrouted);

        $('#chat-assignee-label').text(assignedUserName || text.unassigned);

        if ($tagsInput.length) {
            $tagsInput.val(tagList);
        }

        $('#chat-tags-label').text(tagList || '-');
        renderPlaybook(meta);
        renderEngagementPanel(meta);
        renderSlaPanel(meta);
        renderActivityPanel(meta.activity || []);
        renderActiveChatStatus(meta, buildOperationalStatusText(meta));
    }

    function saveTagsIfChanged() {
        const tags = String($tagsInput.val() || '').trim();

        if (!text.supportsChatTags || tags === last_saved_tags) {
            return;
        }

        updateChatMeta({
            tags: tags
        });
    }

    function updateChatMeta(payload) {
        if (!chat_id || (!text.supportsOperationalMeta && !text.supportsChatRouting && !text.supportsChatTags && !text.supportsSla && !text.supportsPresence && !text.supportsEngagement)) {
            return;
        }

        payload.chat_id = chat_id;

        $.ajax({
            url: 'index.php?route=api/chat_meta' + token,
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function(json) {
                if (json['success']) {
                    const meta = Object.assign({}, json['meta'] || {}, buildMetaLabels(json['meta'] || {}));
                    applyChatMeta(meta);
                    loadChatList();
                    showMetaFeedback(text.metaSaved, false);
                } else {
                    showMetaFeedback(json['error'] || text.metaSaveError, true);
                }
            },
            error: function() {
                showMetaFeedback(text.metaSaveError, true);
            }
        });
    }

    function buildMetaLabels(meta) {
        const statusLabel = $statusSelect.find('option[value="' + String(meta.status || '') + '"]').text();
        const priorityLabel = $prioritySelect.find('option[value="' + String(meta.priority || '') + '"]').text();

        return {
            status_label: statusLabel || String(meta.status || ''),
            priority_label: priorityLabel || String(meta.priority || '')
        };
    }

    function loadChatMeta() {
        if (!chat_id || (!text.supportsOperationalMeta && !text.supportsChatRouting && !text.supportsChatTags && !text.supportsSla && !text.supportsPresence && !text.supportsEngagement)) {
            return;
        }

        $.ajax({
            url: 'index.php?route=api/chat_meta' + token,
            type: 'GET',
            data: { chat_id: chat_id },
            dataType: 'json',
            success: function(json) {
                if (json['success']) {
                    applyChatMeta(Object.assign({}, json['meta'] || {}, buildMetaLabels(json['meta'] || {})));
                }
            }
        });
    }

    function renderChatList(chats) {
        const query = String($searchInput.val() || '').trim().toLowerCase();
        const filtered = query === ''
            ? chats
            : chats.filter(function(chat) {
                return String(chat.participants || '').toLowerCase().indexOf(query) !== -1
                    || String(chat.last_message || '').toLowerCase().indexOf(query) !== -1;
            });

        if (!filtered.length) {
            $('#chat-list-container').html('<div class="text-center p-4 text-muted">' + escapeHtml(text.noResults) + '</div>');
            return;
        }

        let html = '';

        filtered.forEach(function(chat) {
            const active = chat.chat_id == chat_id ? 'active' : '';
            const unread = chat.unread > 0 ? '<span class="badge bg-primary rounded-pill">' + escapeHtml(chat.unread) + '</span>' : '';
            const statusBadge = text.supportsOperationalMeta
                ? '<span class="chat-pill chat-pill-status chat-pill-status-' + cssToken(chat.status) + '">' + escapeHtml(chat.status_label) + '</span>'
                : '';
            const priorityBadge = text.supportsOperationalMeta
                ? '<span class="chat-pill chat-pill-priority chat-pill-priority-' + cssToken(chat.priority) + '">' + escapeHtml(chat.priority_label) + '</span>'
                : '';
            const assignee = text.supportsOperationalMeta && chat.assigned_user_name
                ? '<div class="chat-item-assignee">' + escapeHtml(text.assignedToPrefix) + ': ' + escapeHtml(chat.assigned_user_name) + '</div>'
                : '';
            const routingSummary = text.supportsChatRouting
                ? [String(chat.department_name || '').trim(), String(chat.queue_name || '').trim()].filter(Boolean).join(' / ')
                : '';
            const routingBadge = text.supportsChatRouting && routingSummary
                ? '<div class="chat-item-routing">' + escapeHtml(routingSummary) + '</div>'
                : '';
            const tags = text.supportsChatTags && chat.tag_list
                ? '<div class="chat-item-tags">' + String(chat.tag_list).split(',').map(function(tag) {
                    return '<span class="chat-pill chat-pill-tag">' + escapeHtml(String(tag).trim()) + '</span>';
                }).join('') + '</div>'
                : '';
            const slaBadge = renderSlaBadge(chat.sla || {});
            const engagementBadge = renderEngagementBadge(chat.engagement || {});

            html += `
                <div class="chat-list-item ${active}" data-href="${escapeHtml(chat.view)}">
                    <div class="chat-avatar">${escapeHtml(chat.initials)}</div>
                    <div class="chat-item-info">
                        <div class="chat-item-header">
                            <span class="chat-item-name">${escapeHtml(chat.participants)}</span>
                            <span class="chat-item-time">${escapeHtml(chat.time)}</span>
                        </div>
                        <div class="chat-item-last">${escapeHtml(chat.last_message)}</div>
                        ${routingBadge}
                        ${(text.supportsOperationalMeta || text.supportsChatRouting || text.supportsChatTags || text.supportsSla || text.supportsEngagement) ? `<div class="chat-item-meta">${statusBadge}${priorityBadge}${slaBadge}${engagementBadge}${assignee}${tags}</div>` : ''}
                    </div>
                    ${unread}
                </div>
            `;
        });

        $('#chat-list-container').html(html);
    }

    function syncActiveChatMeta(chats) {
        if (!chat_id) {
            return;
        }

        const activeChat = chats.find(function(chat) {
            return chat.chat_id == chat_id;
        });

        if (!activeChat) {
            return;
        }

        $('#active-chat-avatar').text(String(activeChat.initials || '?'));
        $('#active-chat-name').text(String(activeChat.participants || ''));
        window.currentChatMeta = Object.assign({}, window.currentChatMeta || {}, activeChat);
        renderPlaybook(window.currentChatMeta);
        renderActiveChatStatus(window.currentChatMeta, buildOperationalStatusText(activeChat));
        renderEngagementPanel(window.currentChatMeta);
    }

    function loadChatList() {
        $.ajax({
            url: 'index.php?route=communication/chat.getChatList' + token,
            data: chatListFilters,
            dataType: 'json',
            success: function(json) {
                last_chat_list = Array.isArray(json['chats']) ? json['chats'] : [];
                renderChatList(last_chat_list);
                syncActiveChatMeta(last_chat_list);
            }
        });
    }

    function fetchMessages() {
        if (!chat_id) {
            return;
        }

        $.ajax({
            url: 'index.php?route=api/chat_fetch' + token,
            type: 'GET',
            data: { chat_id: chat_id, last_message_id: last_message_id },
            dataType: 'json',
            success: function(json) {
                if (!json['success']) {
                    return;
                }

                if (Array.isArray(json['messages']) && json['messages'].length > 0) {
                    json['messages'].forEach(function(message) {
                        if (message.message_id > last_message_id && (message.sender_id != current_user_id || message.sender_type !== 'user')) {
                            notificationSound.play().catch(function() {});
                        }

                        addMessageToUI(message);
                        last_message_id = Math.max(last_message_id, parseInt(message.message_id, 10) || 0);
                    });

                    loadChatList();
                    loadChatMeta();
                }

                if (Array.isArray(json['presence'])) {
                    window.currentChatMeta = Object.assign({}, window.currentChatMeta || {}, {
                        presence: json['presence']
                    });
                    renderActiveChatStatus(window.currentChatMeta, buildOperationalStatusText(window.currentChatMeta || {}));
                }

                if (Array.isArray(json['typing']) && json['typing'].length > 0) {
                    const typingNames = json['typing'].map(function(participant) {
                        return participant.name;
                    }).filter(Boolean).join(', ');

                    $('.typing-indicator').text(typingNames + ' ' + text.typingSuffix).show();
                } else {
                    $('.typing-indicator').hide();
                }
            },
            complete: function() {
                setTimeout(fetchMessages, 500);
            }
        });
    }

    function sendTypingStatus(status) {
        if (!chat_id) {
            return;
        }

        $.ajax({
            url: 'index.php?route=api/chat_typing' + token,
            type: 'POST',
            data: { chat_id: chat_id, status: status },
            dataType: 'json'
        });
    }

    $messageInput.on('input', function() {
        if (!is_typing) {
            is_typing = true;
            sendTypingStatus('typing');
        }

        clearTimeout(typing_timeout);
        typing_timeout = setTimeout(function() {
            is_typing = false;
            sendTypingStatus('stop');
        }, 3000);
    });

    function sendMessage() {
        const message = $messageInput.val();

        if (message.trim() !== '' && chat_id) {
            $.ajax({
                url: 'index.php?route=api/chat_send' + token,
                type: 'POST',
                data: { chat_id: chat_id, message: message, message_type: 'text' },
                dataType: 'json',
                success: function(json) {
                    if (json['success']) {
                        $messageInput.val('');
                        is_typing = false;
                        sendTypingStatus('stop');
                        loadChatMeta();
                    }
                }
            });
        }
    }

    function recoverJsonResponse(xhr) {
        if (!xhr || typeof xhr.responseText !== 'string') {
            return null;
        }

        const raw = String(xhr.responseText || '').trim();

        if (!raw) {
            return null;
        }

        try {
            const parsed = JSON.parse(raw);

            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            // ignore and try extracting the JSON payload from noisy output
        }

        const firstBrace = raw.indexOf('{');
        const lastBrace = raw.lastIndexOf('}');

        if (firstBrace === -1 || lastBrace <= firstBrace) {
            return null;
        }

        try {
            const parsed = JSON.parse(raw.slice(firstBrace, lastBrace + 1));

            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function handleInternalNoteSaved() {
        $noteInput.val('');
        $notePanel.stop(true, true).slideUp(140);
        loadChatList();
        showMetaFeedback(text.noteSaved, false);
    }

    function sendInternalNote() {
        const note = String($noteInput.val() || '').trim();

        if (!chat_id || !$noteInput.length) {
            return;
        }

        if (note === '') {
            showMetaFeedback(text.noteEmpty, true);
            return;
        }

        $.ajax({
            url: 'index.php?route=api/chat_send' + token,
            type: 'POST',
            data: { chat_id: chat_id, message: note, message_type: 'internal_note' },
            dataType: 'json',
            success: function(json) {
                if (json['success']) {
                    handleInternalNoteSaved();
                } else {
                    showMetaFeedback(json['error'] || text.noteSaveError, true);
                }
            },
            error: function(xhr) {
                const recovered = recoverJsonResponse(xhr);

                if (recovered && recovered['success']) {
                    handleInternalNoteSaved();
                    return;
                }

                showMetaFeedback((recovered && recovered['error']) || text.noteSaveError, true);
            }
        });
    }

    window.sendAdminInternalNote = sendInternalNote;

    $('#send-message-btn').on('click', sendMessage);
    $messageInput.on('keypress', function(e) {
        if (e.which === 13) {
            sendMessage();
        }
    });

    $playbookActions.on('click', '[data-action]', function() {
        const action = String($(this).data('action') || '').trim();

        switch (action) {
            case 'assign_me':
                updateChatMeta({ assigned_user_id: current_user_id });
                break;
            case 'raise_priority': {
                const nextPriority = getNextPriority(String(((window.currentChatMeta || {}).priority) || $prioritySelect.val() || 'normal'));

                if (nextPriority) {
                    updateChatMeta({ priority: nextPriority });
                }
                break;
            }
            case 'draft_follow_up':
                draftMessage(text.playbookFollowUpMessage);
                showMetaFeedback(text.playbookFollowUpDrafted, false);
                break;
            case 'mark_resolved':
                updateChatMeta({ status: 'resolved' });
                break;
            case 'focus_reply':
                $messageInput.trigger('focus');
                break;
            default:
                break;
        }
    });

    $('#chat-attachment-btn').on('click', function() {
        $('#chat-file-input').trigger('click');
    });

    $('#chat-file-input').on('change', function() {
        const fileInput = this;

        if (fileInput.files.length > 0) {
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('chat_id', chat_id);

            $('#chat-attachment-btn i').removeClass('fa-paperclip').addClass('fa-spinner fa-spin');

            $.ajax({
                url: 'index.php?route=api/chat_upload' + token,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(json) {
                    if (json['success']) {
                        $.ajax({
                            url: 'index.php?route=api/chat_send' + token,
                            type: 'POST',
                            data: {
                                chat_id: chat_id,
                                file: json['file'],
                                message_type: json['type']
                            },
                            dataType: 'json',
                            success: function(sendJson) {
                                if (sendJson['success']) {
                                    $(fileInput).val('');
                                    loadChatMeta();
                                }
                            }
                        });
                    } else if (json['error']) {
                        alert(json['error']);
                    }
                },
                complete: function() {
                    $('#chat-attachment-btn i').removeClass('fa-spinner fa-spin').addClass('fa-paperclip');
                }
            });
        }
    });

    if (chat_id) {
        if ($('#chat-messages-container').length && $('#chat-messages-container')[0]) {
            const container = $('#chat-messages-container');
            container.scrollTop(container[0].scrollHeight);
        }

        fetchMessages();
        if (text.supportsOperationalMeta || text.supportsChatRouting || text.supportsChatTags || text.supportsSla || text.supportsPresence || text.supportsEngagement) {
            applyChatMeta(Object.assign({}, window.currentChatMeta || {}, buildMetaLabels(window.currentChatMeta || {})));
            loadChatMeta();
        }
    }

    loadChatList();
    setInterval(loadChatList, 15000);

    if (chat_id && text.supportsEngagement) {
        setInterval(function() {
            renderEngagementPanel(window.currentChatMeta || {});
        }, 60000);
    }
});
