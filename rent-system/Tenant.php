<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $fillable = [
        'title',
        'code',
        'contact_person_name',
        'contact_person_phone',
        'contact_person_address',
        'contact_person_email',
        'rentable_unit_id',
        'rent',
        'advance',
        'start_from',
        'end_at',
    ];

    /**
     * @var array
     */
    protected $attributes = [
        'end_at' => null,
    ];

    /**
     * formatting the date time for db.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($tenant) {
            $tenant->start_from = Carbon::parse($tenant->start_from)->toDateString();
            if (isset($tenant->end_at) && !empty($tenant->end_at)) {
                $tenant->end_at = Carbon::parse($tenant->end_at)->toDateString();
            }
        });

        static::updating(function ($tenant) {
            $tenant->start_from = Carbon::parse($tenant->start_from)->toDateString();
            if (isset($tenant->end_at) && !empty($tenant->end_at)) {
                $tenant->end_at = Carbon::parse($tenant->end_at)->toDateString();
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unit()
    {
        return $this->belongsTo(RentableUnit::class, 'rentable_unit_id');
    }

    public function rentableUnit()
    {
        return $this->belongsTo(RentableUnit::class, 'rentable_unit_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'tenant_id');
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeIndex($query)
    {
        return $query->with('unit.floor.building')->orderBy('id', 'asc')->get();
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeTrash($query)
    {
        return $query->onlyTrashed()->with('unit.floor.building')->orderBy('id', 'desc')->get();
    }

    public function scopeSingleTenant($query, $id)
    {
        return $query->where('id', $id)->with('unit.floor.building')->orderBy('id', 'desc')->first();
    }
}
