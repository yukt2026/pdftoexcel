<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Document $document): bool
    {
        return $user->isAdmin() || ($user->isActive() && $document->user_id === $user->id);
    }

    public function process(User $user, Document $document): bool
    {
        return $user->isAdmin() || ($user->isActive() && $document->user_id === $user->id);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin() || ($user->isActive() && $document->user_id === $user->id);
    }
}
