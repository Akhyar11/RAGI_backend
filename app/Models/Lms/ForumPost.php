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

    /**
     * Penanda `is_edited` dihitung dari timestamp, tidak disimpan di kolom
     * tersendiri agar tidak ada dua sumber kebenaran.
     */
    protected $appends = ['is_edited'];

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

    /**
     * Apakah pesan pernah diedit (dibandingkan dengan waktu dibuat).
     */
    public function getIsEditedAttribute(): bool
    {
        if (! $this->created_at || ! $this->updated_at) {
            return false;
        }

        return $this->updated_at->gt($this->created_at);
    }
}
