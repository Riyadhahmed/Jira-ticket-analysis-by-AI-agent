<?php

declare(strict_types=1);

/**
 * Render the full page: ticket + comments + Gemini analysis.
 */
function printIssue(array $issue, array $comments, array $analysis): void
{
    $key         = htmlspecialchars($issue['key']                               ?? 'N/A',           ENT_QUOTES);
    $summary     = htmlspecialchars($issue['fields']['summary']                 ?? '(No summary)',  ENT_QUOTES);
    $description = htmlspecialchars(extractAdfText($issue['fields']['description'] ?? null),        ENT_QUOTES);
    $statusRaw   = $issue['fields']['status']['name']                           ?? 'To Do';
    $assignee    = htmlspecialchars($issue['fields']['assignee']['displayName'] ?? 'Unassigned',    ENT_QUOTES);
    $project     = strtok($key, '-');

    $statusClass = match (true) {
        in_array(strtolower($statusRaw), ['done', 'closed', 'resolved'])       => 'done',
        in_array(strtolower($statusRaw), ['in progress', 'in review', 'open']) => 'in-progress',
        default => '',
    };

    $status       = htmlspecialchars($statusRaw, ENT_QUOTES);
    $commentCount = count($comments);

    // ── Comments HTML ─────────────────────────────────────────────────────────
    $commentsHtml = '';
    if ($commentCount === 0) {
        $commentsHtml = '<p class="no-comments">No comments yet.</p>';
    } else {
        foreach ($comments as $index => $comment) {
            $author   = htmlspecialchars($comment['author']['displayName'] ?? 'Unknown', ENT_QUOTES);
            $body     = htmlspecialchars(extractAdfText($comment['body']   ?? null),     ENT_QUOTES);
            $date     = formatCommentDate($comment['created'] ?? '');
            $initials = htmlspecialchars(getInitials($author),                           ENT_QUOTES);
            $n        = $index + 1;

            $commentsHtml .= <<<COMMENT
            <div class="comment-item" role="article" aria-label="Comment $n by $author">
                <div class="comment-avatar" aria-hidden="true">$initials</div>
                <div class="comment-content">
                    <div class="comment-meta">
                        <span class="comment-author">$author</span>
                        <span class="comment-dot" aria-hidden="true">·</span>
                        <time class="comment-date">$date</time>
                    </div>
                    <div class="comment-body">$body</div>
                </div>
            </div>
            COMMENT;
        }
    }

    // ── Analysis HTML ─────────────────────────────────────────────────────────
    $execSummary   = htmlspecialchars($analysis['summary'] ?? '', ENT_QUOTES);
    $analysisHtml  = renderAnalysisSection('🎯', 'Business Objectives',        $analysis['objectives']      ?? []);
    $analysisHtml .= renderAnalysisSection('👥', 'Stakeholders',               $analysis['stakeholders']    ?? []);
    $analysisHtml .= renderAnalysisSection('⚙️', 'Functional Requirements',    $analysis['functional']      ?? []);
    $analysisHtml .= renderAnalysisSection('🔒', 'Non-Functional Requirements', $analysis['non_functional'] ?? []);
    $analysisHtml .= renderAnalysisSection('⚠️', 'Risks & Dependencies',       $analysis['risks']           ?? []);
    $analysisHtml .= renderAnalysisSection('❓', 'Open Questions',              $analysis['open_questions']  ?? []);

    $geminiModel = GROQ_MODEL;

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>$key · $summary</title>
        <link rel="stylesheet" href="ticket.css" />
    </head>
    <body>
    <div class="ticket-wrapper">

        <!-- Breadcrumb -->
        <nav class="ticket-breadcrumb" aria-label="Breadcrumb">
            <span>Projects</span><span>›</span>
            <span>$project</span><span>›</span>
            <strong>$key</strong>
        </nav>

        <!-- ── Main ticket card ─────────────────────────────────────────── -->
        <article class="ticket-card" role="region" aria-label="Jira ticket $key">

            <header class="ticket-topbar">
                <span class="ticket-key">$key</span>
                <span class="ticket-status-badge $statusClass" role="status">
                    <span class="status-pip" aria-hidden="true"></span>$status
                </span>
            </header>

            <div class="ticket-body">

                <h1 class="ticket-title">$summary</h1>

                <div class="ticket-meta" aria-label="Issue details">
                    <div class="meta-pill">
                        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <circle cx="7" cy="4.5" r="2.5"/><path d="M1.5 13c0-3 2.5-4.5 5.5-4.5s5.5 1.5 5.5 4.5"/>
                        </svg>$assignee
                    </div>
                    <div class="meta-pill">
                        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M1.5 1.5h5l6 6-5 5-6-6v-5z"/>
                            <circle cx="4.5" cy="4.5" r="1" fill="currentColor" stroke="none"/>
                        </svg>$key
                    </div>
                    <div class="meta-pill">
                        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M7 1.5L12.5 4.5 7 7.5 1.5 4.5z"/>
                            <path d="M1.5 7L7 10l5.5-3"/><path d="M1.5 10L7 13l5.5-3"/>
                        </svg>$project
                    </div>
                </div>

                <!-- Description -->
                <div class="ticket-section">
                    <p class="section-label">Description</p>
                    <div class="desc-content" role="document">$description</div>
                </div>

                <!-- Comments -->
                <div class="ticket-section comments-section">
                    <p class="section-label">
                        Comments
                        <span class="comment-count">$commentCount</span>
                    </p>
                    <div class="comments-list" aria-label="Comments">$commentsHtml</div>
                </div>

            </div>

            <footer class="ticket-footer">
                <div class="footer-source">
                    <svg class="claude-mark" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                        <circle cx="50" cy="50" r="45" stroke="#c96442" stroke-width="6" opacity="0.7"/>
                        <circle cx="50" cy="50" r="30" stroke="#c96442" stroke-width="5" opacity="0.5"/>
                        <circle cx="50" cy="50" r="15" stroke="#c96442" stroke-width="4" opacity="0.35"/>
                    </svg>
                    Jira Issue Viewer
                </div>
                <span class="footer-url">sipay.atlassian.net</span>
            </footer>
        </article>

        <!-- ── AI Analysis card (powered by FREE Gemini) ─────────────── -->
        <section class="analysis-card" aria-label="AI Business Requirements Analysis">

            <div class="analysis-header">
                <div class="analysis-header-left">
                    <div class="analysis-ai-badge" aria-label="Powered by Google Gemini (Free)">
                        <!-- Google Gemini colour-dot mark -->
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="5"  r="3" fill="#4285F4"/>
                            <circle cx="19" cy="12" r="3" fill="#EA4335"/>
                            <circle cx="12" cy="19" r="3" fill="#34A853"/>
                            <circle cx="5"  cy="12" r="3" fill="#FBBC05"/>
                        </svg>
<<<<<<< HEAD
                        <span>Groq</span>
=======
                        <span>Gemini AI · Free</span>
>>>>>>> d0f1d11 (Initial commit)
                    </div>
                    <h2 class="analysis-title">Business Requirements Analysis</h2>
                </div>
                <span class="analysis-model-badge">$geminiModel</span>
            </div>

            <!-- Executive summary -->
            <div class="analysis-summary-box">
                <p class="analysis-summary-text">$execSummary</p>
            </div>

            <!-- Sectioned grid -->
            <div class="analysis-grid">$analysisHtml</div>

        </section>

    </div><!-- /.ticket-wrapper -->
    </body>
    </html>
    HTML;
}