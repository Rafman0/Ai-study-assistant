<?php
/**
 * PDF Text Extractor
 *
 * Best-effort pure-PHP text extraction for PDF files (PHP 7.x compatible).
 * Handles the most common PDF layout: FlateDecode-compressed content streams
 * containing BT/ET text blocks with Tj / TJ operators.
 *
 * Known limitations (by design, kept dependency-free):
 * - Scanned/image-only PDFs contain no extractable text (caller shows a friendly error)
 * - Some CID/embedded-font encodings may produce partial or garbled text
 * - Encrypted PDFs are not supported
 */

class PdfTextExtractor {

    const MAX_TEXT_LENGTH = 500000;

    /**
     * Extract readable text from a PDF file
     * @param string $file_path Path to the PDF on disk
     * @return array ['text' => string, 'pages' => int]
     * @throws Exception when the file is not a valid/text-bearing PDF
     */
    public static function extract($file_path) {
        if (!is_file($file_path) || !is_readable($file_path)) {
            throw new Exception('PDF file could not be read');
        }

        $data = file_get_contents($file_path);
        if ($data === false || strlen($data) < 8) {
            throw new Exception('PDF file could not be read');
        }
        if (substr($data, 0, 5) !== '%PDF-') {
            throw new Exception('The file is not a valid PDF document');
        }

        $streams = self::extract_streams($data);
        $text = '';
        foreach ($streams as $stream) {
            if ($text !== '' && strlen($text) >= self::MAX_TEXT_LENGTH) {
                break;
            }
            $text .= self::extract_text_from_content($stream);
        }

        // Normalize whitespace while preserving paragraph structure
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/ ?\n ?/', "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

        $pages = preg_match_all('/\/Type\s*\/Page(?![sA-Za-z])/', $data);

        if (trim($text) === '') {
            throw new Exception('No selectable text was found in this PDF. It may be a scanned document or image-only PDF - please paste the material as text instead.');
        }

        return [
            'text'  => mb_substr($text, 0, self::MAX_TEXT_LENGTH, 'UTF-8'),
            'pages' => max($pages, 0),
        ];
    }

    /**
     * Pull raw stream payloads out of the PDF binary, inflating FlateDecode streams
     * @param string $data Raw PDF bytes
     * @return array List of decoded stream contents that look like page-content streams
     */
    private static function extract_streams($data) {
        $contents = [];
        $offset = 0;

        while (($pos = strpos($data, 'stream', $offset)) !== false) {
            // Ensure this is the stream keyword (not part of e.g. "endstream")
            if ($pos > 0 && preg_match('/(endstream|endobj)\s*$/', substr($data, 0, $pos))) {
                $offset = $pos + 6;
                continue;
            }

            $data_start = $pos + 6;
            // Skip EOL directly after the stream keyword
            if (substr($data, $data_start, 2) === "\r\n") {
                $data_start += 2;
            } elseif (substr($data, $data_start, 1) === "\n" || substr($data, $data_start, 1) === "\r") {
                $data_start += 1;
            }

            $end_pos = strpos($data, 'endstream', $data_start);
            if ($end_pos === false) {
                break;
            }

            $raw = substr($data, $data_start, $end_pos - $data_start);
            // Trim one trailing EOL before endstream
            $raw = preg_replace('/\r?\n$/', '', $raw);

            // Inspect the dictionary just before the stream keyword for its filter
            $dict_start = max(0, $pos - 400);
            $dict = substr($data, $dict_start, $pos - $dict_start);
            $is_flate = strpos($dict, '/FlateDecode') !== false;

            $decoded = null;
            if ($is_flate) {
                $decoded = @gzuncompress($raw);
                if ($decoded === false) {
                    $decoded = @gzinflate($raw);
                }
            } else {
                // Uncompressed stream - accept only if it contains text operators
                if (strpos($raw, 'BT') !== false || strpos($raw, 'Tj') !== false) {
                    $decoded = $raw;
                }
            }

            if (is_string($decoded) && (strpos($decoded, 'BT') !== false || strpos($decoded, 'Tj') !== false || strpos($decoded, 'TJ') !== false)) {
                $contents[] = $decoded;
            }

            $offset = $end_pos + 9;
        }

        return $contents;
    }

    /**
     * Extract human-readable text from a single page-content stream
     * @param string $content Decoded content stream
     * @return string Extracted text
     */
    private static function extract_text_from_content($content) {
        $out = '';
        // Sequentially match text-showing operators and line-movement operators
        $pattern = '/
            (\((?:[^()\\\\]|\\\\.)*\))\s*(Tj|\'|")      # (string) Tj  |  (string) \'  |  (string) "
          | (\[(?:[^\[\]\\\\]|\\\\.)*\])\s*TJ           # [(s1)(s2)...] TJ
          | T\*                                          # next line
          | Td                                           # move text position
          | TD                                           # move + leading
          | ET                                           # end text block
        /x';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return '';
        }

        foreach ($matches as $m) {
            if (!empty($m[1])) {
                // Single show-text operator
                $out .= self::unescape_pdf_string(substr($m[1], 1, -1));
            } elseif (!empty($m[4])) {
                // Array form TJ: concatenate its string elements
                preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $m[4], $parts);
                foreach ($parts[0] as $part) {
                    $out .= self::unescape_pdf_string(substr($part, 1, -1));
                }
            } elseif (!empty($m[0])) {
                // Line/block movement -> visual line break
                if ($m[0] !== 'Td' || true) {
                    $out .= "\n";
                }
            }
        }

        return $out;
    }

    /**
     * Decode PDF literal-string escape sequences
     * @param string $s Raw bytes between the parentheses
     * @return string Decoded string
     */
    private static function unescape_pdf_string($s) {
        return preg_replace_callback(
            '/\\\\([nrtbf()\\\\]|[0-7]{1,3})/',
            function ($m) {
                switch ($m[1]) {
                    case 'n': return "\n";
                    case 'r': return "\r";
                    case 't': return "\t";
                    case 'b': return chr(8);
                    case 'f': return "\f";
                    case '(':
                    case ')':
                    case '\\':
                        return $m[1];
                }
                return chr(octdec($m[1]));
            },
            $s
        );
    }
}
