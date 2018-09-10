<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    use SoftDeletes;

    protected function setPrimaryKey($key)
    {
        $this->primaryKey = $key;
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'location_id', 'subject', 'start', 'end', 'instruction', 'status',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($meeting) {
            $meeting->start = date('Y-m-d H:i:s', strtotime($meeting->start));
            $meeting->end = date('Y-m-d H:i:s', strtotime($meeting->end));
        });

        static::updating(function ($meetingUpdate) {
            $meetingUpdate->start = date('Y-m-d H:i:s', strtotime($meetingUpdate->start));
            $meetingUpdate->end = date('Y-m-d H:i:s', strtotime($meetingUpdate->end));
        });
    }

    /**
     * Get the project that has that invitation.
     */
    public function projects()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the location of the invitation.
     */
    public function locations()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the agendas for the invitation.
     */
    public function agendas() {
        return $this->morphMany(Agenda::class, 'agendatable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function personnels()
    {
        return $this->morphMany(Personnel::class, 'personneltable');
    }

    public function callers() {
        return $this->personnels();
    }

    public function attendees() {
        return $this->personnels();
    }

    public function scopeIndex($query)
    {
        return $query->with('projects.locations', 'locations')->where('status', 'open')->orderBy('id', 'desc')->get();
    }

    public function scopeHistory($query)
    {
        return $query->with('projects.locations', 'locations')->where('status', 'done')->orWhere('status', 'cancel')->orderBy('id', 'desc')->get();
    }


    public function scopeEditInvitation($query, $id)
    {

    }
    /**
     * get the Invitation With all Relations
     */
    public function scopeInvitationWithAllRelations($query, $id)
    {
        return $query->where('id', $id)->with(['projects', 'locations', 'agendas', 'attendees' => function ($query) use ($id) {
                $query->where('user_type', 'attendees')->get();
            },
            'callers' => function ($query) use ($id) {
                $query->where('user_type', 'callers')->get();
            }

        ])->first();
    }
    public function scopeTrashInvitationWithRelations($query)
    {
        return $query->onlyTrashed()->with('projects.locations', 'locations')->where('status', 'open')->orderBy('id', 'desc')->get();
    }
}
