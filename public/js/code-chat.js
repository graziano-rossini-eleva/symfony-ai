/**
 * code-chat.js
 *
 * Client-side logic for the Code Chat interface.
 * Configuration is injected by the Twig template via window.CodeChat.
 */
(function () {
    'use strict';

    var cfg      = window.CodeChat;
    var i18n     = cfg.i18n;
    var routes   = cfg.routes;

    var form       = document.getElementById('chat-form');
    var input      = document.getElementById('user-input');
    var btn        = document.getElementById('send-btn');
    var messagesEl = document.getElementById('messages');

    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendMessage(html, role) {
        var div = document.createElement('div');
        div.className = 'message ' + role;
        div.innerHTML = html;
        messagesEl.appendChild(div);
        scrollBottom();
        return div;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderMarkdown(text) {
        // Fenced code blocks: ```lang\ncode\n```
        text = text.replace(/```(\w*)\n([\s\S]*?)```/g, function (_, lang, code) {
            return '<pre><code class="language-' + (lang || 'text') + '">' + escapeHtml(code.trim()) + '</code></pre>';
        });
        // Inline code: `code`
        text = text.replace(/`([^`\n]+)`/g, function (_, code) {
            return '<code>' + escapeHtml(code) + '</code>';
        });
        // Line breaks
        text = text.replace(/\n/g, '<br>');
        return text;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var question = input.value.trim();
        if (!question) return;

        input.value = '';
        btn.disabled = true;
        input.disabled = true;

        appendMessage(escapeHtml(question), 'user');
        var typingDiv = appendMessage(escapeHtml(i18n.typing), 'typing');

        fetch(routes.message, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: question }),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                typingDiv.remove();
                if (!result.ok) {
                    appendMessage(escapeHtml(result.data.error || i18n.errorUnknown), 'assistant');
                } else {
                    appendMessage(renderMarkdown(result.data.reply || ''), 'assistant');
                }
            })
            .catch(function () {
                typingDiv.remove();
                appendMessage(escapeHtml(i18n.errorConnection), 'assistant');
            })
            .finally(function () {
                btn.disabled = false;
                input.disabled = false;
                input.focus();
            });
    });
}());
