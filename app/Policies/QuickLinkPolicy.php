<?php

namespace App\Policies;

use App\Models\QuickLink;
use App\Models\User;

class QuickLinkPolicy
{
    /**
     * Only the author may edit or delete a quick link — bookmarks are
     * personal, so not even admins manage someone else's (NotePolicy stance).
     */
    public function update(User $user, QuickLink $quickLink): bool
    {
        return $quickLink->user_id === $user->id;
    }

    public function delete(User $user, QuickLink $quickLink): bool
    {
        return $this->update($user, $quickLink);
    }
}
