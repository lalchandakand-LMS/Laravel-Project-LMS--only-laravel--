<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryBook extends Model
{
    protected $fillable = [
        'branch_id', 'title', 'author', 'isbn', 'category', 'description',
        'shelf_location', 'total_copies', 'available_copies', 'is_active',
    ];

    protected $casts = [
        'total_copies' => 'integer',
        'available_copies' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function loans() { return $this->hasMany(LibraryLoan::class, 'book_id'); }
}
