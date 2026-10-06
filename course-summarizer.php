<?php
$page_title = 'Course Summarizer';
$active_page = 'courses';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$courses = Course::get_user_courses($user_id);

// Effective upload cap for the client (configured cap vs host upload_max_filesize)
$pdf_client_max = min(PDF_UPLOAD_MAX_SIZE, ini_bytes(ini_get('upload_max_filesize')));

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<!-- Course Summarizer Header -->
<header class="page-heading">
    <h1>Course Summarizer</h1>
    <p>Transform lengthy course materials into concise summaries</p>
</header>

<div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Summarize Course Material</h3>

                        <input type="hidden" name="csrf_token" id="csrf-token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="form-group">
                            <label for="course" class="form-label">Related Course (Optional)</label>
                            <select class="form-control" id="course" name="course">
                                <option value="">Select a course...</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Course Material (PDF) *</label>

                            <!-- Upload zone -->
                            <div class="pdf-upload-zone" id="pdf-upload-zone">
                                <span class="pdf-upload-icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                </span>
                                <button type="button" class="btn btn-primary" id="pdf-add-btn">Upload a file</button>
                                <small class="form-text">Upload a PDF document &mdash; max <?php echo (int)round($pdf_client_max / 1048576); ?> MB</small>
                                <input type="file" id="pdf-input" accept="application/pdf,.pdf" style="display: none;">
                            </div>

                            <!-- Selected PDF file card -->
                            <div class="pdf-file-card" id="pdf-file-card" style="display: none;">
                                <span class="pdf-file-icon" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                </span>
                                <span class="pdf-file-meta">
                                    <span class="pdf-file-name" id="pdf-file-name"></span>
                                    <span class="pdf-file-size" id="pdf-file-size"></span>
                                </span>
                                <span class="pdf-progress" id="pdf-progress-wrap" aria-hidden="true"><span class="pdf-progress-fill" id="pdf-progress-fill"></span></span>
                                <span class="pdf-upload-state" id="pdf-upload-state"></span>
                                <button type="button" class="pdf-remove-btn" id="pdf-remove-btn" title="Remove file" aria-label="Remove selected PDF">&times;</button>
                            </div>
                        </div>

                            <!-- PDF actions -->
                            <div class="pdf-actions" id="pdf-actions" style="display: none;">
                                <button type="button" class="btn btn-primary" id="pdf-summarize-btn">Summarize PDF</button>
                            </div>

                            <!-- PDF loading state -->
                            <div class="alert alert-info pdf-loading-box" id="pdf-loading" style="display: none;" role="status" aria-live="polite">
                                <span class="spinner"></span> <strong>Analyzing your course material...</strong>
                                <div id="pdf-status-text" style="font-size: var(--font-size-sm); margin-top: var(--spacing-xs); color: inherit;">Extracting text from your PDF</div>
                            </div>

                            <!-- PDF error state -->
                            <div class="alert alert-danger" id="pdf-error" style="display: none;">
                                <span id="pdf-error-message"></span>
                                <button type="button" class="btn btn-sm btn-outline" id="pdf-retry-btn" style="margin-left: var(--spacing-md);">Try again</button>
                            </div>
                    </div>
                </div>
                        
                        <!-- PDF Summary Result -->
                        <div id="pdf-result" style="margin-top: var(--spacing-xl); display: none;">
                            <div class="d-flex flex-wrap justify-content-between align-items-start" style="gap: var(--spacing-md); margin-bottom: var(--spacing-sm);">
                                <div>
                                    <h4 style="color: var(--color-primary-dark); margin: 0 0 4px 0;">Study Guide</h4>
                                    <p id="pdf-result-source" style="font-size: var(--font-size-xs); color: var(--color-gray-500); margin: 0;"></p>
                                </div>
                                <button type="button" class="btn btn-primary" id="pdf-download-btn" title="Download your study guide as a PDF">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Download PDF
                                </button>
                            </div>
                            <div id="pdf-study-guide"></div>
                        </div>

