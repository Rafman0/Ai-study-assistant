/**
 * AI Tutor - graduated hint system frontend
 */
(function () {
    'use strict';

    const API_URL = typeof TUTOR_API_URL !== 'undefined' ? TUTOR_API_URL : null;

    const els = {
        form: document.getElementById('ai-tutor-form'),
        askSection: document.getElementById('ask-section'),
        session: document.getElementById('tutor-session'),
        questionText: document.getElementById('session-question'),
        conversation: document.getElementById('conversation'),
        tracker: document.getElementById('hint-tracker'),
        nextHintBtn: document.getElementById('next-hint-btn'),
        revealBtn: document.getElementById('reveal-btn'),
        newQuestionBtn: document.getElementById('new-question-btn'),
        answerAttempt: document.getElementById('answer-attempt'),
        chatInput: document.getElementById('chat-input'),
        sendBtn: document.getElementById('send-btn'),
        submitBtn: document.getElementById('submit-btn'),
        loading: document.getElementById('loading-indicator'),
    };

    if (!els.form) {
        return;
    }

    const LEVEL_LABELS = {
        1: 'Level 1 · Guiding Questions',
        2: 'Level 2 · Conceptual Clue',
        3: 'Level 3 · Partial Solution',
        4: 'Level 4 · Complete Walkthrough',
    };

    let currentLevel = 0;
    let revealed = false;
    const MAX_LEVEL = 4;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function apiPost(action, payload) {
        const res = await fetch(API_URL + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload || {}),
        });
        return res.json();
    }

    function removeLastMessage() {
        const msgs = els.conversation.querySelectorAll('.tutor-msg');
        if (msgs.length) {
            msgs[msgs.length - 1].remove();
        }
    }

    function addMessage(html, answerClass, badge, badgeClass) {
        const wrap = document.createElement('div');
        wrap.className = 'tutor-msg';

        const avatar = document.createElement('div');
        avatar.className = 'msg-avatar';
        avatar.textContent = '🤖';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble' + (answerClass ? ' answer' : '');
        bubble.innerHTML = html;

        if (badge) {
            const b = document.createElement('span');
            b.className = 'msg-badge' + (badgeClass ? ' ' + badgeClass : '');
            b.textContent = badge;
            bubble.prepend(b);
            bubble.appendChild(document.createElement('br'));
        }

        wrap.appendChild(avatar);
        wrap.appendChild(bubble);
        els.conversation.appendChild(wrap);
        els.conversation.scrollTop = els.conversation.scrollHeight;
    }

    function addUserMessage(text) {
        const wrap = document.createElement('div');
        wrap.className = 'tutor-msg user-msg';

        const avatar = document.createElement('div');
        avatar.className = 'msg-avatar';
        avatar.textContent = '💬';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble';
        bubble.textContent = text;

        wrap.appendChild(avatar);
        wrap.appendChild(bubble);
        els.conversation.appendChild(wrap);
        els.conversation.scrollTop = els.conversation.scrollHeight;
    }

    function updateTracker(level, revealedFlag) {
        for (let i = 1; i <= MAX_LEVEL; i++) {
            const step = els.tracker.querySelector('[data-step="' + i + '"]');
            if (!step) continue;
            step.classList.remove('active', 'done');
            if (revealedFlag) {
                step.classList.add('done');
            } else if (i < level) {
                step.classList.add('done');
            } else if (i === level) {
                step.classList.add('active');
            }
        }
    }

    function setControls(state) {
        // state: { active, revealed, exhausted }
        els.nextHintBtn.disabled = !state.active || state.revealed || state.exhausted;
        els.nextHintBtn.textContent = state.exhausted && !state.revealed
            ? 'All Hints Used ✓'
            : 'Request Another Hint 💡';
        els.revealBtn.disabled = !state.active || state.revealed;

        if (state.exhausted && !state.revealed) {
            els.revealBtn.classList.remove('btn-outline');
            els.revealBtn.classList.add('btn-primary');
            els.revealBtn.title = 'All hint levels used - the complete answer is unlocked';
        } else {
            els.revealBtn.classList.add('btn-outline');
            els.revealBtn.classList.remove('btn-primary');
            els.revealBtn.title = 'View the complete answer & step-by-step solution';
        }
    }

    function startSession(question, courseId) {
        els.submitBtn.disabled = true;
        els.loading.style.display = 'inline';

        apiPost('tutor_start', { question: question, course_id: courseId })
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Could not start the session.');
                    return;
                }
                currentLevel = data.level || 1;
                revealed = false;
                els.askSection.style.display = 'none';
                els.session.style.display = 'block';
                els.questionText.textContent = question;
                els.conversation.innerHTML = '';
                els.chatInput.value = '';
                els.answerAttempt.style.display = 'block';
                addMessage(escapeHtml(data.hint), false, LEVEL_LABELS[currentLevel] || ('Hint Level ' + currentLevel));
                updateTracker(currentLevel, false);
                setControls({ active: true, revealed: false, exhausted: !!data.exhausted });
                els.chatInput.focus();
            })
            .catch(function () {
                alert('Failed to reach the AI Tutor. Please try again.');
            })
            .finally(function () {
                els.submitBtn.disabled = false;
                els.loading.style.display = 'none';
            });
    }

    function requestHint() {
        els.nextHintBtn.disabled = true;
        addMessage('<em>Thinking of the next hint...</em>');

        apiPost('tutor_hint', {})
            .then(function (data) {
                removeLastMessage();
                if (!data.success) {
                    alert(data.message || 'No hint available.');
                    setControls({ active: true, revealed: false, exhausted: currentLevel >= MAX_LEVEL });
                    return;
                }
                if (data.hint) {
                    currentLevel = data.level;
                    addMessage(escapeHtml(data.hint), false, LEVEL_LABELS[currentLevel] || ('Hint Level ' + currentLevel));
                    updateTracker(currentLevel, false);
                } else if (data.exhausted) {
                    currentLevel = MAX_LEVEL;
                }
                setControls({ active: true, revealed: false, exhausted: !!data.exhausted });
            })
            .catch(function () {
                alert('Failed to get a hint. Please try again.');
                setControls({ active: true, revealed: false, exhausted: currentLevel >= MAX_LEVEL });
            });
    }

    function revealAnswer() {
        if (els.revealBtn.disabled) {
            return;
        }

        els.revealBtn.disabled = true;
        addMessage('<em>Preparing the complete solution...</em>');

        apiPost('tutor_reveal', { explicit: true })
            .then(function (data) {
                removeLastMessage();
                if (!data.success) {
                    alert(data.message || 'Could not reveal the answer.');
                    setControls({ active: true, revealed: false, exhausted: currentLevel >= MAX_LEVEL });
                    return;
                }

                let badge = '✅ Complete Answer';
                let badgeClass = 'answer-badge';
                if (data.revealed_early) {
                    badge = '🔓 Complete Answer (revealed early - level ' + data.level + '/' + MAX_LEVEL + ')';
                    badgeClass = 'early-note';
                }

                revealed = true;
                addMessage(
                    escapeHtml(data.answer),
                    true,
                    badge,
                    badgeClass
                );
                updateTracker(MAX_LEVEL, true);
                setControls({ active: true, revealed: true, exhausted: true });

                addMessage(
                    '<strong>Before you move on:</strong> close this page and try to re-explain the solution in your own words, or test yourself with a similar problem. That is where real learning happens! 🎯'
                );
            })
            .catch(function () {
                alert('Failed to reveal the answer. Please try again.');
                setControls({ active: true, revealed: false, exhausted: currentLevel >= MAX_LEVEL });
            });
    }

    function sendChat() {
        const message = els.chatInput.value.trim();
        if (!message) {
            els.chatInput.focus();
            return;
        }

        els.sendBtn.disabled = true;
        addUserMessage(message);
        addMessage('<em>Thinking...</em>');

        apiPost('tutor_chat', { message: message })
            .then(function (data) {
                removeLastMessage();
                if (!data.success) {
                    addMessage('<em>⚠️ ' + escapeHtml(data.message || 'Could not reach your tutor.') + '</em>');
                    return;
                }
                if (data.type === 'answer_check') {
                    renderCheckFeedback(data);
                } else {
                    addMessage(escapeHtml(data.reply || ''));
                }
            })
            .catch(function () {
                removeLastMessage();
                addMessage('<em>⚠️ Something went wrong. Please try again.</em>');
            })
            .finally(function () {
                els.sendBtn.disabled = false;
            });
    }

    function renderCheckFeedback(data) {
        const verdict = data.verdict || 'incorrect';
        const meta = {
            correct: { badge: '🎉 Correct', cls: 'verdict-correct' },
            partial: { badge: '🧩 Partially Correct', cls: 'verdict-partial' },
            incorrect: { badge: '❌ Not Quite', cls: 'verdict-incorrect' },
        }[verdict] || { badge: 'Feedback', cls: 'verdict-incorrect' };

        let confidence = parseInt(data.confidence, 10);
        confidence = isNaN(confidence) ? 0 : Math.max(0, Math.min(100, confidence));

        const wrap = document.createElement('div');
        wrap.className = 'tutor-msg';

        const avatar = document.createElement('div');
        avatar.className = 'msg-avatar';
        avatar.textContent = '🤖';
        wrap.appendChild(avatar);

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble feedback-bubble ' + meta.cls;

        let html = '<span class="msg-badge ' + meta.cls + '">' + meta.badge + '</span><br>';

        html += '<div class="confidence-line">'
            + '<span class="confidence-label">My confidence in this verdict:</span>'
            + '<div class="confidence-track"><div class="confidence-fill" style="width:' + confidence + '%;"></div></div>'
            + '<span class="confidence-value">' + confidence + '%</span>'
            + '</div>';

        html += '<p class="feedback-text">' + escapeHtml(data.feedback || '') + '</p>';

        if (data.correct_answer && verdict !== 'correct') {
            html += '<div class="correct-answer-box">'
                + '<span class="correct-answer-label">✅ Correct answer</span>'
                + '<div class="correct-answer-text">' + escapeHtml(data.correct_answer) + '</div>'
                + '</div>';
            html += '<p class="feedback-subtle">' + (verdict === 'partial'
                ? 'You were close! Compare your answer above, then give it one more shot.'
                : 'Take a moment to read it, then try again with the hint in front of you.') + '</p>';
        } else if (verdict === 'correct') {
            html += '<p class="feedback-subtle">That is the level of understanding we are after. Ready to push further?</p>';
        }

        const actions = document.createElement('div');
        actions.className = 'feedback-actions';
        if (verdict !== 'correct') {
            actions.innerHTML += '<button type="button" class="btn btn-sm btn-outline" data-action="answer-retry">🔄 Try Again</button>';
        }
        if (currentLevel < MAX_LEVEL && !revealed) {
            actions.innerHTML += '<button type="button" class="btn btn-sm btn-outline" data-action="next-hint">💡 Next Hint</button>';
        }
        actions.innerHTML += '<button type="button" class="btn btn-sm btn-outline" data-action="reveal-full">🔓 Full Answer</button>';

        bubble.innerHTML = html;
        bubble.appendChild(actions);
        wrap.appendChild(bubble);
        els.conversation.appendChild(wrap);
        els.conversation.scrollTop = els.conversation.scrollHeight;
    }

    function resetSession() {
        els.session.style.display = 'none';
        els.askSection.style.display = 'block';
        els.conversation.innerHTML = '';
        document.getElementById('question').value = '';
        currentLevel = 0;
        revealed = false;
        els.answerAttempt.style.display = 'none';
        els.chatInput.value = '';
        updateTracker(0, false);
        setControls({ active: false, revealed: false, exhausted: false });
    }

    els.form.addEventListener('submit', function (e) {
        e.preventDefault();
        const question = document.getElementById('question').value.trim();
        const course = document.getElementById('course').value;
        if (!question) {
            alert('Please enter a question');
            return;
        }
        startSession(question, course);
    });

    els.nextHintBtn.addEventListener('click', requestHint);
    els.revealBtn.addEventListener('click', revealAnswer);
    els.newQuestionBtn.addEventListener('click', resetSession);
    els.sendBtn.addEventListener('click', sendChat);
    els.chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChat();
        }
    });

    // Interactive inline actions inside feedback messages
    els.conversation.addEventListener('click', function (e) {
        const btn = e.target && e.target.closest ? e.target.closest('[data-action]') : null;
        if (!btn) {
            return;
        }
        const action = btn.getAttribute('data-action');
        if (action === 'answer-retry') {
            els.chatInput.value = '';
            els.chatInput.focus();
            els.answerAttempt.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else if (action === 'next-hint') {
            requestHint();
        } else if (action === 'reveal-full') {
            revealAnswer();
        }
    });

    setControls({ active: false, revealed: false, exhausted: false });
})();