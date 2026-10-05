<?php

namespace App\Models\Lms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ForumPost extends Model
{
    protected $table = 'lms_forum_post';

    protected $fillable = [
        'topik_id',
        'parent_id',
        'user_id',
        'nama_penulis',
        'isi',
    ];

    public function topik()
    {
        return $this->belongsTo(ForumTopik::class, 'topik_id');
    }

    public function parent()
    {
        return $this->belongsTo(ForumPost::class, 'parent_id');
    }

    public function balasan()
    {
        return $this->hasMany(ForumPost::class, 'parent_id')->orderBy('created_at');
    }

    public function penulis()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
