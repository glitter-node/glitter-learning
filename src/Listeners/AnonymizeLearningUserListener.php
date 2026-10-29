<?php

namespace Modules\Glitter\Learning\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnonymizeLearningUserListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.user.before_delete' => ['method' => 'handle', 'priority' => 10, 'sync' => true],
        ];
    }

    public function handle(...$args): void
    {
        $user = $args[0] ?? null;
        if (! $user instanceof User) {
            return;
        }
        DB::transaction(function () use ($user) {
            DB::table('glitter_learning_courses')->where('author_id', $user->id)->update(['author_id' => null]);
            DB::table('glitter_learning_completions')->where('user_id', $user->id)->update(['user_id' => null]);
            DB::table('glitter_learning_enrollments')->where('user_id', $user->id)->update(['user_id' => null]);
        });
    }
}
