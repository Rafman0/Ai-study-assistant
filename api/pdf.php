<?php
/**
 * PDF Upload & Summarization endpoint
 *
 * Security model:
 * - Requires an authenticated session + CSRF token for every action
 * - Validates real file type via %PDF- magic bytes AND extension whitelist
 * - Rejects executables/scripts/unrelated types before anything is written to disk
 * - Stored under /storage/uploads with random hex names; .htaccess blocks direct access
 * - Session-bound upload tokens: only the uploader session may summarize/delete a file
 * - Files are deleted after successful summarization and garbage-collected after 2 hours
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/AiClient.php';
require_once __DIR__ . '/../utils/PdfTextExtractor.php';
require_once __DIR__ . '/../utils/StudyGuide.php';

const PDF_SESSION_KEY   = 'pdf_uploads';
const PDF_UPLOAD_TTL    = 7200;  // seconds an uploaded file stays available
const PDF_SINGLE_LIMIT  = 16000; // characters sent to the AI in one request
const PDF_CHUNK_SIZE    = 14000; // per-chunk size for large documents

/**
 * Effective upload limit: our configured cap, further limited by the host's
 * upload_max_filesize (cannot exceed what PHP itself accepts)
 */
function pdf_effective_max_size() {
    return min(PDF_UPLOAD_MAX_SIZE, ini_bytes(ini_get('upload_max_filesize')));
}

function pdf_max_size_message() {
    return 'File is too large. Maximum allowed size is ' . round(pdf_effective_max_size() / 1048576, 1) . 'MB.';
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    if ($method !== 'POST' || !is_logged_in()) {
        throw new Exception('Unauthorized');
    }

    // Friendly early rejection when the request exceeds server limits
    // (PHP empties $_POST/$_FILES when content-length exceeds post_max_size)
    $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($action === 'upload' && $content_length > pdf_effective_max_size() + 65536) {
        throw new Exception(pdf_max_size_message());
    }

    // Every action requires a valid CSRF token
    $csrf = '';
    if ($action === 'upload') {
        $csrf = $_POST['csrf_token'] ?? '';
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $csrf = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    }
    if (!verify_csrf_token($csrf)) {
        throw new Exception('Invalid security token - please reload the page and try again');
    }

    pdf_gc_uploads();

    switch ($action) {
        case 'upload':
            $response = pdf_handle_upload();
            break;

        case 'delete':
            $response = pdf_handle_delete($input);
            break;

        case 'summarize':
            $response = pdf_handle_summarize($input);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    error_log('PDF API error (' . $action . '): ' . $e->getMessage());
    $message = $e->getMessage();
    // Never leak internals to the client
    if ($message === '' || preg_match('/(SQL|stack|path)/i', $message)) {
        $message = 'Something went wrong. Please try again.';
    }
    $response = ['success' => false, 'message' => $message];
}

echo json_encode($response);
exit;


/** Garbage-collect expired uploads for this session */
function pdf_gc_uploads() {
    if (empty($_SESSION[PDF_SESSION_KEY]) || !is_array($_SESSION[PDF_SESSION_KEY])) {
        return;
    }
    foreach ($_SESSION[PDF_SESSION_KEY] as $token => $info) {
        if ((time() - (int)$info['time']) > PDF_UPLOAD_TTL || !is_file($info['file'])) {
            if (!empty($info['file']) && is_file($info['file'])) {
                @unlink($info['file']);
            }
            unset($_SESSION[PDF_SESSION_KEY][$token]);
        }
    }
}

/** Ensure the protected storage directory exists */
function pdf_storage_dir() {
    $dir = PDF_UPLOAD_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Block direct web access (Apache)
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\n");
    }
    return $dir;
}

/** Sanitize the original filename for safe display only (never used as storage name) */
function pdf_safe_display_name($name) {
    $name = str_replace(["\0", "/", "\\", ".."], '', (string)$name);
    $name = strip_tags($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        $name = 'document.pdf';
    }
    if (strlen($name) > 100) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $name = substr($name, 0, 95) . ($ext ? '.' . $ext : '');
    }
    return $name;
}

