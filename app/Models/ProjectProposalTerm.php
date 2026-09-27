<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projeye kopyalanmış, düzenlenebilir teklif şartı. */
class ProjectProposalTerm extends Model
{
    protected $fillable = ['project_id', 'template_id', 'title', 'body', 'sort_order', 'is_enabled'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProposalTermTemplate::class, 'template_id');
    }

    /** Aktif şablonları projeye kopyala (mevcutları silmez; boş projede kullanılır). */
    public static function seedFromTemplates(Project $project): void
    {
        $order = (int) $project->proposalTerms()->max('sort_order');
        foreach (ProposalTermTemplate::active()->get() as $template) {
            $project->proposalTerms()->create([
                'template_id' => $template->id,
                'title' => $template->title,
                'body' => $template->body,
                'sort_order' => ++$order,
                'is_enabled' => true,
            ]);
        }
    }
}
