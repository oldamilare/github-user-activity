#!/usr/bin/env php
<?php

if ($argc !== 2) {
    fwrite(STDERR, "Usage: github-activity <username>\n");
    exit(1);
}

$username = $argv[1];
$api = getenv('GITHUB_API') ?: 'https://api.github.com';
$url = $api . '/users/' . rawurlencode($username) . '/events';

$context = stream_context_create(['http' => [
    // GitHub rejects requests without a User-Agent
    'header' => "User-Agent: github-activity-cli\r\nAccept: application/vnd.github+json\r\n",
    'ignore_errors' => true,
    'timeout' => 10,
]]);

$body = @file_get_contents($url, false, $context);
if ($body === false) {
    fwrite(STDERR, "Couldn't reach GitHub. Check your connection and try again.\n");
    exit(1);
}

$status = 0;
foreach ($http_response_header as $line) {
    if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
        $status = (int) $m[1];
    }
}

if ($status === 404) {
    fwrite(STDERR, "User \"$username\" not found.\n");
    exit(1);
}
if ($status === 403 || $status === 429) {
    fwrite(STDERR, "GitHub rate limit reached (60 requests an hour without a token). Try again later.\n");
    exit(1);
}
if ($status !== 200) {
    fwrite(STDERR, "GitHub returned an unexpected response (HTTP $status).\n");
    exit(1);
}

$events = json_decode($body, true);
if (!is_array($events)) {
    fwrite(STDERR, "Couldn't read the response from GitHub.\n");
    exit(1);
}
if (count($events) === 0) {
    echo "No recent activity for $username.\n";
    exit(0);
}

foreach ($events as $event) {
    echo '- ' . describe($event) . "\n";
}

function plural(int $n, string $word): string
{
    return $n === 1 ? "$n $word" : "$n {$word}s";
}

function describe(array $event): string
{
    $type = $event['type'] ?? '';
    $repo = $event['repo']['name'] ?? 'an unknown repo';
    $p = $event['payload'] ?? [];
    $action = $p['action'] ?? '';

    switch ($type) {
        case 'PushEvent':
            // GitHub dropped the commit count from push events, so only show it if it's there
            $count = $p['size'] ?? (isset($p['commits']) ? count($p['commits']) : null);
            $branch = preg_replace('#^refs/heads/#', '', $p['ref'] ?? '');
            if ($count !== null) {
                return 'Pushed ' . plural($count, 'commit') . " to $repo";
            }
            return $branch !== '' ? "Pushed to $branch in $repo" : "Pushed to $repo";

        case 'IssuesEvent':
            $num = $p['issue']['number'] ?? '?';
            if ($action === 'opened') {
                return "Opened a new issue (#$num) in $repo";
            }
            return ucfirst($action) . " issue #$num in $repo";

        case 'IssueCommentEvent':
            $num = $p['issue']['number'] ?? '?';
            $kind = isset($p['issue']['pull_request']) ? 'pull request' : 'issue';
            return "Commented on $kind #$num in $repo";

        case 'PullRequestEvent':
            $num = $p['number'] ?? '?';
            if ($action === 'closed' && !empty($p['pull_request']['merged'])) {
                return "Merged pull request #$num in $repo";
            }
            return ucfirst($action) . " pull request #$num in $repo";

        case 'PullRequestReviewEvent':
            return 'Reviewed pull request #' . ($p['pull_request']['number'] ?? '?') . " in $repo";

        case 'PullRequestReviewCommentEvent':
            return 'Commented on pull request #' . ($p['pull_request']['number'] ?? '?') . " in $repo";

        case 'WatchEvent':
            return "Starred $repo";

        case 'ForkEvent':
            return "Forked $repo";

        case 'CreateEvent':
            if (($p['ref_type'] ?? '') === 'repository') {
                return "Created repository $repo";
            }
            return 'Created ' . ($p['ref_type'] ?? 'something') . ' ' . ($p['ref'] ?? '') . " in $repo";

        case 'DeleteEvent':
            return 'Deleted ' . ($p['ref_type'] ?? 'something') . ' ' . ($p['ref'] ?? '') . " in $repo";

        case 'ReleaseEvent':
            return 'Published release ' . ($p['release']['tag_name'] ?? '') . " in $repo";

        case 'PublicEvent':
            return "Made $repo public";

        default:
            return preg_replace('/Event$/', '', $type) . " in $repo";
    }
}
