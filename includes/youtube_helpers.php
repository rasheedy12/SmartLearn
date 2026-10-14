<?php

function youtube_video_id($url)
{
    $parts = parse_url(trim((string) $url));
    if (!is_array($parts) || !isset($parts['host'])
        || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
        return null;
    }

    $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
    $video_id = null;
    if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
        $path = trim($parts['path'] ?? '', '/');
        if ($path === 'watch') {
            parse_str($parts['query'] ?? '', $query);
            $video_id = $query['v'] ?? null;
        } elseif (preg_match('~^(?:embed|shorts)/([^/]+)$~', $path, $matches)) {
            $video_id = $matches[1];
        }
    } elseif ($host === 'youtu.be') {
        $video_id = trim($parts['path'] ?? '', '/');
    }

    return is_string($video_id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)
        ? $video_id
        : null;
}