<script>
(function () {
    'use strict';

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    const pdfUploadZone = document.getElementById('pdf-upload-zone');

    // ===================== PDF Upload & Summarization =====================
    const PDF_MAX_SIZE = <?php echo (int)$pdf_client_max; ?>;
    const pdfApiUrl = '<?php echo app_url('api/pdf.php'); ?>';
    const pdfGenerateUrl = '<?php echo app_url('api/generate_summary_pdf.php'); ?>';
    const csrfToken = document.getElementById('csrf-token').value;

    const pdfInput        = document.getElementById('pdf-input');
    const pdfAddBtn       = document.getElementById('pdf-add-btn');
    const pdfFileCard     = document.getElementById('pdf-file-card');
    const pdfFileName     = document.getElementById('pdf-file-name');
    const pdfFileSize     = document.getElementById('pdf-file-size');
    const pdfProgressWrap = document.getElementById('pdf-progress-wrap');
    const pdfProgressFill = document.getElementById('pdf-progress-fill');
    const pdfUploadState  = document.getElementById('pdf-upload-state');
    const pdfRemoveBtn    = document.getElementById('pdf-remove-btn');
    const pdfActions      = document.getElementById('pdf-actions');
    const pdfSummarizeBtn = document.getElementById('pdf-summarize-btn');
    const pdfLoading      = document.getElementById('pdf-loading');
    const pdfStatusText   = document.getElementById('pdf-status-text');
    const pdfError        = document.getElementById('pdf-error');
    const pdfErrorMessage = document.getElementById('pdf-error-message');
    const pdfRetryBtn     = document.getElementById('pdf-retry-btn');
    const pdfResult       = document.getElementById('pdf-result');
    const pdfDownloadBtn  = document.getElementById('pdf-download-btn');

    let pdfToken = null;
    let pdfStatusTimer = null;
    let studyGuideData = null;
    let studyGuideSource = null;

    function formatBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    function showPdfError(message) {
        pdfErrorMessage.textContent = message;
        pdfError.style.display = 'block';
    }

    function hidePdfMessages() {
        pdfError.style.display = 'none';
        pdfLoading.style.display = 'none';
        stopPdfStatusRotation();
    }

    function resetPdfSelection() {
        pdfToken = null;
        pdfFileCard.style.display = 'none';
        pdfActions.style.display = 'none';
        pdfProgressWrap.style.visibility = 'hidden';
        pdfProgressFill.style.width = '0%';
        pdfUploadState.textContent = '';
        pdfInput.value = '';
        hidePdfMessages();
    }

    function startPdfStatusRotation() {
        const stages = [
            'Extracting text from your PDF',
            'Analyzing your course material',
            'Identifying key concepts and definitions',
            'Composing your study summary'
        ];
        let i = 0;
        pdfStatusText.textContent = stages[0];
        stopPdfStatusRotation();
        pdfStatusTimer = setInterval(function () {
            i = (i + 1) % stages.length;
            pdfStatusText.textContent = stages[i] + '...';
        }, 6000);
    }

    function stopPdfStatusRotation() {
        if (pdfStatusTimer) { clearInterval(pdfStatusTimer); pdfStatusTimer = null; }
    }

    pdfAddBtn.addEventListener('click', function () {
        pdfInput.click();
    });

    pdfUploadZone.addEventListener('click', function (e) {
        if (e.target !== pdfAddBtn) {
            pdfInput.click();
        }
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
        pdfUploadZone.addEventListener(evt, function (e) {
            e.preventDefault();
            pdfUploadZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
        pdfUploadZone.addEventListener(evt, function (e) {
            e.preventDefault();
            pdfUploadZone.classList.remove('dragover');
        });
    });

    pdfUploadZone.addEventListener('drop', function (e) {
        const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) {
            handlePdfFile(file);
        }
    });

    function handlePdfFile(file) {
        if (!file) return;

        // Client-side pre-validation (server re-validates everything)
        const isPdfName = /\.pdf$/i.test(file.name);
        const isPdfMime = file.type === 'application/pdf' || file.type === '' || file.type === 'application/x-pdf';
        if (!isPdfName || !isPdfMime) {
            showPdfError('Only PDF files are supported. Please select a .pdf document.');
            pdfInput.value = '';
            return;
        }
        if (file.size > PDF_MAX_SIZE) {
            showPdfError('"' + file.name + '" is too large (' + formatBytes(file.size) + '). Maximum allowed size is ' + formatBytes(PDF_MAX_SIZE) + '.');
            pdfInput.value = '';
            return;
        }
        if (file.size === 0) {
            showPdfError('The selected file appears to be empty.');
            pdfInput.value = '';
            return;
        }

        hidePdfMessages();
        uploadPdf(file);
    }

    pdfInput.addEventListener('change', function () {
        handlePdfFile(this.files && this.files[0]);
    });

    function uploadPdf(file) {
        // Show the card immediately in "uploading" state
        pdfFileName.textContent = file.name;
        pdfFileSize.textContent = formatBytes(file.size);
        pdfUploadState.textContent = 'Uploading...';
        pdfUploadState.classList.remove('text-success');
        pdfFileCard.style.display = 'flex';
        pdfActions.style.display = 'none';
        pdfProgressWrap.style.visibility = 'visible';
        pdfProgressFill.style.width = '0%';

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('pdf', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', pdfApiUrl + '?action=upload');

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                pdfProgressFill.style.width = pct + '%';
                pdfUploadState.textContent = pct < 100 ? 'Uploading... ' + pct + '%' : 'Processing...';
            }
        });

        xhr.addEventListener('load', function () {
            let data = null;
            try { data = JSON.parse(xhr.responseText); } catch (err) { data = null; }
            if (!data || !data.success) {
                resetPdfSelection();
                showPdfError((data && data.message) ? data.message : 'Upload failed. Please try again.');
                return;
            }
            pdfToken = data.token;
            pdfProgressWrap.style.visibility = 'hidden';
            pdfUploadState.textContent = 'Ready';
            pdfUploadState.classList.add('text-success');
            pdfActions.style.display = 'flex';
        });

        xhr.addEventListener('error', function () {
            resetPdfSelection();
            showPdfError('Upload failed due to a network error. Please try again.');
        });

        xhr.send(fd);
    }

    pdfRemoveBtn.addEventListener('click', function () {
        if (pdfToken) {
            fetch(pdfApiUrl + '?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ csrf_token: csrfToken, token: pdfToken })
            }).catch(function () {});
        }
        resetPdfSelection();
    });

    pdfSummarizeBtn.addEventListener('click', function () {
        if (!pdfToken) return;

        pdfSummarizeBtn.disabled = true;
        pdfActions.style.display = 'none';
        pdfResult.style.display = 'none';
        pdfError.style.display = 'none';
        pdfLoading.style.display = 'block';
        startPdfStatusRotation();

        fetch(pdfApiUrl + '?action=summarize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ csrf_token: csrfToken, token: pdfToken })
        })
        .then(response => response.json())
        .then(data => {
            stopPdfStatusRotation();
            pdfLoading.style.display = 'none';
            pdfSummarizeBtn.disabled = false;

            if (!data.success) {
                showPdfError(data.message || 'Could not summarize this PDF. Please try again.');
                if (pdfToken) { pdfActions.style.display = 'flex'; }
                return;
            }

            // Success consumes the uploaded file
            pdfToken = null;
            studyGuideData = data.summary || {};
            studyGuideSource = data.source || {};
            renderStudyGuide(studyGuideData, studyGuideSource);
        })
        .catch(error => {
            console.error('PDF summarization error:', error);
            stopPdfStatusRotation();
            pdfLoading.style.display = 'none';
            pdfSummarizeBtn.disabled = false;
            showPdfError('Something went wrong while processing your PDF. Please try again.');
            if (pdfToken) { pdfActions.style.display = 'flex'; }
        });
    });

    pdfRetryBtn.addEventListener('click', function () {
        pdfError.style.display = 'none';
        if (pdfToken) {
            pdfActions.style.display = 'flex';
        } else {
            pdfInput.click();
        }
    });

    // ===================== Study guide rendering =====================

    function renderStudyGuide(summary, source) {
        const guide = document.getElementById('pdf-study-guide');
        guide.innerHTML = '';

        document.getElementById('pdf-result-source').textContent =
            (source.name ? source.name : '') +
            (source.size ? ' \u2022 ' + formatBytes(source.size) : '');

        const str = function (s) { return String(s == null ? '' : s).trim(); };

        const proseBlock = function (text) {
            return '<div class="pdf-sec-body">' + escapeHtml(str(text)).replace(/\n/g, '<br>') + '</div>';
        };
        const listBlock = function (items) {
            return '<ul class="pdf-sec-body pdf-sec-list">' +
                items.map(function (it) { return '<li>' + escapeHtml(str(it)) + '</li>'; }).join('') +
                '</ul>';
        };
        const pairsBlock = function (items, labelKey, bodyKey) {
            const rows = items.map(function (it) {
                const label = str(it[labelKey]);
                const body = str(it[bodyKey]);
                if (label === '' && body === '') return '';
                return '<div class="pdf-pair-item"><strong>' + escapeHtml(label === '' ? '•' : label) + '</strong>'
                    + (body ? (label !== '' ? ': ' : ' ') + escapeHtml(body).replace(/\n/g, '<br>') : '') + '</div>';
            }).join('');
            return '<div class="pdf-sec-body">' + rows + '</div>';
        };
        const topicsBlock = function (items) {
            return '<div class="pdf-sec-body">' + items.map(function (t) {
                const title = str(t.title);
                const body = str(t.content);
                let html = title ? '<p class="pdf-topic-title">' + escapeHtml(title) + '</p>' : '';
                if (body) html += '<div>' + escapeHtml(body).replace(/\n/g, '<br>') + '</div>';
                return html;
            }).join('') + '</div>';
        };
        const tablesBlock = function (items) {
            return items.map(function (t) {
                const rows = Array.isArray(t.rows) ? t.rows : [];
                if (!rows.length) return '';
                const head = rows[0] || [];
                const bodyRows = rows.slice(1);
                let html = '<div class="pdf-table-title">' + escapeHtml(str(t.title || 'Comparison')) + '</div>';
                html += '<div class="table-responsive"><table class="table table-sm table-bordered pdf-table"><thead><tr>' +
                    head.map(function (c) { return '<th>' + escapeHtml(str(c)) + '</th>'; }).join('') +
                    '</tr></thead><tbody>';
                bodyRows.forEach(function (r) {
                    html += '<tr>' + r.map(function (c) { return '<td>' + escapeHtml(str(c)) + '</td>'; }).join('') + '</tr>';
                });
                html += '</tbody></table></div>';
                return html;
            }).join('');
        };

        function sectionBody(key, value) {
            switch (key) {
                case 'overview':
                case 'conclusion':
                    return str(value) !== '' ? proseBlock(value) : '';
                case 'learning_objectives':
                case 'important_points':
                case 'exam_focus':
                case 'quick_revision':
                    return Array.isArray(value) && value.length ? listBlock(value) : '';
                case 'topics':
                    return Array.isArray(value) && value.length ? topicsBlock(value) : '';
                case 'key_concepts':
                    return Array.isArray(value) && value.length ? pairsBlock(value, 'concept', 'explanation') : '';
                case 'definitions':
                    return Array.isArray(value) && value.length ? pairsBlock(value, 'term', 'definition') : '';
                case 'examples':
                case 'formulas_processes':
                    return Array.isArray(value) && value.length ? pairsBlock(value, 'title', 'explanation') : '';
                case 'comparisons':
                    return Array.isArray(value) && value.length ? tablesBlock(value) : '';
                default:
                    return '';
            }
        }

        const plan = [
            ['overview', 'Overview'],
            ['learning_objectives', 'Learning Objectives'],
            ['topics', 'Main Topics'],
            ['key_concepts', 'Key Concepts'],
            ['definitions', 'Definitions & Terms'],
            ['important_points', 'Important Points'],
            ['examples', 'Examples'],
            ['comparisons', 'Comparisons'],
            ['formulas_processes', 'Formulas & Processes'],
            ['exam_focus', 'Exam Focus'],
            ['quick_revision', 'Quick Revision'],
            ['conclusion', 'Conclusion']
        ];

        let html = '';
        let index = 0;
        plan.forEach(function (step) {
            const body = sectionBody(step[0], summary[step[0]]);
            if (body === '') return;
            index++;
            html += '<div class="card mb-3"><div class="card-body">'
                + '<h5 class="pdf-sec-title">' + index + '. ' + step[1] + '</h5>'
                + body
                + '</div></div>';
        });

        guide.innerHTML = html !== ''
            ? html
            : '<div class="card"><div class="card-body"><em>No study guide content was generated. Please try again.</em></div></div>';

        pdfResult.style.display = 'block';
        pdfResult.scrollIntoView({ behavior: 'smooth' });
    }

    // ===================== Study guide PDF download =====================

    pdfDownloadBtn.addEventListener('click', function () {
        if (!studyGuideData) {
            showPdfError('Please generate a study guide before downloading the PDF.');
            return;
        }
        if (pdfDownloadBtn.dataset.busy === '1') return;

        const original = pdfDownloadBtn.innerHTML;
        pdfDownloadBtn.dataset.busy = '1';
        pdfDownloadBtn.disabled = true;
        pdfDownloadBtn.classList.add('pdf-btn-busy');
        pdfDownloadBtn.innerHTML = '<span class="spinner spinner-sm"></span> Preparing your study guide PDF...';

        const finish = function () {
            pdfDownloadBtn.dataset.busy = '0';
            pdfDownloadBtn.disabled = false;
            pdfDownloadBtn.classList.remove('pdf-btn-busy');
            pdfDownloadBtn.innerHTML = original;
        };

        const courseSel = document.getElementById('course');
        const courseLabel = courseSel.selectedOptions && courseSel.selectedOptions[0]
            ? courseSel.selectedOptions[0].textContent.trim()
            : '';

        fetch(pdfGenerateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                course_name: courseLabel,
                source: studyGuideSource || {},
                summary: studyGuideData
            })
        })
        .then(function (res) {
            const contentType = res.headers.get('content-type') || '';
            if (!res.ok || contentType.indexOf('application/pdf') === -1) {
                return res.json().then(function (j) {
                    throw new Error((j && j.message) ? j.message : 'Could not generate the PDF. Please try again.');
                });
            }
            return res.blob().then(function (blob) {
                let filename = 'AI_Study_Guide.pdf';
                const cd = res.headers.get('content-disposition') || '';
                const matches = cd.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                if (matches) filename = matches[1].replace(/["']/g, '');

                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = /\.pdf$/i.test(filename) ? filename : filename + '.pdf';
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
            });
        })
        .then(function () { finish(); })
        .catch(function (err) {
            console.error('Study guide PDF download error:', err);
            const msg = (err && err.message && err.message !== 'Failed to fetch')
                ? err.message
                : 'Could not generate the PDF. Please try again.';
            showPdfError(msg);
            finish();
        });
    });
})();
</script>

<?php
require_once __DIR__ . '/views/partials/footer.php';
?>
