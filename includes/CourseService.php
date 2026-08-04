<?php
declare(strict_types=1);

/**
 * Shared domain rules for the courses module.
 *
 * Course lessons are persisted as a small, versioned JSON document.  Rendering
 * is deliberately done server-side from an allow-listed data structure; course
 * authors never get to execute markup, scripts, or arbitrary embeds.
 */

const COURSE_ITEM_TYPES = ['text', 'video', 'quiz', 'lab', 'exam'];
const COURSE_LAB_TOOLS = ['logic', 'psu', 'subnet', 'router', 'numbers', 'ohm', 'live', 'crypto'];
const COURSE_BLOCK_TYPES = ['text', 'callout', 'code', 'checklist', 'image', 'divider'];

function courseText(string $value, int $maxLength, bool $multiline = true): string {
    $value = trim(str_replace("\0", '', $value));
    if (!$multiline) {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function courseValidDate(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

function courseDateRangeIsValid(?string $startDate, ?string $endDate): bool {
    return $startDate === null || $endDate === null || $startDate <= $endDate;
}

function courseNormalizeImageUrl(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (mb_strlen($value, '8bit') > 255 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return null;
    }

    $localPath = ltrim($value, '/');
    if (preg_match('#^assets/images/[A-Za-z0-9._/-]+$#', $localPath) === 1 && !str_contains($localPath, '..')) {
        return $localPath;
    }

    $parts = parse_url($value);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        return null;
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($host, ['praktycznyegzamin.pl', 'www.praktycznyegzamin.pl'], true)) {
        return null;
    }
    return $value;
}

function courseDisplayImageUrl(?string $value, string $basePrefix = ''): ?string {
    $safe = courseNormalizeImageUrl((string)$value);
    if ($safe === null) {
        return null;
    }
    return str_starts_with($safe, 'assets/') ? assetUrl($safe, $basePrefix) : $safe;
}

function courseYoutubeEmbedUrl(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '' || mb_strlen($value, '8bit') > 255) {
        return null;
    }
    $parts = parse_url($value);
    if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return null;
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $videoId = '';
    if ($host === 'youtu.be') {
        $videoId = trim((string)explode('/', trim((string)($parts['path'] ?? ''), '/'))[0]);
    } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        parse_str((string)($parts['query'] ?? ''), $query);
        $path = trim((string)($parts['path'] ?? ''), '/');
        if (isset($query['v'])) {
            $videoId = (string)$query['v'];
        } elseif (preg_match('#^(?:embed|shorts|live)/([^/]+)#', $path, $matches)) {
            $videoId = $matches[1];
        }
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
        return null;
    }
    return 'https://www.youtube-nocookie.com/embed/' . $videoId . '?rel=0&modestbranding=1';
}

function courseDefaultLessonDocument(): array {
    return ['version' => 2, 'blocks' => [['type' => 'text', 'heading' => '', 'body' => '']]];
}

function courseNormalizeLessonDocument(array $document): array {
    $rawBlocks = $document['blocks'] ?? [];
    if (!is_array($rawBlocks)) {
        $rawBlocks = [];
    }

    $blocks = [];
    foreach (array_slice($rawBlocks, 0, 30) as $rawBlock) {
        if (!is_array($rawBlock)) {
            continue;
        }
        $type = (string)($rawBlock['type'] ?? 'text');
        if (!in_array($type, COURSE_BLOCK_TYPES, true)) {
            continue;
        }
        if ($type === 'text') {
            $blocks[] = [
                'type' => 'text',
                'heading' => courseText((string)($rawBlock['heading'] ?? ''), 160, false),
                'body' => courseText((string)($rawBlock['body'] ?? ''), 10000),
            ];
        } elseif ($type === 'callout') {
            $tone = (string)($rawBlock['tone'] ?? 'info');
            $blocks[] = [
                'type' => 'callout',
                'tone' => in_array($tone, ['info', 'success', 'warning'], true) ? $tone : 'info',
                'title' => courseText((string)($rawBlock['title'] ?? ''), 160, false),
                'body' => courseText((string)($rawBlock['body'] ?? ''), 6000),
            ];
        } elseif ($type === 'code') {
            $language = courseText((string)($rawBlock['language'] ?? ''), 32, false);
            $blocks[] = [
                'type' => 'code',
                'language' => preg_match('/^[A-Za-z0-9+#._ -]*$/', $language) === 1 ? $language : '',
                'code' => courseText((string)($rawBlock['code'] ?? ''), 10000),
            ];
        } elseif ($type === 'checklist') {
            $items = is_array($rawBlock['items'] ?? null) ? $rawBlock['items'] : [];
            $cleanItems = [];
            foreach (array_slice($items, 0, 25) as $item) {
                $item = courseText((string)$item, 300);
                if ($item !== '') {
                    $cleanItems[] = $item;
                }
            }
            $blocks[] = [
                'type' => 'checklist',
                'title' => courseText((string)($rawBlock['title'] ?? ''), 160, false),
                'items' => $cleanItems,
            ];
        } elseif ($type === 'image') {
            $source = courseNormalizeImageUrl((string)($rawBlock['src'] ?? ''));
            if ($source !== null) {
                $blocks[] = [
                    'type' => 'image',
                    'src' => $source,
                    'alt' => courseText((string)($rawBlock['alt'] ?? ''), 160, false),
                    'caption' => courseText((string)($rawBlock['caption'] ?? ''), 300),
                ];
            }
        } else {
            $blocks[] = ['type' => 'divider'];
        }
    }

    return ['version' => 2, 'blocks' => $blocks ?: courseDefaultLessonDocument()['blocks']];
}

