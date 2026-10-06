# GitHub User Activity

A small command line tool that shows a GitHub user's recent activity in the terminal. Written in PHP with no libraries, just the standard library.

Project page: https://roadmap.sh/projects/github-user-activity

## Requirements

PHP 8.0 or newer.

## Usage

```bash
php github-activity.php <username>
```

Example:

```bash
php github-activity.php kamranahmedse
```

```
- Pushed to master in kamranahmedse/developer-roadmap
- Opened a new issue (#7) in kamranahmedse/developer-roadmap
- Starred kamranahmedse/developer-roadmap
```

If you want to type `github-activity` instead:

```bash
chmod +x github-activity.php
ln -s "$(pwd)/github-activity.php" /usr/local/bin/github-activity
```

## How it works

It calls `https://api.github.com/users/<username>/events` and turns each event into a line of text. Pushes, issues, pull requests, comments, stars, forks, branches and releases get their own wording, and anything else falls back to the event name and the repo.

Push events don't include a commit count any more (GitHub removed it from the API), so pushes show the branch instead. If an older payload does have a count, it's shown.

GitHub only returns recent events (the last 30 or so), and unauthenticated requests are limited to 60 an hour.

## Errors

- Unknown username: `User "x" not found.`
- Rate limit hit: tells you to wait and try again
- No network or a bad response: a short message, exit code 1
- No username given: prints the usage line