/** Validate and persist an uploaded PDF; returns session token for later actions */
function pdf_handle_upload() {
    if (empty($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
        throw new Exception('No file was received');
    }
    $f = $_FILES['pdf'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception(pdf_max_size_message());
        }
        throw new Exception('Upload failed. Please try again.');
    }

    $max_size = pdf_effective_max_size();
    if (($f['size'] ?? 0) <= 0) {
        throw new Exception('The selected file appears to be empty');
    }
    if (($f['size'] ?? 0) > $max_size) {
        throw new Exception(pdf_max_size_message());
    }

    if (!is_uploaded_file($f['tmp_name'])) {
        throw new Exception('Invalid upload source');
    }

    // Extension whitelist (case-insensitive)
    $orig_name = pdf_safe_display_name($f['name'] ?? 'document.pdf');
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        throw new Exception('Only PDF files are supported. Please select a .pdf document.');
    }

    // Real content validation: must begin with %PDF-
    $head = file_get_contents($f['tmp_name'], false, null, 0, 1024);
    if ($head === false || substr(ltrim($head), 0, 5) !== '%PDF-') {
        throw new Exception('This file is not a valid PDF. Only genuine PDF documents are accepted.');
    }

    // Move into protected storage under a random name (never user-controlled)
    $dir = pdf_storage_dir();
    $token = bin2hex(random_bytes(16));
    $stored = $dir . DIRECTORY_SEPARATOR . $token . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], $stored)) {
        throw new Exception('Could not save the uploaded file');
    }

    $_SESSION[PDF_SESSION_KEY][$token] = [
        'file' => $stored,
        'name' => $orig_name,
        'size' => (int)$f['size'],
        'time' => time(),
    ];

    return [
        'success' => true,
        'token'   => $token,
        'name'    => $orig_name,
        'size'    => (int)$f['size'],
    ];
}

/** Remove an uploaded file belonging to this session */
function pdf_handle_delete($input) {
    $token = $input['token'] ?? '';
    if (isset($_SESSION[PDF_SESSION_KEY][$token])) {
        if (!empty($_SESSION[PDF_SESSION_KEY][$token]['file']) && is_file($_SESSION[PDF_SESSION_KEY][$token]['file'])) {
            @unlink($_SESSION[PDF_SESSION_KEY][$token]['file']);
        }
        unset($_SESSION[PDF_SESSION_KEY][$token]);
    }
    return ['success' => true];
}

/** Look up a session upload or fail with a friendly message */
function pdf_get_upload($input) {
    $token = $input['token'] ?? '';
    if (!$token || empty($_SESSION[PDF_SESSION_KEY][$token])) {
        throw new Exception('Your uploaded file is no longer available. Please select it again.');
    }
    $info = $_SESSION[PDF_SESSION_KEY][$token];
    if (!is_file($info['file'])) {
        unset($_SESSION[PDF_SESSION_KEY][$token]);
        throw new Exception('Your uploaded file is no longer available. Please select it again.');
    }
    return $info;
}

/**
 * Split extracted text into chunks at paragraph/sentence boundaries
 * @return array List of string chunks
 */
function pdf_chunk_text($text) {
    if (strlen($text) <= PDF_CHUNK_SIZE) {
        return [$text];
    }

    $chunks = [];
    $paragraphs = preg_split("/(\n{2,}|\n(?=[A-Z0-9]))/", $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    $current = '';
    foreach ($paragraphs as $p) {
        while (strlen($p) > PDF_CHUNK_SIZE) {
            // Hard-split very long paragraphs at sentence boundary near the limit
            $cut = strrpos(substr($p, 0, PDF_CHUNK_SIZE), '. ');
            if ($cut === false || $cut < PDF_CHUNK_SIZE * 0.5) {
                $cut = PDF_CHUNK_SIZE - 1;
            }
            $part = substr($p, 0, $cut + 1);
            if (trim($current) !== '') {
                $chunks[] = trim($current);
                $current = '';
            }
            $chunks[] = trim($part);
            $p = substr($p, $cut + 1);
        }
        if (strlen($current) + strlen($p) + 1 > PDF_CHUNK_SIZE && trim($current) !== '') {
            $chunks[] = trim($current);
            $current = $p;
        } else {
            $current .= ($current === '' ? '' : "\n") . $p;
        }
    }
    if (trim($current) !== '') {
        $chunks[] = trim($current);
    }

    return array_values(array_filter($chunks, function ($c) { return trim($c) !== ''; }));
}

/**
 * Ask the AI for a strict-JSON structured study guide and parse leniently
 * @return array|null Normalized study guide, or null when the call fails
 */
function pdf_request_structured_summary($prompt_text) {
    $system = 'You are an expert academic tutor that transforms course material into a DETAILED, well-structured STUDY GUIDE for a student preparing for exams. '
        . 'Always respond with ONLY valid JSON. No markdown fences, no code blocks, no extra commentary. '
        . 'Use exactly this JSON shape: ' . StudyGuide::schemaDoc() . '. '
        . 'Guidelines: '
        . '1) Match the depth and length of the guide to the amount of source material. A short 1-2 page handout gets a concise guide (brief overview, a handful of bullets, few definitions and examples). '
        . 'A long multi-chapter document gets a substantially detailed guide with thorough per-topic sections, many definitions, concepts, examples and comparisons grounded in the text. '
        . '2) Only include content that is actually present in the provided material. NEVER invent facts, numbers, terms or references. '
        . 'If a section has no material at all, return [] for lists or an empty string for strings - do not make anything up. '
        . '3) Write clean, professional study-note prose for a student. Use plain text only: no HTML, no Markdown, no LaTeX. '
        . 'Put formulas in readable plain text (e.g. "E = mc^2", "O(n log n)"). '
        . '4) "course_title" must be a concise descriptive title for the material, derived from the document itself. '
        . '5) In "comparisons", the first row of each "rows" array is the header row of aspect labels followed by the compared items; keep rows rectangular. '
        . '6) Keep the JSON valid, complete and non-truncated.';

    $data = [
        'model'       => AI_MODEL,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $prompt_text],
        ],
        'max_tokens'  => 4096,
        'temperature' => 0.4,
    ];

    $content = call_ai_api($data, 150);
    if ($content === null) {
        return null;
    }

    $decoded = json_decode(trim($content), true);
    if (!is_array($decoded)) {
        // Model wrapped the JSON in prose/fences - grab outermost braces
        $start = strpos($content, '{');
        $end   = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($candidate)) {
                $decoded = $candidate;
            }
        }
    }

    return StudyGuide::normalize($decoded);
}

