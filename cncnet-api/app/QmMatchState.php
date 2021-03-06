<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class QmMatchState extends Model {

	//
    public function state()
    {
        return $this->belongsTo('App\StateType');
    }

    public function qmMatch()
    {
        return $this->belongsTo('App\QmMatch');
    }

    public function player()
    {
        return $this->belongsTo('App\Player');
    }
}
