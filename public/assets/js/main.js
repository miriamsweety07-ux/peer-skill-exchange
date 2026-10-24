// SkillSwap -- small vanilla-JS touches: alert auto-dismiss, session-request
// inline toggle, and the session chat's AJAX poll (no WebSockets).
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss flash alerts after a few seconds.
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            var btn = el.querySelector('.btn-close');
            if (btn) btn.click();
        }, 6000);
    });

    // ---- Session chat polling ----
    var thread = document.getElementById('chatThread');
    if (thread) {
        var sessionId = thread.dataset.sessionId;
        var lastId = parseInt(thread.dataset.lastId || '0', 10);
        var meId = parseInt(thread.dataset.myId || '0', 10);

        function bubbleHtml(msg) {
            var mine = msg.sender_id === meId;
            var div = document.createElement('div');
            div.className = 'chat-bubble ' + (mine ? 'chat-bubble-mine' : 'chat-bubble-theirs');
            var meta = document.createElement('div');
            meta.className = 'chat-meta';
            meta.textContent = (mine ? 'You' : msg.sender_name) + ' · ' + msg.created_at;
            var body = document.createElement('div');
            body.textContent = msg.body;
            div.appendChild(meta);
            div.appendChild(body);
            return div;
        }

        function poll() {
            fetch('/index.php?route=sessions/messages/poll&session_id=' + encodeURIComponent(sessionId) + '&after_id=' + lastId)
                .then(function (res) { return res.ok ? res.json() : { messages: [] }; })
                .then(function (data) {
                    (data.messages || []).forEach(function (msg) {
                        thread.appendChild(bubbleHtml(msg));
                        lastId = msg.id;
                    });
                    if ((data.messages || []).length > 0) {
                        thread.scrollTop = thread.scrollHeight;
                    }
                })
                .catch(function () { /* silent -- next poll tick will retry */ });
        }

        thread.scrollTop = thread.scrollHeight;
        setInterval(poll, 4000);

        var chatForm = document.getElementById('chatForm');
        if (chatForm) {
            chatForm.addEventListener('submit', function () {
                var input = chatForm.querySelector('textarea[name="body"]');
                if (input) {
                    setTimeout(function () { input.value = ''; }, 0);
                }
            });
        }
    }

    // ---- Inline "request a session" toggle (Directory / Matches tabs) ----
    document.querySelectorAll('[data-toggle-request]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-toggle-request');
            var target = document.getElementById(targetId);
            if (target) {
                target.classList.toggle('d-none');
            }
        });
    });
});
