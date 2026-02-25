<?php
declare(strict_types=1);
 
const GROQ_KEY   = "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";
const GROQ_MODEL = "openai/gpt-oss-20b";

// ─── GROQ AI Analysis ───────────────────────────────────────────────────────

function analyzeWithAgent(string $title, string $description, array $comments): array
{
    $commentBlock = empty($comments)
        ? "No comments."
        : implode("\n\n---\n\n", array_map(
            fn($c, $i) => "Comment " . ($i + 1) . " by {$c['author']}:\n{$c['body']}",
            $comments,
            array_keys($comments)
        ));

    $prompt = <<<PROMPT
You are a senior business analyst. Analyse the following Jira ticket and produce a concise
business requirements analysis. Respond ONLY with a valid JSON object — no markdown fences,
no preamble, no trailing text whatsoever.

The JSON must have exactly these keys:
{
  "summary":        "2-3 sentence executive summary of the ticket",
  "objectives":     ["business objective 1", "business objective 2"],
  "stakeholders":   ["stakeholder or team 1", "stakeholder or team 2"],
  "functional":     ["functional requirement 1", "functional requirement 2"],
  "non_functional": ["non-functional requirement 1"],
  "risks":          ["risk or dependency 1"],
  "open_questions": ["unanswered question 1"]
}

── Ticket ──────────────────────────────────────────
Title: $title

Description:
$description

── Comments ────────────────────────────────────────
$commentBlock
PROMPT;

    $apiUrl = "https://api.groq.com/openai/v1/chat/completions";

    $body = json_encode([
        "model" => GROQ_MODEL,
        "messages" => [
            [
                "role" => "system",
                "content" => "You are a senior business analyst that returns structured JSON only."
            ],
            [
                "role" => "user",
                "content" => $prompt
            ]
        ],
        "temperature" => 0.2,
        "max_tokens" => 1024
    ]);

    $ch = curl_init($apiUrl);

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . GROQ_KEY,
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    unset($ch);

    if ($curlError) {
        throw new RuntimeException("Groq cURL error: $curlError");
    }

    if ($httpCode !== 200) {
        throw new RuntimeException("Groq API HTTP $httpCode: $response");
    }

    $data = json_decode($response, true);

    $rawJson = $data['choices'][0]['message']['content'] ?? '';

    // Remove possible markdown fences
    $rawJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawJson));
    $rawJson = preg_replace('/\s*```$/', '', $rawJson);

    $analysis = json_decode(trim($rawJson), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException(
            "Failed to parse AI JSON: " . json_last_error_msg() . "\nRaw: $rawJson"
        );
    }

    return $analysis;
}

// ─── HTML Rendering ───────────────────────────────────────────────────────────

/** Render one labelled list block inside the analysis card. */
function renderAnalysisSection(string $icon, string $label, array $items): string
{
    if (empty($items)) return '';

    $listItems = implode('', array_map(
        fn($item) => '<li>' . htmlspecialchars($item, ENT_QUOTES) . '</li>',
        $items
    ));

    return <<<HTML
    <div class="analysis-section">
        <h3 class="analysis-section-title">
            <span class="analysis-icon" aria-hidden="true">$icon</span>
            $label
        </h3>
        <ul class="analysis-list">$listItems</ul>
    </div>
    HTML;
}