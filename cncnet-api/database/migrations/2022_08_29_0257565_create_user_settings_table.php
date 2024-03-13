<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserSettingsTable extends Migration {

	/**
	 * Run the migrations.
	 * 
	 * Create a table for QM User settings
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user_settings', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('user_id')->unsigned();
			$table->foreign('user_id')
				  ->references('id')->on('users')
				  ->onDelete('cascade');
			$table->boolean('enableAnonymous')->default(false);	//flag to determine if player is 'anonymous', their player details will be hidden from player profile
			$table->boolean('disabledPointFilter')->default(false); //flag to determine if when entering queue they can ignore point filter when matching players with less pts to find matches quicker
		});

		//initialize user settings for all users
		\App\Models\User::chunk(500, function ($allUsers)  {
			foreach ($allUsers as $user)
			{
				$userSettings = new \App\Models\UserSettings();
				$userSettings->user_id = $user->id;
				$userSettings->save();
			}
		});

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('user_settings', function(Blueprint $table) {
			$table->dropForeign('user_settings_user_id_foreign');
			$table->dropColumn('user_id');
		});
	}

}