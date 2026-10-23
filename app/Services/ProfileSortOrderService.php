<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProfileSortOrderService
{
    /**
     * Persist a new ordering for a profile section.
     *
     * The submitted ids must be exactly the ids that belong to the profile,
     * otherwise a crafted request could reorder (or reference) another user's rows.
     *
     * @param  Collection<int, Model>  $items
     * @param  list<int>  $ids
     */
    public function reorder(Collection $items, array $ids): void
    {
        $existing = $items->pluck('id')->sort()->values()->all();
        $submitted = collect($ids)->sort()->values()->all();

        if ($existing !== $submitted) {
            throw ValidationException::withMessages([
                'ids' => 'Urutan tidak valid: daftar item tidak cocok dengan data profil.',
            ]);
        }

        foreach ($ids as $position => $id) {
            $items->firstWhere('id', $id)?->update(['sort_order' => $position]);
        }
    }
}
