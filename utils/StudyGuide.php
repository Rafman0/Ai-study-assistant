<?php
/**
 * Study Guide structured-data helpers
 *
 * Shared between the AI summarization endpoint (api/pdf.php) and the
 * PDF rendering endpoint (api/generate_summary_pdf.php).
 *
 * - defineStudyGuideSchema(): the JSON shape the AI is asked to produce
 * - normalize(): leniently parse any decoded value into a safe guide array
 * - flattenNotes(): collapse a guide into plain text (used for map-reduce)
 * - pdfText()/slug(): sanitize values for FPDF (Latin-1) output and filenames
 */

class StudyGuide
{
    /** Top-level sections of the study guide (AI-generated JSON keys) */
    public static function fields() {
        return [
            'course_title',
            'overview',
            'learning_objectives',
            'topics',
            'key_concepts',
            'definitions',
            'important_points',
            'examples',
            'comparisons',
            'formulas_processes',
            'exam_focus',
            'quick_revision',
            'conclusion',
        ];
    }

    /** Schema description embedded in the AI system prompt */
    public static function schemaDoc() {
        return '{'
            . '"course_title": string, '
            . '"overview": string, '
            . '"learning_objectives": [string, ...], '
            . '"topics": [{"title": string, "content": string}, ...], '
            . '"key_concepts": [{"concept": string, "explanation": string}, ...], '
            . '"definitions": [{"term": string, "definition": string}, ...], '
            . '"important_points": [string, ...], '
            . '"examples": [{"title": string, "explanation": string}, ...], '
            . '"comparisons": [{"title": string, "rows": [[string, ...], ...]}, ...], '
            . '"formulas_processes": [{"title": string, "explanation": string}, ...], '
            . '"exam_focus": [string, ...], '
            . '"quick_revision": [string, ...], '
            . '"conclusion": string'
            . '}';
    }

