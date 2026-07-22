<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * An event may be edited or deleted only by its creator or an admin.
     */
    public function update(User $user, Event $event): bool
    {
        return $event->created_by === $user->id || $user->isAdmin();
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
