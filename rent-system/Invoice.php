<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'amount',
        'is_paid',
        'tenant_id',
    ];

    /**
     * @var array
     */
    protected $attributes = [
        'is_paid' => 0,
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function details()
    {
        return $this->hasMany(InvoiceDetails::class, 'invoice_id');
    }

    /**
     * @param $tenant
     * @return array
     */
    public static function tenantHaveInvoiceCheckingForDate($tenant)
    {
        $tenantInvoice = Invoice::where('tenant_id', $tenant->id)->orderBy('created_at', 'desc')->first();
        $date = [];
        if (is_null($tenantInvoice)){
            $date['start_date'] = self::settingUpDate(Carbon::now()->firstOfMonth());
            $date['end_date'] = self::settingUpDate(Carbon::now()->lastOfMonth());
            $date['month'] = Carbon::now()->format('F, Y');
        } else {
            $date['start_date'] = self::settingUpDate(Carbon::parse($tenantInvoice->start_date)->addMonth());
            $date['end_date'] = self::settingUpDate(Carbon::parse($tenantInvoice->start_date)->addMonth()->lastOfMonth());
            $date['month'] = Carbon::parse($tenantInvoice->start_date)->addMonth()->format('F, Y');
        }
        return $date;
    }

    /**
     * @param $date
     * @return string
     */
    private static function settingUpDate($date)
    {
        return Carbon::parse($date)->toDateString();
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeIndex($query)
    {
        return $query->with('tenant')->orderBy('is_paid', 'asc')->get();
    }

}
