<?php

namespace App\Policies;

use App\Models\DocumentBatch;
use App\Models\User;

class DocumentBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, DocumentBatch $batch): bool
    {
        return $user->isAdmin() || ($user->isActive() && $batch->user_id === $user->id);
    }

    public function delete(User $user, DocumentBatch $batch): bool
    {
        return $user->isAdmin() || ($user->isActive() && $batch->user_id === $user->id);
    }

    public function retry(User $user, DocumentBatch $batch): bool
    {
        return $user->isAdmin() || ($user->isActive() && $batch->user_id === $user->id);
    }
}
