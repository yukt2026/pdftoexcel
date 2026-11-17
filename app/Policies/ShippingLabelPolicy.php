<?php

namespace App\Policies;

use App\Models\ShippingLabel;
use App\Models\User;

class ShippingLabelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, ShippingLabel $label): bool
    {
        return $user->isAdmin() || ($user->isActive() && $label->user_id === $user->id);
    }

    public function update(User $user, ShippingLabel $label): bool
    {
        return $user->isAdmin() || ($user->isActive() && $label->user_id === $user->id);
    }

    public function delete(User $user, ShippingLabel $label): bool
    {
        return $user->isAdmin() || ($user->isActive() && $label->user_id === $user->id);
    }
}
