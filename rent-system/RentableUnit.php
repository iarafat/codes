<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentableUnit extends Model
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $fillable = ['floor_id', 'title', 'is_rented'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeIndex($query)
    {
        return $query->with('floor.building')->orderBy('id', 'desc')->get();
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeTrash($query)
    {
        return $query->onlyTrashed()->with('floor.building')->orderBy('id', 'desc')->get();
    }

    /**
     * @param $query
     * @param $floorId
     * @param $startForm
     * @return mixed
     */
    public function scopeAvailableUnits($query, $floorId, $startForm)
    {
        return $query->where('floor_id', $floorId)
            ->whereDoesntHave('tenants', function ($query) use ($startForm) {
                $query->whereNull('end_at');
                if($startForm != '') {
                    $query->orWhereDate('end_at', '>', Carbon::parse($startForm)->toDateString());
                }
            })->get();
    }
}
