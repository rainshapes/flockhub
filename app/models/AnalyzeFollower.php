<?php


class AnalyzeFollower extends Eloquent{
	protected $table = 'analyzeFollower';

	public function user()
	{
		$this->belongsTo('User', 'user_id');
	}

}
