<?php

namespace App\Domain\Connect;

class Providers
{
    public static function get(string $id): Provider
    {
        return match ($id) {
            'github' => new GitHubProvider, 'slack' => new SlackProvider, 'demo-github','demo-slack' => new DemoProvider($id), 'generic' => new GenericProvider, default => throw new RequestRejected('Unknown provider.')
        };
    }

    public static function catalog(): array
    {
        $ids = ['generic', 'github', 'slack'];
        if (config('apiqendra.demo')) {
            $ids = [...$ids, 'demo-github', 'demo-slack'];
        }

        return array_map(fn ($id) => self::get($id)->definition(), $ids);
    }
}
