/**
 * Quiz Center - AI generation + quiz-taking flow
 */
(function () {
    'use strict';

    const AI_URL = typeof TUTOR_API_URL !== 'undefined' ? TUTOR_API_URL : null; // api/ai.php
    const QUIZ_URL = typeof QUIZ_API_URL !== 'undefined' ? QUIZ_API_URL : null; // api/quizzes.php

    if (!QUIZ_URL) {
        return;
    }

    /* ---------------- AI GENERATOR ---------------- */

    const genBtn = document.getElementById('generate-btn');
    const genLoading = document.getElementById('gen-loading');
    const genStatus = document.getElementById('gen-status');

    function setGenStatus(text, isError) {
        if (!genStatus) return;
        genStatus.textContent = text || '';
        genStatus.style.color = isError ? 'var(--color-error)' : 'var(--color-success)';
    }

    async function apiPost(url, action, payload) {
        const res = await fetch(url + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload || {}),
        });
        return res.json();
    }

    function fillFormWithQuestions(questions) {
        questions.forEach(function (q, index) {
            const i = index + 1;
            if (i > 5) return;
            const fs = document.querySelector('fieldset[data-question="' + i + '"]');
            if (!fs) return;

            fs.querySelector('.q-text').value = q.question || '';
            fs.querySelector('.q-opt-a').value = q.option_a || '';
            fs.querySelector('.q-opt-b').value = q.option_b || '';
            fs.querySelector('.q-opt-c').value = q.option_c || '';
            fs.querySelector('.q-opt-d').value = q.option_d || '';
            fs.querySelector('.q-expl').value = q.explanation || '';

            const radio = fs.querySelector('.q-correct[value="' + (q.correct_answer || '') + '"]');
            if (radio) radio.checked = true;

            // Highlight freshly generated content briefly
            fs.style.boxShadow = '0 0 0 2px var(--color-accent)';
            setTimeout(function () { fs.style.boxShadow = ''; }, 1200);
        });
    }

    if (genBtn && AI_URL) {
        genBtn.addEventListener('click', async function () {
            const topic = document.getElementById('gen-topic').value.trim();
            const count = document.getElementById('gen-count').value;

            if (!topic) {
                setGenStatus('Enter a topic first.', true);
                return;
            }

            genBtn.disabled = true;
            genLoading.style.display = 'inline';
            setGenStatus('');

            try {
                const data = await apiPost(AI_URL, 'generate_quiz', { topic: topic, count: parseInt(count, 10) });

                if (!data.success || !data.questions || !data.questions.length) {
                    setGenStatus(data.message || 'Could not generate questions. Try another topic.', true);
                    return;
                }

                fillFormWithQuestions(data.questions);

                const titleInput = document.getElementById('title');
                if (titleInput && !titleInput.value) {
                    titleInput.value = data.simulated ? topic + ' - Practice Quiz' : topic + ' - AI Practice Quiz';
                }
                document.getElementById('quiz-create-form').scrollIntoView({ behavior: 'smooth' });

                setGenStatus(
                    data.simulated
                        ? data.questions.length + ' draft question(s) filled in. Review the answers and adjust anything before saving.'
                        : data.questions.length + ' AI-generated question(s) filled in. Review before saving.'
                );
            } catch (err) {
                setGenStatus('Generation failed. Please try again.', true);
            } finally {
                genBtn.disabled = false;
                genLoading.style.display = 'none';
            }
        });
    }

    /* ---------------- TAKE QUIZ FLOW ---------------- */

    const modal = document.getElementById('quizModal');
    const modalTitle = document.getElementById('quizModalTitle');
    const modalBody = document.getElementById('quizModalBody');
    const modalFooter = document.getElementById('quizModalFooter');
    let currentQuiz = null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function openModal() {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
        currentQuiz = null;
        modalTitle.textContent = '';
        modalBody.innerHTML = '';
        modalFooter.innerHTML = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            closeModal();
        }
    });

    function renderTakeView() {
        modalTitle.textContent = currentQuiz.title;
        const letters = ['a', 'b', 'c', 'd'];

        const html = currentQuiz.questions.map(function (q, idx) {
            const opts = letters.map(function (L) {
                const label = q['option_' + L];
                return (
                    '<label class="quiz-option">' +
                    '<input type="radio" name="qa_' + q.id + '" value="' + L + '">' +
                    '<span><strong>' + L.toUpperCase() + '.</strong> ' + escapeHtml(label) + '</span>' +
                    '</label>'
                );
            }).join('');

            return (
                '<div class="quiz-question" data-qid="' + q.id + '">' +
                '<p class="quiz-q-text"><strong>Q' + (idx + 1) + '.</strong> ' + escapeHtml(q.question) + '</p>' +
                opts +
                '</div>'
            );
        }).join('');

        modalBody.innerHTML = html;
        modalFooter.innerHTML =
            '<button type="button" class="btn btn-secondary" id="cancelQuizBtn">Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="submitQuizBtn">Submit Answers</button>';

        document.getElementById('cancelQuizBtn').addEventListener('click', closeModal);
        document.getElementById('submitQuizBtn').addEventListener('click', submitQuiz);
    }

    async function submitQuiz() {
        const answers = {};
        let unanswered = 0;

        currentQuiz.questions.forEach(function (q) {
            const chosen = document.querySelector('input[name="qa_' + q.id + '"]:checked');
            if (chosen) {
                answers[q.id] = chosen.value;
            } else {
                unanswered++;
            }
        });

        if (unanswered > 0 && !window.confirm(unanswered + ' question(s) are unanswered. Submit anyway?')) {
            return;
        }

        const submitBtn = document.getElementById('submitQuizBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Grading...';

        try {
            const data = await apiPost(QUIZ_URL, 'grade', { quiz_id: currentQuiz.id, answers: answers });

            if (!data.success) {
                alert(data.message || 'Grading failed.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Answers';
                return;
            }

            renderResultView(data);
        } catch (err) {
            alert('Failed to submit. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Answers';
        }
    }

    function renderResultView(data) {
        const pct = parseFloat(data.percentage);
        let verdict;
        if (pct >= 90) verdict = '🌟 Outstanding!';
        else if (pct >= 70) verdict = '🎉 Well done!';
        else if (pct >= 50) verdict = '👍 Keep practising.';
        else verdict = '💪 Review and try again.';

        let html =
            '<div class="quiz-result-summary">' +
            '<div class="quiz-score-ring" style="--pct:' + pct + ';"><span>' + Math.round(pct) + '%</span></div>' +
            '<h4 style="margin-bottom:4px;">' + escapeHtml(verdict) + '</h4>' +
            '<p style="color:var(--color-gray-600);">' + data.score + ' out of ' + data.total_questions + ' correct</p>';

        if (data.new_achievements && data.new_achievements.length) {
            html += '<div class="alert alert-success">🎉 New achievement unlocked: <strong>' +
                data.new_achievements.map(escapeHtml).join(', ') + '</strong></div>';
        }
        html += '</div>';

        html += '<h5 style="margin:16px 0 8px;">Review</h5>';
        html += data.review.map(function (r, i) {
            return (
                '<div class="quiz-review-item ' + (r.is_correct ? 'correct' : 'incorrect') + '">' +
                '<p style="margin:0 0 4px;"><strong>Q' + (i + 1) + '. ' + (r.is_correct ? '✅' : '❌') + '</strong> ' + escapeHtml(r.question) + '</p>' +
                '<p style="margin:0;font-size:13px;color:var(--color-gray-600);">' +
                'Your answer: <strong>' + (r.your_answer ? escapeHtml(r.your_answer) : '<em>skipped</em>') + '</strong>' +
                (r.is_correct ? '' : ' &nbsp;•&nbsp; Correct answer: <strong>' + escapeHtml(r.correct_answer) + '</strong>') +
                '</p>' +
                (r.explanation ? '<p style="margin:6px 0 0;font-size:13px;"><em>' + escapeHtml(r.explanation) + '</em></p>' : '') +
                '</div>'
            );
        }).join('');

        modalBody.innerHTML = html;
        modalFooter.innerHTML =
            '<button type="button" class="btn btn-secondary" id="retakeBtn">Retake</button>' +
            '<button type="button" class="btn btn-primary" id="doneBtn">Done</button>';

        document.getElementById('retakeBtn').addEventListener('click', renderTakeView);
        document.getElementById('doneBtn').addEventListener('click', function () {
            closeModal();
            window.location.reload(); // refresh results history
        });
    }

    async function takeQuiz(quizId) {
        openModal();
        modalTitle.textContent = 'Loading quiz...';
        modalBody.innerHTML = '<em style="color:var(--color-gray-500);">Loading...</em>';
        modalFooter.innerHTML = '';

        try {
            const res = await fetch(QUIZ_URL + '?action=take&quiz_id=' + encodeURIComponent(quizId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            if (!data.success || !data.quiz) {
                modalTitle.textContent = 'Error';
                modalBody.innerHTML = '<p>' + escapeHtml(data.message || 'Quiz not found.') + '</p>';
                modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" id="cancelQuizBtn2">Close</button>';
                document.getElementById('cancelQuizBtn2').addEventListener('click', closeModal);
                return;
            }

            currentQuiz = data.quiz;

            if (!currentQuiz.questions.length) {
                modalTitle.textContent = currentQuiz.title;
                modalBody.innerHTML = '<p>This quiz has no questions.</p>';
                modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" id="cancelQuizBtn3">Close</button>';
                document.getElementById('cancelQuizBtn3').addEventListener('click', closeModal);
                return;
            }

            renderTakeView();
        } catch (err) {
            modalTitle.textContent = 'Error';
            modalBody.innerHTML = '<p>Could not load the quiz.</p>';
        }
    }

    document.querySelectorAll('.take-quiz-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            takeQuiz(btn.getAttribute('data-quiz-id'));
        });
    });

    if (document.getElementById('quizCloseBtn')) {
        document.getElementById('quizCloseBtn').addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }
})();
