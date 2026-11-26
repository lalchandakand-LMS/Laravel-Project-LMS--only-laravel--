<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryLoan extends Model
{
    protected $fillable = [
        'book_id', 'student_id', 'requested_at', 'issued_by', 'reviewed_by', 'reviewed_at', 'issued_at',
        'due_at', 'returned_at', 'status', 'fine_amount', 'notes', 'return_condition',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'fine_amount' => 'float',
    ];

    public function book() { return $this->belongsTo(LibraryBook::class, 'book_id'); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isOverdue(): bool
    {
        return $this->status === 'issued' && $this->due_at !== null && $this->due_at->isPast();
    }
}
