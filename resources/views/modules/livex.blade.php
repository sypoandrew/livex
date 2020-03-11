@extends('admin::layouts.main')

@section('content')
    <div class="flex pb-2 mb-4">
        <h2 class="flex-1 m-0 p-0">Liv-Ex</h2>
    </div>
    @include('admin::partials.alerts')
	<form action="{{ route('admin.modules.livex') }}" method="post" class="flex flex-wrap">
		<div class="card mt-4 w-full">
			<h3>Duty-paid rate settings</h3>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Rates per X litre</label>
			<input type="text" id="litre_calc" name="litre_calc" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.litre_calc') }}">
			</div>
			<!--<div class="mt-4 w-full">
			<label for="enabled" class="block">Still Wine Rate</label>
			<input type="text" id="still_wine_rate" name="still_wine_rate" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.still_wine_rate') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Sparkling Wine Rate</label>
			<input type="text" id="sparkling_wine_rate" name="sparkling_wine_rate" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.sparkling_wine_rate') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Fortified Wine Rate</label>
			<input type="text" id="fortified_wine_rate" name="fortified_wine_rate" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.fortified_wine_rate') }}">
			</div>-->
		</div>
		<div class="card mt-4 w-full">
			<h3>Liv-Ex API settings for Aero Commerce</h3>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Enabled</label>
			<input type="text" id="enabled" name="enabled" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.enabled') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Stock Threshold</label>
			<input type="text" id="stock_threshold" name="stock_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.stock_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Price Threshold</label>
			<input type="text" id="price_threshold" name="price_threshold" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.price_threshold') }}">
			</div>
			<div class="mt-4 w-full">
			<label for="enabled" class="block">Margin Markup %</label>
			<input type="text" id="margin_markup" name="margin_markup" autocomplete="off" required="required" class="w-full " value="{{ setting('Livex.margin_markup') }}">
			</div>
		</div>
		
		<div class="card mt-4 p-4 w-full flex flex-wrap"><button type="submit" class="btn btn-secondary">Save</button> </div>
	</form>
		
@endsection
