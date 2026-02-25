<?php
 
declare(strict_types=1);

// Configuration
const JIRA_URL   = "https://sipay.atlassian.net";
const JIRA_EMAIL = "monjur.morshed@softrobotics.com.bd";
const JIRA_TOKEN = "ATATT3xFfGF0wvhKW6H6BXS4xYhCVwifLc6x66kF6_FClhJ0YGcyuypn92IUnclUfgF7qrweh3I3jxINeedR1NueLVi1kM7XrOerDvWihE-FEZLReKe-45id7onqnqVmxtTAkO6DJxSksav1BkNBaCSIVxvuU1pfjVFyh6FcB8rmt8NjhrJiKyY=96584D4B";

/**
 * Extract the issue key from a Jira ticket URL.
 * e.g. https://sipay.atlassian.net/browse/PAY-123 → PAY-123
 *
 * @param string $ticketUrl
 * @return string
 * @throws InvalidArgumentException
 */
function extractIssueKey(string $ticketUrl): string
{
    $path     = parse_url($ticketUrl, PHP_URL_PATH);
    $issueKey = basename($path);

    // Validate format: e.g. PAY-123, ABC-1, PROJECT-9999
    if (!preg_match('/^[A-Z]+-\d+$/', $issueKey)) {
        throw new InvalidArgumentException("Invalid Jira ticket URL or issue key: $ticketUrl");
    }

    return $issueKey;
}

/**
 * Shared cURL helper — makes a GET request and returns decoded JSON.
 */
function jiraGet(string $url): array
{
    $headers = [
        "Authorization: Basic " . base64_encode(JIRA_EMAIL . ":" . JIRA_TOKEN),
        "Accept: application/json",
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    unset($ch);

    if ($curlError) {
        throw new RuntimeException("cURL error: $curlError");
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg  = $errorData['errorMessages'][0] ?? $errorData['message'] ?? $response;
        throw new RuntimeException("Jira API HTTP $httpCode: $errorMsg");
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("JSON decode error: " . json_last_error_msg());
    }

    return $data;
}

/**
 * Fetch a single Jira issue by key.
 */
function fetchJiraIssue(string $issueKey): array
{
    $url = JIRA_URL . "/rest/api/3/issue/" . urlencode($issueKey) . "?" . http_build_query([
        "fields" => "summary,description,status,assignee",
    ]);

    return jiraGet($url);
}

/**
 * Fetch all comments for a Jira issue.
 * Returns an array of comment objects.
 */
function fetchIssueComments(string $issueKey): array
{
    $url = JIRA_URL . "/rest/api/3/issue/" . urlencode($issueKey) . "/comment?" . http_build_query([
        "orderBy" => "created",   // oldest first — natural conversation order
        "maxResults" => 100,
    ]);

    $data = jiraGet($url);

    return $data['comments'] ?? [];
}


/**
 * Extract plain text from Jira's Atlassian Document Format (ADF).
 * Works for both descriptions and comment bodies.
 */
function extractAdfText(mixed $adf): string
{
    if (empty($adf) || !is_array($adf)) {
        return "(No content)";
    }

    $text = [];

    $traverse = function (array $node) use (&$traverse, &$text): void {
        if (isset($node['text'])) {
            $text[] = $node['text'];
        }
        // Add line breaks after paragraph/heading nodes
        if (isset($node['type']) && in_array($node['type'], ['paragraph', 'heading', 'bulletList', 'listItem'])) {
            $text[] = "\n";
        }
        foreach ($node['content'] ?? [] as $child) {
            $traverse($child);
        }
    };

    $traverse($adf);

    return trim(implode('', $text)) ?: "(No content)";
}

/**
 * Format a Jira ISO timestamp into a human-readable relative time.
 * e.g. "2024-03-15T10:30:00.000+0000" → "Mar 15, 2024 at 10:30"
 */
function formatCommentDate(string $isoDate): string
{
    try {
        $dt = new DateTimeImmutable($isoDate);
        return $dt->format('M j, Y \a\t H:i');
    } catch (Exception) {
        return $isoDate;
    }
}

/**
 * Generate initials avatar text from a display name.
 * "John Doe" → "JD"
 */
/** Extract 2-letter initials from a display name. */
function getInitials(string $name): string
{
    $words = array_filter(explode(' ', $name));
    return implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(array_values($words), 0, 2)));
}

