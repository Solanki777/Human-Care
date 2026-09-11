
        (function () {
            'use strict';

            const chatWindow = document.getElementById('chatWindow');
            const chatInput = document.getElementById('chatInput');
            const sendBtn = document.getElementById('sendBtn');
            const typingRow = document.getElementById('typingRow');
            const suggestionsWrap = document.getElementById('suggestionsWrap');

            const CHAT_API_URL = 'api/medimate_msg.php';

            // Stores full conversation: { role: 'user'|'assistant', content: '...' }
            const conversationHistory = [];

            /* ---------- Helpers ---------- */

            function formatTimestamp(date) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function scrollToBottom() {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }

            function renderMarkdown(text) {
                const div = document.createElement('div');
                div.textContent = text;
                let escaped = div.innerHTML;

                // ✅ / ❌ notice boxes
                escaped = escaped.replace(/(✅|❌)[^\n]*/g, function (match) {
                    const isSuccess = match.startsWith('✅');
                    const color = isSuccess ? '#065f46' : '#991b1b';
                    const bg = isSuccess ? '#d1fae5' : '#fee2e2';
                    const border = isSuccess ? '#10b981' : '#ef4444';
                    return '<div style="margin-top:10px;padding:10px 14px;border-radius:8px;background:' + bg + ';color:' + color + ';border-left:3px solid ' + border + ';font-size:13.5px;">' + match + '</div>';
                });

                escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                escaped = escaped.replace(/\*(.+?)\*/g, '<em>$1</em>');
                escaped = escaped.replace(/\n/g, '<br>');

                return escaped;
            }

            // Set timestamp on initial greeting
            const initialTs = document.getElementById('initialTimestamp');
            if (initialTs) initialTs.textContent = formatTimestamp(new Date());

            /* ---------- Render a message bubble ---------- */

            function appendMessage(text, sender, isError) {
                const row = document.createElement('div');
                row.className = 'message-row ' + sender;

                const avatar = document.createElement('div');
                avatar.className = 'avatar ' + (sender === 'user' ? 'user-avatar' : 'ai-avatar');
                avatar.textContent = sender === 'user' ? '🙂' : '🤖';

                const group = document.createElement('div');
                group.className = 'bubble-group';

                const bubble = document.createElement('div');
                bubble.className = 'bubble' + (isError ? ' error-bubble' : '');

                if (sender === 'ai' && !isError) {
                    bubble.innerHTML = renderMarkdown(text);
                } else {
                    bubble.textContent = text;
                }

                const time = document.createElement('div');
                time.className = 'msg-timestamp';
                time.textContent = formatTimestamp(new Date());

                group.appendChild(bubble);
                group.appendChild(time);
                row.appendChild(avatar);
                row.appendChild(group);

                chatWindow.insertBefore(row, typingRow);
                scrollToBottom();
            }

            function showTyping(show) {
                typingRow.style.display = show ? 'flex' : 'none';
                if (show) scrollToBottom();
            }

            function setSending(isSending) {
                sendBtn.disabled = isSending;
                chatInput.disabled = isSending;
            }

            /* ---------- Suggestion cards ---------- */

            suggestionsWrap.addEventListener('click', function (e) {
                const card = e.target.closest('.suggestion-card');
                if (!card) return;
                chatInput.value = card.dataset.text || card.textContent.trim();
                chatInput.focus();
                autoResizeTextarea();
            });

            /* ---------- Textarea auto-resize ---------- */

            function autoResizeTextarea() {
                chatInput.style.height = 'auto';
                chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
            }

            chatInput.addEventListener('input', autoResizeTextarea);

            /* ---------- Enter / Shift+Enter ---------- */

            chatInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            sendBtn.addEventListener('click', sendMessage);

            /* ---------- Send message ---------- */

            async function sendMessage() {
                const text = chatInput.value.trim();
                if (!text) return;

                // Show user bubble
                appendMessage(text, 'user');
                chatInput.value = '';
                autoResizeTextarea();

                // Add to history as 'user'
                conversationHistory.push({ role: 'user', content: text });

                // Keep last 20 turns max
                if (conversationHistory.length > 20) {
                    conversationHistory.splice(0, 2);
                }

                setSending(true);
                showTyping(true);

                try {
                    // Build history to send (exclude current message, already in 'message' field)
                    // Convert any legacy 'ai' role to 'assistant' for the backend
                    const historyToSend = conversationHistory.slice(0, -1).map(function (turn) {
                        return {
                            role: turn.role === 'ai' ? 'assistant' : turn.role,
                            content: turn.content
                        };
                    });

                    const response = await fetch(CHAT_API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            message: text,
                            history: historyToSend
                        })
                    });

                    showTyping(false);

                    if (!response.ok) {
                        const errText = await response.text();
                        throw new Error('HTTP ' + response.status + ' — ' + errText);
                    }

                    const data = await response.json();

                    if (data && typeof data.reply === 'string' && data.reply.length > 0) {
                        appendMessage(data.reply, 'ai');
                        // Store as 'assistant' so backend understands it
                        conversationHistory.push({ role: 'assistant', content: data.reply });
                    } else {
                        appendMessage('Sorry, the AI service is currently unavailable.', 'ai', true);
                        // Remove failed user turn
                        conversationHistory.pop();
                    }

                } catch (err) {
                    console.error('MediMate error:', err);
                    showTyping(false);
                    appendMessage('Sorry, something went wrong: ' + err.message, 'ai', true);
                    // Remove failed user turn from history
                    conversationHistory.pop();
                } finally {
                    setSending(false);
                    chatInput.focus();
                }
            }

            /* ---------- Initial scroll ---------- */
            scrollToBottom();

        })();