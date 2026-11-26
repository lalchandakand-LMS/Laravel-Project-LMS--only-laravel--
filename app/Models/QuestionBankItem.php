<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankItem extends Model
{
    protected $fillable = [
        'created_by', 'category', 'difficulty', 'type', 'question',
        'options', 'correct_option_index', 'marks', 'explanation',
    ];

    protected $casts = [
        'options' => 'array',
        'marks' => 'float',
        'correct_option_index' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
