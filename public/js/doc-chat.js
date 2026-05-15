/**
 * doc-chat.js
 *
 * Client-side logic for the documentation chat interface.
 *
 * Reads configuration from the global `DocChat` object injected by the
 * Twig template:
 *
 *   DocChat.routes.message    - URL for POST /doc-chat/message
 *   DocChat.routes.sendEmail  - URL for POST /doc-chat/send-email
 *   DocChat.i18n              - localised UI strings
 */
(function () {
    'use strict';

    const cfg   = window.DocChat;
    const i18n  = cfg.i18n;
    const routes = cfg.routes;

    const form       = document.getElementById('chat-form');
    const input      = document.getElementById('user-input');
    const btn        = document.getElementById('send-btn');
    const messagesEl = document.getElementById('messages');

    const history = [];

    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendMessage(text, role) {
        const div = document.createElement('div');
        div.className = 'message ' + role;
        div.textContent = text;
        messagesEl.appendChild(div);
        scrollBottom();
        return div;
    }

    function showEmailCard() {
        const card = document.createElement('div');
        card.className = 'email-card';
        card.id = 'email-card';

        // Build the card using DOM methods to avoid any innerHTML XSS risk.
        const p = document.createElement('p');
        p.textContent = i18n.emailCardIntro;
        card.appendChild(p);

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.id = 'email-name';
        nameInput.placeholder = i18n.emailNamePlaceholder;
        card.appendChild(nameInput);

        const emailInput = document.createElement('input');
        emailInput.type = 'email';
        emailInput.id = 'email-address';
        emailInput.placeholder = i18n.emailEmailPlaceholder;
        card.appendChild(emailInput);

        const errorEl = document.createElement('div');
        errorEl.className = 'field-error';
        errorEl.id = 'email-error';
        card.appendChild(errorEl);

        const actions = document.createElement('div');
        actions.className = 'email-card-actions';

        const sendBtn = document.createElement('button');
        sendBtn.className = 'btn-primary';
        sendBtn.id = 'email-send-btn';
        sendBtn.textContent = i18n.emailSubmit;
        sendBtn.addEventListener('click', submitEmail);

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn-secondary';
        cancelBtn.id = 'email-cancel-btn';
        cancelBtn.textContent = i18n.emailCancel;
        cancelBtn.addEventListener('click', function () {
            card.remove();
            appendMessage(i18n.emailCancelMsg, 'assistant');
        });

        actions.appendChild(sendBtn);
        actions.appendChild(cancelBtn);
        card.appendChild(actions);

        messagesEl.appendChild(card);
        scrollBottom();
    }

    async function submitEmail() {
        const name     = document.getElementById('email-name').value.trim();
        const email    = document.getElementById('email-address').value.trim();
        const errorEl  = document.getElementById('email-error');
        const sendBtn  = document.getElementById('email-send-btn');

        errorEl.textContent = '';

        if (!name) {
            errorEl.textContent = i18n.emailErrorName;
            return;
        }

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errorEl.textContent = i18n.emailErrorEmail;
            return;
        }

        sendBtn.disabled    = true;
        sendBtn.textContent = i18n.emailSubmitting;

        try {
            const res = await fetch(routes.sendEmail, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, history }),
            });

            const data = await res.json();
            document.getElementById('email-card').remove();

            if (data.success) {
                appendMessage(i18n.emailSuccess.replace('%name%', name), 'assistant');
            } else {
                appendMessage(i18n.emailErrorSend, 'assistant');
            }
        } catch (_) {
            document.getElementById('email-send-btn').disabled    = false;
            document.getElementById('email-send-btn').textContent = i18n.emailSubmit;
            document.getElementById('email-error').textContent    = i18n.emailErrorConn;
        }
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const text = input.value.trim();
        if (!text) { return; }

        document.getElementById('email-card')?.remove();

        appendMessage(text, 'user');
        history.push({ role: 'user', text });
        input.value  = '';
        btn.disabled = true;

        const typing = appendMessage('...', 'typing');

        try {
            const res = await fetch(routes.message, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text }),
            });

            const data = await res.json();
            typing.remove();

            const reply = data.reply ?? data.error ?? i18n.errorUnknown;
            appendMessage(reply, 'assistant');
            history.push({ role: 'assistant', text: reply });

            if (data.offer_email) {
                showEmailCard();
            }
        } catch (_) {
            typing.remove();
            appendMessage(i18n.errorConnection, 'assistant');
        } finally {
            btn.disabled = false;
            input.focus();
        }
    });
}());
