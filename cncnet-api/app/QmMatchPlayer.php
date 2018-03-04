<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class QmMatchPlayer extends Model {

	//
    public function matches()
    {
        return $this->belongsTo('App\QmMatch');
    }

    public function readyMatch()
    {
        return $this->matches()->where('status', 'ready')->get();;
    }

    public function player()
    {
        return $this->belongsTo('App\Player');
    }

    public function ladder()
    {
        return DB::where('App\Ladder');
    }

    protected $_map_side_array = null;
    public function map_side_array()
    {
        if ($this->_map_side_array === null)
        {
            $this->_map_side_array = explode(',', $this->map_sides);
        }
        return $this->_map_side_array;
    }
}
