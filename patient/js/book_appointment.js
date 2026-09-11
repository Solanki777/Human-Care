//     <!-- ================================================================
//      UPDATED JAVASCRIPT — Replace the existing <script> block in
//      ai_assistant.php with this one.

//      Changes vs original:
//        - Maintains a `conversationHistory` array so Claude has
//          multi-turn context (needed for booking flows).
//        - Passes { message, history } to api/.php.
//        - Renders Markdown-style bold (**text**) from the AI reply.
//        - Action result notices (✅ / ❌) are rendered in a styled box.
// ================================================================ -->
        (function () {
            'use strict';

            const chatWindow = document.getElementById('chatWindow');
            const chatInput = document.getElementById('chatInput');
            const sendBtn = document.getElementById('sendBtn');
            const typingRow = document.getElementById('typingRow');
            const suggestionsWrap = document.getElementById('suggestionsWrap');

            const CHAT_API_URL = 'api/medimate_msg.php';

            // In-memory conversation history for multi-turn context
            const conversationHistory = [];

            /* ---------- Helpers ---------- */

            function formatTimestamp(date) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function scrollToBottom() {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }

            /**
             * Minimal Markdown renderer for AI replies.
             * Supports: **bold**, *italic*, line-breaks, ✅/❌ notice blocks.
             */
            function renderMarkdown(text) {
                // Escape HTML first
                const div = document.createElement('div');
                div.textContent = text;
                let escaped = div.innerHTML;

                // Action result notice lines (lines starting with ✅ or ❌)
                escaped = escaped.replace(
                    /(✅|❌)[^\n]*/g,
                    (match) => {
                        const isSuccess = match.startsWith('✅');
                        const color = isSuccess ? '#065f46' : '#991b1b';
                        const bg = isSuccess ? '#d1fae5' : '#fee2e2';
                        const border = isSuccess ? '#10b981' : '#ef4444';
                        return `<div style="margin-top:10px;padding:10px 14px;border-radius:8px;background:${bg};color:${color};border-left:3px solid ${border};font-size:13.5px;">${match}</div>`;
                    }
                );

                // **bold**
                escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                // *italic*
                escaped = escaped.replace(/\*(.+?)\*/g, '<em>$1</em>');
                // newlines → <br>
                escaped = escaped.replace(/\n/g, '<br>');

                return escaped;
            }

            // Set timestamp on the initial greeting
            document.getElementById('initialTimestamp').textContent = formatTimestamp(new Date());

            /* ---------- Message rendering ---------- */

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

            /* ---------- Send + fetch ---------- */

            async function sendMessage() {
                const text = chatInput.value.trim();
                if (!text) return;

                appendMessage(text, 'user');
                chatInput.value = '';
                autoResizeTextarea();

                // Add to history BEFORE the API call
                conversationHistory.push({ role: 'user', content: text });

                // Keep history to last 20 turns to avoid huge payloads
                if (conversationHistory.length > 20) conversationHistory.splice(0, 2);

                setSending(true);
                showTyping(true);

                try {
                    const response = await fetch(CHAT_API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            message: text,
                            history: conversationHistory.slice(0, -1) // all but current
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
                        // Add assistant reply to history
                        conversationHistory.push({ role: 'assistant', content: data.reply });
                    } else {
                        appendMessage('Sorry, the AI service is currently unavailable.', 'ai', true);
                    }

                } catch (err) {
                    console.error(err);
                    showTyping(false);
                    appendMessage('Sorry, something went wrong: ' + err.message, 'ai', true);
                    // Remove the failed user turn from history
                    conversationHistory.pop();
                } finally {
                    setSending(false);
                    chatInput.focus();
                }
            }

            /* ---------- Initial scroll ---------- */
            scrollToBottom();

        })();
    