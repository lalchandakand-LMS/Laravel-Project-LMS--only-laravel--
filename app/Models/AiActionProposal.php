<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiActionProposal extends Model
{
    protected $fillable = [
        'ai_agent_run_id', 'user_id', 'action_type', 'payload', 'status',
        'reviewed_by', 'reviewed_at', 'executed_at', 'result', 'failure_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'reviewed_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function run() { return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
