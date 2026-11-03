<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes human readable entries to the activity journal.
 */
class AuditLogger
{
    private const LABELS = [
        'Client' => 'Client',
        'Supplier' => 'Fournisseur',
        'Product' => 'Produit',
        'PurchaseOrder' => 'Commande fournisseur',
        'SalesOrder' => 'Commande client',
        'Quote' => 'Devis',
        'Invoice' => 'Facture',
        'Payment' => 'Paiement',
    ];

    private const VERBS = [
        'created' => 'créé',
        'updated' => 'modifié',
        'deleted' => 'supprimé',
    ];

    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(string $event, Model $subject, array $changes = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'description' => $this->describe($event, $subject),
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function describe(string $event, Model $subject): string
    {
        $label = self::LABELS[class_basename($subject)] ?? class_basename($subject);

        $identity = $subject->getAttribute('reference')
            ?? $subject->getAttribute('name')
            ?? $subject->getAttribute('sku')
            ?? '#'.$subject->getKey();

        return sprintf('%s %s %s', $label, $identity, self::VERBS[$event] ?? $event);
    }
}
