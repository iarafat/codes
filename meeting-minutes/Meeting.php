<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'subject', 'location_id', 'start', 'end',
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
     * Get the project that has that meeting.
     */
    public function projects()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the location of the meeting.
     */
    public function locations()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the agendas for the meeting.
     */
    /*public function agendas() {
        return $this->hasMany(Agenda::class);
    }*/
    public function agendas()
    {
        return $this->morphMany(Agenda::class, 'agendatable');
    }

    /**
     * Get the Follow Up of the meeting.
     */
    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the Decision of the meeting.
     */
    public function decisions()
    {
        return $this->hasMany(Decision::class);
    }

    /**
     * Get the users of the meeting.
     */
    public function user()
    {
        return $this->belongsToMany(User::class, 'personnel')
            ->withPivot('type');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function personnels()
    {
        return $this->morphMany(Personnel::class, 'personneltable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    /*public function callers() {
        return $this->user()->wherePivot('type', 'callers');
    }*/
    public function callers()
    {
        return $this->personnels();
    }
    /**
     * Get the attendees of the meeting.
     */
    /*public function attendees() {
        return $this->user()
            ->wherePivot('type', 'attendees');
    }*/
    public function attendees()
    {
        return $this->personnels();
    }

    /**
     * Get the document writers of the meeting.
     */
    public function documentWriters()
    {
        return $this->user()
            ->wherePivot('type', 'document_writers');
    }

    /**
     * Get all of the tags for the post.
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * get the meeting
     */
    public function scopeAllMeeting($query)
    {
        return $query->get();
    }

    /**
     * get the Meeting With Relations
     */
    public function scopeMeetingWithRelations($query)
    {
        return $query->with('projects', 'locations', 'tags')->get();
    }

    /**
     * get the Meeting With all Relations
     */
    public function scopeMeetingWithAllRelations($query, $id)
    {
        return $query->where('id', $id)->with(['projects', 'locations', 'agendas', 'followUps', 'tags',
            'attendees' => function ($query) use ($id) {
                $query->where('user_type', 'attendees')->get();
            },
            'callers' => function ($query) use ($id) {
                $query->where('user_type', 'callers')->get();
            }

        ])->first();
    }

    /**
     * get the Trash Meeting With Relations
     */
    public function scopeTrashMeetingWithRelations($query)
    {
        return $query->onlyTrashed()->with('projects', 'locations', 'tags')->get();
    }

    /**
     * Get meeting with project
     */
    public function scopeMeetingWithProject($query, $meeting)
    {
        return $query->where('id', $meeting)->with(['projects', 'locations',
            'attendees' => function ($query) use ($meeting) {
            $query->where('user_type', 'attendees')->get();
        }, 'callers' => function ($query) use ($meeting) {
            $query->where('user_type', 'callers')->get();
        }, 'agendas', 'followUps', 'tags'])->first();
    }
}
