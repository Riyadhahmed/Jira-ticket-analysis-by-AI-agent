<?php
 
// Paste any Jira ticket URL here
$ticketUrl = "https://sipay.atlassian.net/browse/PS-2378";

include_once('JiraService.php');
include_once('AiAgentAnalysis.php');
include_once('view_ticket_details.php');

try {
    $issueKey = extractIssueKey($ticketUrl);

    // 1. Fetch issue + comments from Jira in parallel concept (sequential here)
    $issue    = fetchJiraIssue($issueKey);
    $comments = fetchIssueComments($issueKey);

    // 2. Prepare plain-text inputs for Gemini
    $plainTitle       = $issue['fields']['summary'] ?? '(No title)';
    $plainDescription = extractAdfText($issue['fields']['description'] ?? null);
    $plainComments    = array_map(fn($c) => [
        'author' => $c['author']['displayName'] ?? 'Unknown',
        'body'   => extractAdfText($c['body'] ?? null),
    ], $comments);

    // 3. Run FREE Gemini analysis
    $analysis = analyzeWithAgent($plainTitle, $plainDescription, $plainComments);

    // 4. Render everything as one HTML page
    printIssue($issue, $comments, $analysis);

} catch (InvalidArgumentException $e) {
    echo "Invalid URL: " . $e->getMessage() . "\n"; exit(1);
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n"; exit(1);
}