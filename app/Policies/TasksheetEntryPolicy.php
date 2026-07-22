<?php

namespace App\Policies;

use App\Models\TasksheetEntry;
use App\Models\User;

class TasksheetEntryPolicy
{
    /**
     * A member may save their own row; leads (admin, CTO, team lead) may
     * correct anyone's. Also authorized against unsaved rows (upsert).
     */
    public function update(User $user, TasksheetEntry $entry): bool
    {
        return $entry->user_id === $user->id || $user->isLead();
    }
}