    /** Sanitize a value so it is always valid UTF-8 (recursive) */
    public static function cleanUtf8($value) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::cleanUtf8($v);
            }
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('//u', $value)) {
            return $value;
        }
        $value = (string)@iconv('UTF-8', 'UTF-8//IGNORE', $value);
        return is_string($value) ? $value : '';
    }

    /**
     * Leniently parse whatever the model returned into a normalized study guide.
     * Never throws; unknown inputs degrade to the raw overview.
     *
     * @param mixed $decoded
     * @return array
     */
    public static function normalize($decoded) {
        $fallback = array_fill_keys(self::fields(), '');
        $fallback['learning_objectives'] = [];
        $fallback['topics'] = [];
        $fallback['key_concepts'] = [];
        $fallback['definitions'] = [];
        $fallback['important_points'] = [];
        $fallback['examples'] = [];
        $fallback['comparisons'] = [];
        $fallback['formulas_processes'] = [];
        $fallback['exam_focus'] = [];
        $fallback['quick_revision'] = [];

        if (!is_array($decoded)) {
            $fallback['overview'] = self::cap(is_string($decoded) ? trim($decoded) : 'No study guide could be parsed.', 20000);
            return $fallback;
        }

        $out = $fallback;

        // --- prose fields -------------------------------------------------
        $out['course_title'] = self::cap(self::pickString($decoded, ['course_title', 'title', 'document_title', 'topic']), 400);
        $out['overview']     = self::cap(self::pickString($decoded, ['overview', 'summary']), 20000);
        $out['conclusion']   = self::cap(self::pickString($decoded, ['conclusion', 'final_summary']), 12000);

        // --- string-list fields -------------------------------------------
        $out['learning_objectives'] = self::capItems(self::pickList($decoded, ['learning_objectives', 'objectives']), 80, 1500);
        $out['important_points']    = self::capItems(self::pickList($decoded, ['important_points', 'key_points']), 80, 1500);
        $out['exam_focus']          = self::capItems(self::pickList($decoded, ['exam_focus', 'exam_tips', 'likely_exam_focus']), 60, 1500);
        $out['quick_revision']      = self::capItems(self::pickList($decoded, ['quick_revision', 'revision_notes', 'quick_review']), 60, 1500);

        // --- object-list fields (title + body) ------------------------------
        $out['topics']             = self::capPairs(self::pickPairs($decoded, ['topics', 'main_topics', 'sections'], 'title', 'content'), 60, 3000, 'title', 'content');
        $out['key_concepts']       = self::capPairs(self::pickPairs($decoded, ['key_concepts', 'important_concepts', 'core_concepts'], 'concept', 'explanation'), 60, 2000, 'concept', 'explanation');
        $out['definitions']        = self::capPairs(self::pickPairs($decoded, ['definitions', 'terms'], 'term', 'definition'), 60, 2000, 'term', 'definition');
        $out['examples']           = self::capPairs(self::pickPairs($decoded, ['examples', 'worked_examples'], 'title', 'explanation'), 40, 2500, 'title', 'explanation');
        $out['formulas_processes'] = self::capPairs(self::pickPairs($decoded, ['formulas_processes', 'formulas', 'processes', 'equations'], 'title', 'explanation'), 40, 2500, 'title', 'explanation');

        // --- comparison tables --------------------------------------------
        $tables = [];
        foreach ((array)($decoded['comparisons'] ?? ($decoded['comparison_tables'] ?? [])) as $t) {
            if (!is_array($t)) {
                continue;
            }
            $title = trim((string)($t['title'] ?? ($t['name'] ?? 'Comparison')));
            $rows = [];
            $rawRows = $t['rows'] ?? ($t['details'] ?? ($t['items'] ?? []));
            foreach ((array)$rawRows as $r) {
                if (is_string($r) && trim($r) !== '') {
                    $rows[] = [$title, trim($r)];
                } elseif (is_array($r)) {
                    $cells = [];
                    foreach ($r as $cell) {
                        $cells[] = self::cap(is_string($cell) ? trim($cell) : (is_scalar($cell) ? (string)$cell : ''), 500);
                    }
                    $cells = array_values(array_filter($cells, function ($c) { return $c !== ''; }));
                    if (count($cells) > 0) {
                        $rows[] = $cells;
                    }
                }
                if (count($rows) >= 40) {
                    break;
                }
            }
            $tables[] = ['title' => self::cap($title, 300), 'rows' => $rows];
        }
        $out['comparisons'] = $tables;

        return $out;
    }

    public static function isEmptyField($section, $value) {
        if (is_string($value)) {
            return trim($value) === '';
        }
        return empty($value);
    }

    /** Collapse a normalized guide into readable plain-text notes (map-reduce merge) */
    public static function flattenNotes($notes, $limits = []) {
        $parts = [];

        if (!empty($notes['overview'])) {
            $parts[] = 'Overview: ' . trim($notes['overview']);
        }
        foreach (array_slice((array)($notes['topics'] ?? []), 0, ($limits['topics'] ?? 4)) as $t) {
            if (is_array($t) && !empty($t['title'])) {
                $parts[] = (isset($t['content']) && $t['content'] !== '') ? $t['title'] . ":\n" . $t['content'] : $t['title'];
            }
        }
        foreach (array_slice((array)($notes['key_concepts'] ?? []), 0, ($limits['concepts'] ?? 6)) as $c) {
            if (is_array($c)) {
                $parts[] = 'Concept: ' . ($c['concept'] ?? '') . (($c['explanation'] ?? '') !== '' ? ' - ' . $c['explanation'] : '');
            }
        }
        foreach (array_slice((array)($notes['definitions'] ?? []), 0, ($limits['definitions'] ?? 6)) as $d) {
            if (is_array($d)) {
                $parts[] = (($d['term'] ?? '') !== '' ? $d['term'] . ': ' : 'Definition: ') . ($d['definition'] ?? '');
            }
        }
        foreach (array_slice((array)($notes['important_points'] ?? []), 0, ($limits['points'] ?? 8)) as $p) {
            $parts[] = '- ' . $p;
        }
        foreach (array_slice((array)($notes['formulas_processes'] ?? []), 0, ($limits['formulas'] ?? 4)) as $f) {
            if (is_array($f)) {
                $parts[] = 'Formula/Process: ' . ($f['title'] ?? '') . (($f['explanation'] ?? '') !== '' ? ' - ' . $f['explanation'] : '');
            }
        }
        foreach (array_slice((array)($notes['examples'] ?? []), 0, ($limits['examples'] ?? 3)) as $e) {
            if (is_array($e)) {
                $parts[] = 'Example: ' . ($e['title'] ?? '') . (($e['explanation'] ?? '') !== '' ? ' - ' . $e['explanation'] : '');
            }
        }
        foreach (array_slice((array)($notes['exam_focus'] ?? []), 0, ($limits['exam'] ?? 5)) as $x) {
            $parts[] = 'Exam focus: ' . $x;
        }
        if (!empty($notes['conclusion'])) {
            $parts[] = 'Conclusion: ' . trim($notes['conclusion']);
        }

        return trim(implode("\n\n", array_filter($parts, function ($p) { return trim($p) !== ''; })));
    }

    /** Sanitize text for FPDF output (core fonts are Latin-1) */
    public static function pdfText($value) {
        if (!is_string($value)) {
            return '';
        }
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = self::cleanUtf8($value);
        if (function_exists('iconv')) {
            $enc = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
            if ($enc !== false) {
                return $enc;
            }
        }
        $out = '';
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($value[$i]);
            $out .= ($code < 128) ? $value[$i] : '?';
        }
        return $out;
    }

    /** ASCII-safe slug for filenames */
    public static function slug($value) {
        $s = strtolower(trim((string)$value));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim($s, '_');
        return $s === '' ? 'Study_Guide' : substr($s, 0, 60);
    }

    // -- internals --------------------------------------------------------

    private static function cap($str, $max) {
        if (mb_strlen($str, 'UTF-8') > $max) {
            return mb_substr($str, 0, $max, 'UTF-8');
        }
        return $str;
    }

    private static function capItems($items, $countMax, $lenMax) {
        $out = [];
        foreach (array_slice($items, 0, $countMax) as $it) {
            $it = self::cap($it, $lenMax);
            if ($it !== '') {
                $out[] = $it;
            }
        }
        return $out;
    }

    private static function capPairs($pairs, $countMax, $lenMax, $a, $b) {
        $out = [];
        foreach (array_slice($pairs, 0, $countMax) as $p) {
            $p[$a] = self::cap($p[$a], $lenMax);
            $p[$b] = self::cap($p[$b], $lenMax);
            if ($p[$a] === '' && $p[$b] === '') {
                continue;
            }
            $out[] = $p;
        }
        return $out;
    }

    private static function pickString($decoded, $keys) {
        foreach ($keys as $k) {
            $v = $decoded[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim(self::cleanUtf8($v));
            }
        }
        return '';
    }

    private static function pickList($decoded, $keys) {
        $out = [];
        foreach ($keys as $k) {
            foreach ((array)($decoded[$k] ?? []) as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $out[] = trim(self::cleanUtf8($v));
                } elseif (is_array($v)) {
                    $t = trim((string)reset($v));
                    if ($t !== '') {
                        $out[] = trim(self::cleanUtf8($t));
                    }
                }
            }
        }
        return array_values(array_unique($out));
    }

    private static function pickPairs($decoded, $keys, $a, $b) {
        $out = [];
        foreach ($keys as $k) {
            foreach ((array)($decoded[$k] ?? []) as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $out[] = ['_a' => trim(self::cleanUtf8($v)), '_b' => ''];
                } elseif (is_array($v)) {
                    $va = trim((string)($v[$a] ?? ($v['name'] ?? ($v['_0'] ?? ''))));
                    $vb = trim((string)($v[$b] ?? ($v['description'] ?? ($v['details'] ?? ($v['explanation'] ?? ($v['content'] ?? ($v['body'] ?? '')))))));
                    $out[] = [$a => self::cleanUtf8($va), $b => self::cleanUtf8($vb)];
                }
            }
        }
        return $out;
    }
}