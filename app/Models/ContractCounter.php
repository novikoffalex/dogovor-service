<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ContractCounter extends Model
{
    protected $fillable = ['date', 'counter'];
    
    protected $casts = [
        'date' => 'date',
    ];
    
    /**
     * Получить или создать счетчик для указанной даты
     */
    public static function getCounterForDate($date = null)
    {
        $date = $date ?: now()->toDateString();
        
        return static::firstOrCreate(
            ['date' => $date],
            ['counter' => 0]
        );
    }
    
    /**
     * Увеличить счетчик и вернуть новое значение
     */
    public static function incrementCounterForDate($date = null)
    {
        $date = $date ?: now()->toDateString();
        
        $counter = static::getCounterForDate($date);
        $counter->increment('counter');
        
        return $counter->counter;
    }
}