/** Full summarize flow: extract -> chunk -> map-reduce -> coherent structured summary */
function pdf_handle_summarize($input) {
    @set_time_limit(300);
    $info = pdf_get_upload($input);
    $file = $info['file'];

    try {
        $extracted = PdfTextExtractor::extract($file);
    } catch (Exception $e) {
        // Friendly, actionable message for the student
        throw new Exception($e->getMessage());
    }

    $text = $extracted['text'];
    $meta_note = 'Document: "' . $info['name'] . '"';
    if ($extracted['pages'] > 0) {
        $meta_note .= ' (' . $extracted['pages'] . ' page' . ($extracted['pages'] === 1 ? '' : 's') . ')';
    }

    if (strlen($text) <= PDF_SINGLE_LIMIT) {
        $summary = pdf_request_structured_summary(
            $meta_note . "\n\nCreate a comprehensive study summary of the following course material.\n\n---\n" . $text . "\n---"
        );
    } else {
        // Large PDF: summarize chunks first, then combine into ONE coherent summary
        $chunks = pdf_chunk_text($text);
        $partial_notes = [];

        foreach ($chunks as $i => $chunk) {
            if (count($partial_notes) >= 8) {
                // Safety valve for extremely long documents
                break;
            }
            $map_prompt = 'These are part ' . ($i + 1) . ' of ' . count($chunks)
                . ' from a longer course document. Write concise study notes capturing the key ideas, facts, concepts, definitions and examples from THIS part only, under the requested study-guide structure.'
                . "\n\n---\n" . $chunk . "\n---";
            $notes = pdf_request_structured_summary($map_prompt);
            if ($notes === null) {
                continue;
            }
            $flattened = StudyGuide::flattenNotes($notes);
            if ($flattened !== '') {
                $partial_notes[] = $flattened;
            }
        }

        if (empty($partial_notes)) {
            throw new Exception('The AI service could not process this document right now. Please try again shortly.');
        }

        $summary = pdf_request_structured_summary(
            "You are given study notes extracted from consecutive sections of ONE course document ({$meta_note}). "
            . "Combine them into ONE coherent, well-organized study guide written by a single author. "
            . "Merge duplicates, remove repetition, preserve the logical flow of topics, and keep every section of the required JSON structure filled with the best content available. "
            . "Use the full depth available: this is a long document, so produce a detailed guide.\n\n"
            . implode("\n\n", $partial_notes)
        );

        if ($summary === null) {
            throw new Exception('The AI service could not combine the section summaries. Please try again shortly.');
        }
    }

    if ($summary === null) {
        throw new Exception('The AI summarization service is temporarily unavailable. Please try again in a moment.');
    }

    // Persist for platform analytics (best-effort)
    try {
        $pdb = get_db_connection();
        if ($pdb) {
            $combined = trim(($summary['course_title'] ?? '')
                . "\n" . ($summary['overview'] ?? '')
                . "\n\n" . implode('; ', array_slice((array)($summary['important_points'] ?? []), 0, 10)));
            $stmt = $pdb->prepare("INSERT INTO summaries (user_id, course_id, source_type, source_name, summary) VALUES (?, NULL, 'pdf', ?, ?)");
            $stmt->execute([get_current_user_id(), $info['name'], $combined]);
            Auth::log_activity(get_current_user_id(), 'summary_generated', null, 'PDF summary: ' . mb_substr($info['name'], 0, 100));
        }
    } catch (Exception $pe) {
        error_log('PDF summary persist error: ' . $pe->getMessage());
    }

    // Success: remove the stored file (privacy) but keep nothing reusable behind
    if (is_file($file)) {
        @unlink($file);
    }
    unset($_SESSION[PDF_SESSION_KEY][$input['token']]);

    return [
        'success' => true,
        'summary' => $summary,
        'source'  => ['name' => $info['name'], 'size' => (int)$info['size']],
    ];
}
