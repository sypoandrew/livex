<?php

namespace Sypo\Livex\Models;

use Illuminate\Database\Eloquent\Model;

class SearchMarketProcess extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'sypo_livex_import';

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

	/**
	 * Get the total number of pages to process
	 */
	public function total_pages()
	{
		return ceil($this->total_items / $this->page_size);
	}

	/**
	 * Reset the process counter to run again
	 */
	public function reset_process()
	{
		$this->decrement('current_page');
		$this->where('id', 1)->update(['complete' => true]);
	}

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

	/**
	 * Get the items linked to this process
	 */
	public function items()
	{
		return $this->hasMany('Sypo\Livex\Models\SearchMarketItem', 'sypo_livex_import_id');
	}

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
