<?php

namespace App;

use Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use SoftDeletes;
    protected $fillable = ['title', 'capacity'];
    protected $dates = ['deleted_at'];

    public function programs()
    {
        return $this->belongsToMany(Program::class)->withPivot(
            'expected_start_datatime',
            'expected_end_datatime',
            'actual_start_datatime',
            'actual_end_datatime',
            'sequence_id',
            'is_need_first'
        )->orderBy('machine_program.sequence_id', 'asc');
    }

    public function programsBorderBy()
    {
        return $this->programs();
    }



    public function getUsableCapacityAttribute()
    {
        $percentage = Setting::config('dyeing_percentage');
        if (!$percentage || !is_numeric($percentage) || $percentage <= 0) {
            return $this->capacity;
        }
        $unUsableCapacity = ($this->capacity * $percentage) / 100;
        return $this->capacity - $unUsableCapacity;
    }
}
