<?php

namespace App\Policies;

use App\Models\ShippingDocument;
use App\Models\User;

class ShippingDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, ShippingDocument $shipping): bool
    {
        return $user->isAdmin() || ($user->isActive() && $shipping->user_id === $user->id);
    }

    public function update(User $user, ShippingDocument $shipping): bool
    {
        return $user->isAdmin() || ($user->isActive() && $shipping->user_id === $user->id);
    }

    public function delete(User $user, ShippingDocument $shipping): bool
    {
        return $user->isAdmin() || ($user->isActive() && $shipping->user_id === $user->id);
    }
}
