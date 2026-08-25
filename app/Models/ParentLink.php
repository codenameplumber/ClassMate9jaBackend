<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ParentLink extends Model
{
    protected $fillable = ['parent_id', 'student_id', 'code', 'report_day', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function parent(): BelongsTo { return $this->belongsTo(User::class, 'parent_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