function courseDecodeLessonDocument(?string $raw): ?array {
    $raw = trim((string)$raw);
    if ($raw === '' || mb_strlen($raw, '8bit') > 300000) {
        return $raw === '' ? courseDefaultLessonDocument() : null;
    }
    try {
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        return null;
    }
    if (!is_array($decoded) || (int)($decoded['version'] ?? 0) !== 2 || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
        return null;
    }
    return courseNormalizeLessonDocument($decoded);
}

function courseEncodeLessonDocument(array $document): string {
    return json_encode(courseNormalizeLessonDocument($document), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function courseLegacyContentHtml(string $raw): string {
    $candidate = trim($raw);
    if ($candidate === '') {
        return '';
    }
    try {
        $json = json_decode($candidate, true, 16, JSON_THROW_ON_ERROR);
        if (is_array($json) && isset($json['html']) && is_string($json['html'])) {
            $candidate = $json['html'];
        }
    } catch (JsonException $error) {
        // Plain historical HTML is handled below.
    }
    if (!class_exists('DOMDocument')) {
        return nl2br(htmlspecialchars(strip_tags($candidate), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<div id="course-legacy-root">' . $candidate . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        $root = $document->getElementById('course-legacy-root');
        if (!$root) {
            return nl2br(htmlspecialchars(strip_tags($candidate), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a', 'img', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];
        $dangerous = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'link', 'meta'];
        $nodes = [];
        foreach ($root->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }
        foreach (array_reverse($nodes) as $node) {
            $tag = strtolower($node->nodeName);
            if (in_array($tag, $dangerous, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }
            if (!in_array($tag, $allowed, true)) {
                while ($node->firstChild) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);
                continue;
            }
            $attributes = [];
            foreach ($node->attributes ?? [] as $attribute) {
                $attributes[] = $attribute->nodeName;
            }
            foreach ($attributes as $name) {
                $lowerName = strtolower($name);
                $value = (string)$node->getAttribute($name);
                $keep = false;
                if ($tag === 'a' && $lowerName === 'href') {
                    $parts = parse_url($value);
                    $scheme = strtolower((string)($parts['scheme'] ?? ''));
                    $keep = $value !== '' && ($scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true));
                } elseif ($tag === 'img' && $lowerName === 'src') {
                    $safeImage = courseNormalizeImageUrl($value);
                    if ($safeImage !== null) {
                        $node->setAttribute('src', courseDisplayImageUrl($safeImage) ?? '');
                        $keep = true;
                    }
                } elseif ($lowerName === 'alt') {
                    $keep = $tag === 'img';
                }
                if (!$keep) {
                    $node->removeAttribute($name);
                }
            }
            if ($tag === 'a') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }
        return $html;
    } catch (Throwable $error) {
        return nl2br(htmlspecialchars(strip_tags($candidate), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function courseRenderLessonContent(?string $raw, string $basePrefix = ''): string {
    $document = courseDecodeLessonDocument($raw);
    if ($document === null) {
        $legacy = courseLegacyContentHtml((string)$raw);
        return $legacy === '' ? '<p class="text-muted mb-0">Ta lekcja nie ma jeszcze treści.</p>' : '<div class="course-legacy-content">' . $legacy . '</div>';
    }

    $html = '<div class="course-document">';
    foreach ($document['blocks'] as $block) {
        $type = $block['type'];
        if ($type === 'text') {
            $heading = (string)$block['heading'];
            $body = (string)$block['body'];
            $html .= '<section class="course-block course-block-text">';
            if ($heading !== '') {
                $html .= '<h2>' . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>';
            }
            if ($body !== '') {
                $html .= '<div class="course-prose">' . nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>';
            }
            $html .= '</section>';
        } elseif ($type === 'callout') {
            $tone = (string)$block['tone'];
            $icon = $tone === 'success' ? 'bi-check-circle-fill' : ($tone === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
            $html .= '<aside class="course-callout course-callout-' . $tone . '"><div class="course-callout-icon-wrap"><i class="bi ' . $icon . '" aria-hidden="true"></i></div><div>';
            if ($block['title'] !== '') {
                $html .= '<h3>' . htmlspecialchars((string)$block['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>';
            }
            $html .= '<div>' . nl2br(htmlspecialchars((string)$block['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div></div></aside>';
        } elseif ($type === 'code') {
            $lang = $block['language'] !== '' ? '<span class="course-code-lang">' . htmlspecialchars((string)$block['language'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>' : '<span></span>';
            $header = '<div class="course-code-header"><div class="course-code-dots"><span class="course-code-dot course-code-dot-red"></span><span class="course-code-dot course-code-dot-yellow"></span><span class="course-code-dot course-code-dot-green"></span></div>' . $lang . '</div>';
            $html .= '<section class="course-code-block">' . $header . '<pre><code>' . htmlspecialchars((string)$block['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre></section>';
        } elseif ($type === 'checklist') {
            $html .= '<section class="course-checklist">';
            if ($block['title'] !== '') {
                $html .= '<h3>' . htmlspecialchars((string)$block['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>';
            }
            $html .= '<ul>';
            foreach ($block['items'] as $item) {
                $html .= '<li><i class="bi bi-check2" aria-hidden="true"></i><span>' . htmlspecialchars((string)$item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></li>';
            }
            $html .= '</ul></section>';
        } elseif ($type === 'image') {
            $source = courseDisplayImageUrl((string)$block['src'], $basePrefix);
            if ($source !== null) {
                $html .= '<figure class="course-image-block"><img src="' . htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . htmlspecialchars((string)$block['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy" decoding="async">';
                if ($block['caption'] !== '') {
                    $html .= '<figcaption>' . htmlspecialchars((string)$block['caption'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</figcaption>';
                }
                $html .= '</figure>';
            }
        } elseif ($type === 'divider') {
            $html .= '<hr class="course-divider">';
        }
    }
    return $html . '</div>';
}

function courseFetchById(PDO $pdo, int $courseId): ?array {
    if ($courseId <= 0) {
        return null;
    }
    $statement = $pdo->prepare('SELECT id, title, description, content, created_by, image_url, category, difficulty, estimated_hours, status, sequential_learning, has_certificate, start_date, end_date, created_at, updated_at FROM courses WHERE id = ? LIMIT 1');
    $statement->execute([$courseId]);
    $course = $statement->fetch(PDO::FETCH_ASSOC);
    return $course ?: null;
}

function courseFetchModule(PDO $pdo, int $moduleId): ?array {
    if ($moduleId <= 0) {
        return null;
    }
    $statement = $pdo->prepare('SELECT cm.id, cm.course_id, cm.title, cm.description, cm.sort_order, cm.created_at, c.status AS course_status FROM course_modules cm JOIN courses c ON c.id = cm.course_id WHERE cm.id = ? LIMIT 1');
    $statement->execute([$moduleId]);
    $module = $statement->fetch(PDO::FETCH_ASSOC);
    return $module ?: null;
}

function courseFetchItem(PDO $pdo, int $itemId): ?array {
    if ($itemId <= 0) {
        return null;
    }
    $statement = $pdo->prepare('SELECT ci.id, ci.module_id, ci.title, ci.type, ci.content, ci.video_url, ci.quiz_passing_score, ci.lab_source, ci.lab_tool_key, ci.lab_custom_id, ci.lab_instructions, ci.sort_order, ci.created_at, cm.course_id, cm.sort_order AS module_sort_order, c.status AS course_status, c.created_by, c.start_date, c.end_date, c.sequential_learning FROM course_items ci JOIN course_modules cm ON cm.id = ci.module_id JOIN courses c ON c.id = cm.course_id WHERE ci.id = ? LIMIT 1');
    $statement->execute([$itemId]);
    $item = $statement->fetch(PDO::FETCH_ASSOC);
    return $item ?: null;
}

function courseFetchStructure(PDO $pdo, int $courseId, bool $includeContent = false): array {
    $columns = 'ci.id, ci.module_id, ci.title, ci.type, ci.video_url, ci.quiz_passing_score, ci.lab_source, ci.lab_tool_key, ci.lab_custom_id, ci.lab_instructions, ci.sort_order';
    if ($includeContent) {
        $columns .= ', ci.content';
    }
    $statement = $pdo->prepare("SELECT cm.id AS module_id, cm.title AS module_title, cm.description AS module_description, cm.sort_order AS module_sort_order, $columns FROM course_modules cm LEFT JOIN course_items ci ON ci.module_id = cm.id WHERE cm.course_id = ? ORDER BY cm.sort_order ASC, cm.id ASC, ci.sort_order ASC, ci.id ASC");
    $statement->execute([$courseId]);
    $structure = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $moduleId = (int)$row['module_id'];
        if (!isset($structure[$moduleId])) {
            $structure[$moduleId] = [
                'id' => $moduleId,
                'title' => (string)$row['module_title'],
                'description' => (string)($row['module_description'] ?? ''),
                'sort_order' => (int)$row['module_sort_order'],
                'items' => [],
            ];
        }
        if ($row['id'] !== null) {
            $item = $row;
            unset($item['module_title'], $item['module_description'], $item['module_sort_order']);
            $structure[$moduleId]['items'][] = $item;
        }
    }
    return array_values($structure);
}

function courseItemsInOrder(array $structure): array {
    $items = [];
    foreach ($structure as $module) {
        foreach ($module['items'] as $item) {
            $items[] = $item;
        }
    }
    return $items;
}

function courseIsPubliclyAvailable(array $course): bool {
    if (($course['status'] ?? $course['course_status'] ?? '') !== 'active') {
        return false;
    }
    $today = date('Y-m-d');
    return (empty($course['start_date']) || (string)$course['start_date'] <= $today)
        && (empty($course['end_date']) || (string)$course['end_date'] >= $today);
}

function courseCanUserAccess(PDO $pdo, array $course, int $userId, bool $isAdmin = false): bool {
    if ($isAdmin) {
        return true;
    }
    $status = (string)($course['status'] ?? $course['course_status'] ?? '');
    if ($status === 'active') {
        $today = date('Y-m-d');
        return (empty($course['start_date']) || (string)$course['start_date'] <= $today)
            && (empty($course['end_date']) || (string)$course['end_date'] >= $today);
    }
    if ($status === 'private' && $userId > 0) {
        $createdBy = isset($course['created_by']) ? (int)$course['created_by'] : 0;
        if ($createdBy > 0 && $createdBy === $userId) {
            return true;
        }
        $courseId = (int)($course['id'] ?? $course['course_id'] ?? 0);
        if ($courseId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT 1 FROM course_shares WHERE course_id = ? AND shared_with_user_id = ? LIMIT 1');
                $stmt->execute([$courseId, $userId]);
                if ((bool)$stmt->fetchColumn()) {
                    return true;
                }
            } catch (Throwable $e) {
                if (function_exists('_ensurePlatformCourses')) {
                    _ensurePlatformCourses($pdo);
                    try {
                        $stmt = $pdo->prepare('SELECT 1 FROM course_shares WHERE course_id = ? AND shared_with_user_id = ? LIMIT 1');
                        $stmt->execute([$courseId, $userId]);
                        if ((bool)$stmt->fetchColumn()) {
                            return true;
                        }
                    } catch (Throwable $e2) {
                        error_log('courseCanUserAccess error: ' . $e2->getMessage());
                    }
                }
            }
        }
    }
    return false;
}

function courseGetSharedUserIds(PDO $pdo, int $courseId): array {
    if ($courseId <= 0) {
        return [];
    }
    try {
        $stmt = $pdo->prepare('SELECT shared_with_user_id FROM course_shares WHERE course_id = ?');
        $stmt->execute([$courseId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        if (function_exists('_ensurePlatformCourses')) {
            _ensurePlatformCourses($pdo);
            try {
                $stmt = $pdo->prepare('SELECT shared_with_user_id FROM course_shares WHERE course_id = ?');
                $stmt->execute([$courseId]);
                return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable $e2) {
                error_log('courseGetSharedUserIds error: ' . $e2->getMessage());
            }
        }
        return [];
    }
}

function courseSetSharedUserIds(PDO $pdo, int $courseId, array $sharedWithUserIds): void {
    if ($courseId <= 0) {
        return;
    }
    $doSet = function() use ($pdo, $courseId, $sharedWithUserIds) {
        $stmt = $pdo->prepare('DELETE FROM course_shares WHERE course_id = ?');
        $stmt->execute([$courseId]);

        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $sharedWithUserIds), fn($id) => $id > 0)));
        if (!empty($cleanIds)) {
            $insertStmt = $pdo->prepare('INSERT INTO course_shares (course_id, shared_with_user_id) VALUES (?, ?)');
            foreach ($cleanIds as $friendId) {
                $insertStmt->execute([$courseId, $friendId]);
            }
        }
    };

    try {
        $doSet();
    } catch (Throwable $e) {
        if (function_exists('_ensurePlatformCourses')) {
            _ensurePlatformCourses($pdo);
            try {
                $doSet();
            } catch (Throwable $e2) {
                error_log('courseSetSharedUserIds error: ' . $e2->getMessage());
            }
        }
    }
}


function courseRecalculateEnrollmentProgress(PDO $pdo, int $userId, int $courseId): int {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM course_items ci JOIN course_modules cm ON cm.id = ci.module_id WHERE cm.course_id = ?');
    $statement->execute([$courseId]);
    $total = (int)$statement->fetchColumn();
    $completed = 0;
    if ($total > 0) {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM user_course_progress ucp JOIN course_items ci ON ci.id = ucp.item_id JOIN course_modules cm ON cm.id = ci.module_id WHERE ucp.user_id = ? AND ucp.course_id = ? AND ucp.status = 'completed' AND cm.course_id = ?");
        $statement->execute([$userId, $courseId, $courseId]);
        $completed = (int)$statement->fetchColumn();
    }
    $percent = $total > 0 ? max(0, min(100, (int)round(($completed / $total) * 100))) : 0;
    $status = $total > 0 && $percent === 100 ? 'completed' : 'active';
    $statement = $pdo->prepare("UPDATE user_course_enrollments SET progress_percent = ?, status = ?, completed_at = CASE WHEN ? = 'completed' THEN COALESCE(completed_at, NOW()) ELSE NULL END WHERE user_id = ? AND course_id = ?");
    $statement->execute([$percent, $status, $status, $userId, $courseId]);
    return $percent;
}

function courseIssueCertificate(PDO $pdo, int $userId, int $courseId): ?string {
    if ($userId <= 0 || $courseId <= 0) {
        return null;
    }
    $course = courseFetchById($pdo, $courseId);
    if (!$course || (int)($course['has_certificate'] ?? 1) !== 1) {
        return null;
    }

    $structure = courseFetchStructure($pdo, $courseId, false);
    $items = courseItemsInOrder($structure);
    $totalItems = count($items);
    if ($totalItems === 0) {
        return null;
    }

    $pStmt = $pdo->prepare("SELECT COUNT(*) FROM user_course_progress WHERE user_id = ? AND course_id = ? AND status = 'completed'");
    $pStmt->execute([$userId, $courseId]);
    $completed = (int)$pStmt->fetchColumn();

    foreach ($items as $it) {
        if (in_array($it['type'], ['quiz', 'exam'], true)) {
            $eStmt = $pdo->prepare("SELECT status FROM user_course_progress WHERE user_id = ? AND item_id = ? LIMIT 1");
            $eStmt->execute([$userId, (int)$it['id']]);
            if ($eStmt->fetchColumn() !== 'completed') {
                return null;
            }
        }
    }

    $percent = (int)round(($completed / $totalItems) * 100);
    if ($percent < 100) {
        return null;
    }

    $certHash = strtoupper(substr(hash('sha256', 'ZSEM_CERT_' . $userId . '_' . $courseId), 0, 10));
    $certCode = 'ZSEM-CERT-' . $certHash;

    try {
        $stmt = $pdo->prepare("INSERT INTO user_certificates (user_id, course_id, name, organization, certificate_code, obtained_date, description)
            VALUES (?, ?, ?, 'ZSEM Tech Platforma Edukacyjna', ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), certificate_code = VALUES(certificate_code)");
        $stmt->execute([$userId, $courseId, (string)$course['title'], $certCode, 'Certyfikat ukończenia kursu nr ' . $certCode]);
        return $certCode;
    } catch (Throwable $e) {
        error_log('Certificate issue error: ' . $e->getMessage());
        return $certCode;
    }
}
