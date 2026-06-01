<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

final class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toMobileArray(DatabaseNotification $n): array
    {
        $data = self::dataArray($n);

        $title = isset($data['title']) && is_string($data['title'])
            ? $data['title']
            : class_basename($n->type);
        $body = $data['body'] ?? $data['message'] ?? '';

        return [
            'id' => $n->id,
            'title' => $title,
            'body' => is_string($body) ? $body : (string) $body,
            'message' => is_string($body) ? $body : (string) $body,
            'created_at' => $n->created_at?->toIso8601String(),
            'read' => $n->read_at !== null,
            'read_at' => $n->read_at?->toIso8601String(),
            'module' => $data['module'] ?? null,
            'kind' => $data['kind'] ?? null,
            'url' => $data['url'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toWebArray(DatabaseNotification $n): array
    {
        return self::toMobileArray($n);
    }

    /**
     * @return array<string, mixed>
     */
    private static function dataArray(DatabaseNotification $n): array
    {
        $data = $n->data;
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }
}
