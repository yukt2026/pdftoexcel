<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isActive() && $invoice->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isActive() && $invoice->user_id === $user->id);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isActive() && $invoice->user_id === $user->id);
    }

    public function export(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isActive() && $invoice->user_id === $user->id);
    }
}
