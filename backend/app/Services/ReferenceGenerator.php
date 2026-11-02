<?php

namespace App\Services;

use App\Models\Sequence;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Generates the human-readable references used on ERP documents
 * (SO-2026-0001, PO-2026-0014, FAC-2026-0007 ...).
 *
 * The counter is scoped per document type *and* per year, and is incremented
 * inside a transaction with a row lock so concurrent requests cannot produce
 * duplicate numbers.
 */
class ReferenceGenerator
{
    public function next(string $key, string $prefix, ?DateTimeInterface $date = null): string
    {
        $date ??= now();
        $year = (int) $date->format('Y');

        return sprintf('%s-%d-%04d', $prefix, $year, $this->nextNumber($key.':'.$year));
    }

    private function nextNumber(string $sequenceKey): int
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($sequenceKey) {
                    $sequence = Sequence::query()
                        ->where('key', $sequenceKey)
                        ->lockForUpdate()
                        ->first();

                    if ($sequence === null) {
                        Sequence::create(['key' => $sequenceKey, 'value' => 1]);

                        return 1;
                    }

                    $next = (int) $sequence->value + 1;
                    $sequence->value = $next;
                    $sequence->save();

                    return $next;
                });
            } catch (QueryException $e) {
                // Two requests created the sequence row at the same time.
                $lastException = $e;
            }
        }

        throw $lastException;
    }
}
