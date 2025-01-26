<?php

namespace MrNewport\LaravelDocSign\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';

    protected $fillable = [
        'title', 'file_path', 'status', 'data', 'signature_provider', 'signers'
    ];

    protected $casts = [
        'data' => 'array',
        'signers' => 'array',
    ];
}
