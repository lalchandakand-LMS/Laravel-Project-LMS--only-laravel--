<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgentRun extends Model
{
    protected $fillable = [
        'user_id', 'agent_type', 'prompt', 'response', 'status',
        'provider', 'model', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function proposals() { return $this->hasMany(AiActionProposal::class, 'ai_agent_run_id'); }
}
